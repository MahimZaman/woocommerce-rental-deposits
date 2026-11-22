<?php

defined('ABSPATH') || exit;
?>

<div class="wrap">
    <h2><?php echo __('WooCommerce Rental Deposits - Settings', 'wcrd'); ?></h2>
    <hr>
    <form method="POST" class="wcrd-settings">
        <table>
            <tbody>
                <tr>
                    <th>
                        <label for="wcrd_platform_fee" class="wcrd-label"><?php _e('Platform Fee (%)', 'wcrd'); ?></label>
                        <small class="wcrd_field_ins"><?php echo __('I.E: 6% is the platform fee. If order total is $100, $103 will be charged where 3$ is the half commission from customer and other half will be charged fromt he product owner.'); ?></small>
                    </th>
                    <td>
                        <div class="wcrd-form-group">
                            <input type="number" step="0.01" name="wcrd_platform_fee" id="wcrd_platform_fee" value="<?php echo get_option('wcrd_platform_fee'); ?>" />
                        </div>
                    </td>
                </tr>
                <tr>
                    <th>
                        <label for="wcrd_dispute_term" class="wcrd-label"><?php _e('Auto Resolve Dispute (in days)', 'wcrd'); ?></label>
                        <small class="wcrd_field_ins"><?php echo __('If no response was made for these days then the disput will auto resolve and deposit will be released.'); ?></small>
                    </th>
                    <td>
                        <div class="wcrd-form-group">
                            <input type="number" name="wcrd_dispute_term" id="wcrd_dispute_term" value="<?php echo get_option('wcrd_dispute_term'); ?>" />
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button('Save Changes') ?>
    </form>
</div>