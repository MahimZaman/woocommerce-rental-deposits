<?php

defined('ABSPATH') || exit;

class WCRD_Frontend
{
    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'public_scripts']);
    }

    public function public_scripts()
    {
        wp_enqueue_style('public', WCRD_URL . 'assets/css/public.css', [], WCRD_VERSION, 'all');
        wp_enqueue_script('public', WCRD_URL . 'assets/js/public.js', ['jquery'], WCRD_VERSION, true);
        
    }
}
