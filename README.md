# WooCommerce Rental Deposits

A WooCommerce payment gateway for rental marketplaces using Stripe Connect. It creates separate PaymentIntents for rent and a manually captured security deposit. The platform fee applies to rent. The plugin also provides order and vendor onboarding shortcodes.

## Installation

Copy the repository contents into `wp-content/plugins/woocommerce-rental-deposits/` on a WordPress installation with WooCommerce active. From that plugin directory, run `composer install --no-dev` to install the locked dependencies. Activate **WooCommerce Rental Deposits** from the Plugins screen.

## Configuration and usage

Configure **Rental Deposit & Split** in **WooCommerce > Settings > Payments** using Stripe test keys. Each rental line item must carry `Rental Producto`, the rental post ID. The gateway reads `security_deposit` from that post and `_stripe_account_id` from its author. Use `[wcrd_user_orders]` for the orders view and `[wcrd_stripe_connect_button]` for the onboarding button. Validate authorization, capture, and release on a staging store before processing live orders.

## Author

[Mahim Zaman](https://github.com/MahimZaman)
