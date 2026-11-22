<?php

defined('ABSPATH') || exit;

class WCRD_Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_get_client_secret', [$this, 'get_client_secret']);
        add_action('wp_ajax_nopriv_get_client_secret', [$this, 'get_client_secret']);

        add_action('wp_ajax_create_payment_intent', [$this, 'create_payment_intent']);
        add_action('wp_ajax_nopriv_create_payment_intent', [$this, 'create_payment_intent']);
    }

    public function create_payment_intent()
    {

        if (!WC()->cart) {
            wp_send_json_error(['message' => 'Cart not found']);
        }

        $amount = (int) round(WC()->cart->total * 100);

        $gateways = WC()->payment_gateways->payment_gateways();
        $gateway = $gateways['wcrd_stripe'];

        \Stripe\Stripe::setApiKey($gateway->private_key);

        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => strtolower(get_woocommerce_currency()),
                'automatic_payment_methods' => ['enabled' => true],
            ]);

            wp_send_json_success([
                'clientSecret' => $intent->client_secret
            ]);
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function get_client_secret()
    {

        if (empty($_POST['order_id'])) {
            wp_send_json_error(['message' => 'Missing order ID']);
        }

        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);

        if (!$order) {
            wp_send_json_error(['message' => 'Invalid order']);
        }

        $client_secret = $order->get_meta('_stripe_client_secret');

        if (!$client_secret) {
            wp_send_json_error(['message' => 'No client secret found']);
        }

        wp_send_json_success([
            'clientSecret' => $client_secret
        ]);
    }
}
