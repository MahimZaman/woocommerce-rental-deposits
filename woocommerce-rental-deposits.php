<?php
/**
 * Plugin Name: WooCommerce Rental Deposits (Stripe Manual Capture)
 * Author: Mahim Zaman
 * Author URI: https://github.com/MahimZaman
 * Description: Rental deposit system using Stripe PaymentIntents (manual capture). Webhooks are the single source of truth.
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: wcrd
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WCRD_PATH', plugin_dir_path(__FILE__));
define('WCRP_DIR', __FILE__);
define('WCRD_URL', plugin_dir_url(__FILE__));
define('WCRD_VERSION', '1.0.0');

require_once WCRD_PATH . 'includes/class-wcrd-loader.php';
require_once WCRD_PATH . 'includes/class-wcrd-dispute_manager.php';
register_activation_hook(WCRP_DIR, 'wcrd_activator');

function wcrd_activator()
{
    WCRD_Dispute_Manager::create_database();
}

function wcrd_init()
{

    $loader = new WCRD_Loader();
    $loader->init();
}

add_action('plugins_loaded', 'wcrd_init');
