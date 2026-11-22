<?php

defined('ABSPATH') || exit;

class WCRD_Loader
{
    public function init()
    {
        $this->dependencies();
        $this->instances();
        $this->hooks();
    }

    private function hooks()
    {
        add_filter('woocommerce_payment_gateways', [$this, 'wcrd_register_gateway']);
        add_action('plugins_loaded', [$this, 'wcrd_init_stripe_gateway']);
    }

    public function wcrd_init_stripe_gateway() {}

    public function wcrd_register_gateway($gateways)
    {
        $gateways[] = 'WCRD_Stripe_Gateway';
        return $gateways;
    }

    private function dependencies()
    {
        $classes = [
            'admin',
            'frontend',
            'stripe-gateway',
            'woocommerce',
            'ajax',
        ];

        require_once WCRD_PATH . 'vendor/autoload.php';
        if (!empty($classes)) {
            foreach ($classes as $class) {
                require WCRD_PATH . 'includes/class-wcrd-' . $class . '.php';
            }
        }
    }

    private function instances()
    {
        new WCRD_Admin();
        new WCRD_Frontend();
        new WCRD_WooCommerce();
        new WCRD_Ajax();
    }
}
