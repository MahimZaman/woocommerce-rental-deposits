<?php

defined('ABSPATH') || exit;

class WCRD_WooCommerce
{
    public function __construct()
    {
        add_filter('woocommerce_account_menu_items', [$this, 'wcrd_account_tabs'], 1000);
        add_action('init', [$this, 'wcrd_register_account_endpoints']);
        add_action('woocommerce_account_my-orders_endpoint', [$this, 'wcrd_my_orders']);
        add_shortcode('wcrd_user_orders', [$this, 'wcrd_user_orders']);

        add_shortcode('wcrd_stripe_connect_button', [$this, 'render_connect_button']);
        add_action('wp_ajax_wcrd_create_connect_link', [$this, 'create_connect_link']);
        add_action('wp_ajax_nopriv_wcrd_create_connect_link', [$this, 'create_connect_link']);
        add_action('template_redirect', [$this, 'handle_stripe_return']);

        add_action('woocommerce_cart_calculate_fees', [$this, 'add_platform_fee']);
    }

    public function add_platform_fee($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Prevent duplicate execution
        if (did_action('woocommerce_cart_calculate_fees') > 1) {
            return;
        }

        // 🔹 Get platform fee %
        $platform_fee_percent = (float) get_option('wcrd_platform_fee', 0);

        if ($platform_fee_percent <= 0) {
            return;
        }

        // 🔹 Half of the fee
        $half_fee_percent = $platform_fee_percent / 2;

        // 🔹 Cart total (before fees)
        $cart_total = $cart->get_subtotal();

        // 💸 Calculate fee
        $fee_amount = ($cart_total * $half_fee_percent) / 100;

        if ($fee_amount <= 0) {
            return;
        }

        // 🏷 Spanish label
        $label = 'Tarifa de plataforma';

        // Add fee (not taxable)
        $cart->add_fee($label, $fee_amount, false);
    }

    /**
     * Get Stripe Gateway Instance
     */
    private function get_gateway()
    {

        $gateways = WC()->payment_gateways->payment_gateways();

        if (empty($gateways['wcrd_stripe'])) {
            return null;
        }

        return $gateways['wcrd_stripe'];
    }

    /**
     * Shortcode Button
     */
    public function render_connect_button()
    {

        if (!is_user_logged_in()) {
            return '<p>Please login first.</p>';
        }

        $user_id = get_current_user_id();
        $connected = get_user_meta($user_id, '_stripe_connected', true);

        $label = ($connected === 'yes') ? 'Stripe Connected' : 'Connect with Stripe';

        ob_start();
?>

        <button id="wcrd-connect-stripe" class="button" <?php echo ($connected === 'yes') ? 'disabled' : ''; ?>>
            <?php echo esc_html($label); ?>
        </button>

        <script>
            jQuery(function($) {

                $('#wcrd-connect-stripe').on('click', function(e) {
                    e.preventDefault();

                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'wcrd_create_connect_link'
                    }, function(res) {

                        if (res.success) {
                            window.location.href = res.data.url;
                        } else {
                            alert(res.data.message || 'Something went wrong');
                        }

                    });

                });

            });
        </script>

    <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Create Stripe Connect Link
     */
    public function create_connect_link()
    {

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Not logged in']);
        }

        $user_id = get_current_user_id();

        try {

            $gateway = $this->get_gateway();

            if (!$gateway || empty($gateway->private_key)) {
                throw new Exception('Stripe gateway not configured');
            }

            \Stripe\Stripe::setApiKey($gateway->private_key);

            // 🔹 Check existing account
            $account_id = get_user_meta($user_id, '_stripe_account_id', true);

            // 🔹 Create if not exists
            if (!$account_id) {

                $account = \Stripe\Account::create([
                    'type' => 'express',
                ]);

                $account_id = $account->id;

                update_user_meta($user_id, '_stripe_account_id', $account_id);
            }

            // 🔹 Create onboarding link
            $account_link = \Stripe\AccountLink::create([
                'account' => $account_id,
                'refresh_url' => site_url('/stripe-refresh'),
                'return_url'  => site_url('/stripe-success'),
                'type' => 'account_onboarding',
            ]);

            wp_send_json_success([
                'url' => $account_link->url
            ]);
        } catch (Exception $e) {

            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle Stripe Return (success page)
     */
    public function handle_stripe_return()
    {

        if (!is_user_logged_in()) return;

        if (!is_page('stripe-success')) return;

        $user_id = get_current_user_id();
        $account_id = get_user_meta($user_id, '_stripe_account_id', true);

        if (!$account_id) return;

        try {

            $gateway = $this->get_gateway();

            if (!$gateway || empty($gateway->private_key)) return;

            \Stripe\Stripe::setApiKey($gateway->private_key);

            $account = \Stripe\Account::retrieve($account_id);

            if ($account->details_submitted) {
                update_user_meta($user_id, '_stripe_connected', 'yes');
            }
        } catch (Exception $e) {
            // silent fail (optional: log it)
        }
    }

    public function wcrd_user_orders()
    {

        if (!is_user_logged_in()) {
            return '<p>Please login to view your orders.</p>';
        }

        $customer_id = get_current_user_id();

        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        ob_start();

        if (empty($orders)) {
            echo '<p>No orders found.</p>';
            return ob_get_clean();
        }
    ?>

        <div class="wcrd-orders">
            <?php foreach ($orders as $order): ?>
                <div class="wcrd-single-order">

                    <div class="wcrd-order-header">
                        <div>
                            <strong>#<?php echo $order->get_id(); ?></strong>
                            <span><?php echo wc_format_datetime($order->get_date_created()); ?></span>
                        </div>
                        <div class="status status-<?php echo esc_attr($order->get_status()); ?>">
                            <?php echo wc_get_order_status_name($order->get_status()); ?>
                        </div>
                    </div>

                    <div class="wcrd-order-items">
                        <?php foreach ($order->get_items() as $item_id => $item):
                            $product = $item->get_product();
                            if (!$product) continue;

                            $product_id = $product->get_id();
                            $image = wp_get_attachment_image($product->get_image_id(), 'thumbnail');
                        ?>

                            <div class="wcrd-item">

                                <div class="wcrd-item-left">
                                    <div class="wcrd-thumb"><?php echo $image; ?></div>

                                    <div class="wcrd-meta">
                                        <h4><?php echo esc_html($item->get_name()); ?></h4>
                                        <p>Qty: <?php echo $item->get_quantity(); ?></p>
                                    </div>
                                </div>

                                <div class="wcrd-item-right">
                                    <span class="price">
                                        <?php echo wc_price($item->get_total()); ?>
                                    </span>

                                    <button class="wcrd-dispute-btn"
                                        data-order="<?php echo $order->get_id(); ?>"
                                        data-product="<?php echo $product_id; ?>">
                                        Launch Dispute
                                    </button>
                                </div>

                            </div>

                        <?php endforeach; ?>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>

<?php
        return ob_get_clean();
    }

    public function wcrd_account_tabs($items)
    {
        $items['my-orders'] = __('Mis pedidos', 'wcrd');
        return $items;
    }

    public function wcrd_register_account_endpoints()
    {
        add_rewrite_endpoint('my-orders', EP_ROOT | EP_PAGES);
    }

    public function wcrd_my_orders()
    {
        require_once WCRD_PATH . 'templates/account-my-orders.php';
    }
}
