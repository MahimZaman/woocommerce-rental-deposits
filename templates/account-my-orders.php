<?php

defined('ABSPATH') || exit;
?>

<div class="wcrd-account-content" id="wcrd-my-orders">
    <h2><?php _e('Mis Pedidos', 'wcrd'); ?></h2>
    <?php echo do_shortcode('[wcrd_user_orders]'); ?>
</div>