<?php

defined('ABSPATH') || exit;

class WCRD_Stripe_Gateway extends WC_Payment_Gateway
{
    public $id;
    public $icon;
    public $has_fields;
    public $method_title;
    public $method_description;
    public $supports;
    public $title;
    public $description;
    public $enabled;
    public $testmode;
    public $private_key;
    public $publishable_key;

    public function __construct()
    {
        $this->id = 'wcrd_stripe'; // payment gateway plugin ID
        $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
        $this->has_fields = true; // in case you need a custom credit card form
        $this->method_title = 'Rental Deposit & Split';
        $this->method_description = 'Description of Rental Deposit payment gateway'; // will be displayed on the options page

        // gateways can support subscriptions, refunds, saved payment methods,
        // but in this tutorial we begin with simple payments
        $this->supports = array(
            'products'
        );

        // Method with all the options fields
        $this->init_form_fields();

        // Load the settings.
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->private_key = $this->testmode ? $this->get_option('test_private_key') : $this->get_option('private_key');
        $this->publishable_key = $this->testmode ? $this->get_option('test_publishable_key') : $this->get_option('publishable_key');

        // This action hook saves the settings
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        // We need custom JavaScript to obtain a token
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Plugin options, we deal with it in Step 3 too
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => 'Enable/Disable',
                'type'    => 'checkbox',
                'label'   => 'Enable Stripe Connect',
                'default' => 'yes'
            ),
            'title' => array(
                'title'       => 'Title',
                'type'        => 'text',
                'default'     => 'Credit Card (Stripe Connect)',
            ),
            'description' => array(
                'title'       => 'Description',
                'type'        => 'textarea',
                'default'     => 'Pay securely using your card.',
            ),
            'testmode' => array(
                'title'   => 'Test mode',
                'type'    => 'checkbox',
                'label'   => 'Enable Test Mode',
                'default' => 'yes'
            ),
            'test_publishable_key' => array(
                'title' => 'Test Publishable Key',
                'type'  => 'text'
            ),
            'test_private_key' => array(
                'title' => 'Test Secret Key',
                'type'  => 'password'
            ),
            'publishable_key' => array(
                'title' => 'Live Publishable Key',
                'type'  => 'text'
            ),
            'private_key' => array(
                'title' => 'Live Secret Key',
                'type'  => 'password'
            )
        );
    }

    /**
     * You will need it if you want your custom credit card form, Step 4 is about it
     */
    public function payment_fields()
    {
        ob_start()
?>
        <div id="wcrd-stripe-payment-element"></div>
        <div id="wcrd-stripe-payment-message" class="hidden" style="margin-top:10px;color:red;"></div>
        <div id="wcrd-stripe-spinner" class="hidden" style="margin-top:10px;">
            <span class="wcrd-loader"></span>
        </div>
<?php
        echo ob_get_clean();
    }

    /*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
    public function payment_scripts()
    {
        if (!is_checkout()) return;

        wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/');
        wp_enqueue_script(
            'wcrd-stripe-js',
            WCRD_URL . 'assets/js/gateway.js',
            ['jquery'],
            WCRD_VERSION,
            true
        );

        wp_localize_script('wcrd-stripe-js', 'wcrd_checkout_params', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'checkout_url' => wc_get_checkout_url(),
            'publishable_key' => $this->publishable_key
        ]);
    }

    /*
	* We're processing the payments here, everything about it is in Step 5
	*/
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        try {

            if (empty($this->private_key)) {
                throw new Exception('Stripe private key missing');
            }

            \Stripe\Stripe::setApiKey($this->private_key);

            $currency = strtolower($order->get_currency());

            $rent_total = 0;
            $deposit_total = 0;
            $vendor_id = null;

            foreach ($order->get_items() as $item) {

                // 🔹 Get rental product ID (CPT)
                $rental_product_id = $item->get_meta('Rental Producto');

                if (!$rental_product_id) {
                    continue;
                }

                // 🔹 Get post object
                $rental_post = get_post($rental_product_id);

                if (!$rental_post) {
                    continue;
                }

                // 🔹 Vendor (author of CPT)
                $vendor_id = $rental_post->post_author;

                // 🔹 Deposit from CPT meta
                $deposit = (float) get_post_meta($rental_product_id, 'security_deposit', true);

                // 🔹 Rent from order item total
                $rent = (float) $item->get_total();

                $deposit_total += $deposit * $item->get_quantity();
                $rent_total += $rent;
            }

            if (!$vendor_id) {
                throw new Exception('Vendor not found');
            }

            // 🔹 Platform fee %
            $platform_fee_percent = (float) get_option('wcrd_platform_fee', 0);

            // 💸 Fee only on rent
            $platform_fee = ($rent_total * $platform_fee_percent) / 100;

            // 🔗 Vendor Stripe account
            $vendor_stripe_account_id = get_user_meta($vendor_id, '_stripe_account_id', true);

            if (!$vendor_stripe_account_id) {
                throw new Exception('Vendor Stripe account not connected');
            }

            /**
             * =========================
             * 1. RENT PAYMENT (captured)
             * =========================
             */
            $rent_intent = \Stripe\PaymentIntent::create([
                'amount' => (int) round($rent_total * 100),
                'currency' => $currency,
                'automatic_payment_methods' => ['enabled' => true],
                'application_fee_amount' => (int) round($platform_fee * 100),
                'transfer_data' => [
                    'destination' => $vendor_stripe_account_id,
                ],
                'metadata' => [
                    'order_id' => $order_id,
                    'type' => 'rent',
                ],
            ]);

            /**
             * =========================
             * 2. DEPOSIT AUTH (manual)
             * =========================
             */
            $deposit_intent = \Stripe\PaymentIntent::create([
                'amount' => (int) round($deposit_total * 100),
                'currency' => $currency,
                'capture_method' => 'manual',
                'automatic_payment_methods' => ['enabled' => true],
                'metadata' => [
                    'order_id' => $order_id,
                    'type' => 'deposit',
                ],
            ]);

            // 💾 Save intents
            $order->update_meta_data('_rent_intent_id', $rent_intent->id);
            $order->update_meta_data('_deposit_intent_id', $deposit_intent->id);
            $order->save();

            return [
                'result'   => 'success',
                'redirect' => '#',
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {

            wc_add_notice('Stripe error: ' . $e->getMessage(), 'error');

            return ['result' => 'failure'];
        } catch (Exception $e) {

            wc_add_notice('Payment error: ' . $e->getMessage(), 'error');

            return ['result' => 'failure'];
        }
    }
}
