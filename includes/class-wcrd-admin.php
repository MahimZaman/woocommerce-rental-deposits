<?php

defined('ABSPATH') || exit;

class WCRD_Admin
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'settings_page']);
        add_action('init', [$this, 'save_settings']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
    }


    public function admin_scripts()
    {
        wp_enqueue_style('admin-style', WCRD_URL . 'assets/css/admin.css', [], WCRD_VERSION, 'all');
        wp_enqueue_script('admin-script', WCRD_URL . 'assets/js/admin.js', ['jquery'], WCRD_VERSION, true);
    }

    public function save_settings()
    {
        if (isset($_POST['wcrd_platform_fee'])) {
            update_option('wcrd_platform_fee', floatval($_POST['wcrd_platform_fee']));
        }
        if (isset($_POST['wcrd_dispute_term'])) {
            update_option('wcrd_dispute_term', intval($_POST['wcrd_dispute_term']));
        }
    }

    public function settings_page()
    {
        add_menu_page('Woo Rental Deposit', 'Woo Rental Deposit', 'manage_options', 'wcrd-settings', [$this, 'render_settings_page'], 'dashicons-admin-generic', 99);
    }

    public function render_settings_page()
    {
        require_once WCRD_PATH . 'templates/admin/settings-page.php';
    }
}
