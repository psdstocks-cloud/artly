<?php
/**
 * My Account Wrapper Template
 * Ensures header and footer are always visible on WooCommerce account pages
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main class="artly-dashboard-shell artly-dashboard-shell--woo">
  <div class="artly-container">
    <?php
    // Display WooCommerce notices (errors, info, success)
    if ( function_exists( 'wc_print_notices' ) ) {
        wc_print_notices();
    }

    /**
     * WooCommerce account content hooks
     * This outputs the current endpoint content:
     * - dashboard
     * - orders
     * - edit-account
     * - lost-password
     * - reset password
     * - etc.
     */
    do_action( 'woocommerce_before_my_account' );
    do_action( 'woocommerce_account_content' );
    do_action( 'woocommerce_after_my_account' );
    ?>
  </div>
</main>

<?php
get_footer();

