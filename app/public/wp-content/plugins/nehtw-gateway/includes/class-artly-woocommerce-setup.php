<?php
/**
 * WooCommerce Product Setup Helper
 * Ensures the wallet top-up product is configured correctly for dynamic pricing
 *
 * @package Nehtw_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Check if WooCommerce is active
 */
if ( ! class_exists( 'WooCommerce' ) ) {
    return;
}

/**
 * Setup wallet product on plugin activation
 * Makes sure the product accepts dynamic pricing
 */
function artly_wc_setup_wallet_product() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    
    $product_id = get_option( 'artly_woocommerce_product_id', 25 );
    
    if ( ! $product_id ) {
        return;
    }
    
    $product = wc_get_product( $product_id );
    
    if ( ! $product ) {
        return;
    }
    
    // Set product price to 0 (we'll override it dynamically)
    $product->set_regular_price( '0' );
    $product->set_sale_price( '' );
    $product->set_price( '0' );
    
    // Make sure it's a simple product (not variable)
    if ( $product->get_type() !== 'simple' ) {
        $product->set_type( 'simple' );
    }
    
    // Enable virtual product (no shipping needed)
    $product->set_virtual( true );
    
    // Save the product
    $product->save();
}

// Hook into activation
add_action( 'nehtw_gateway_activate', 'artly_wc_setup_wallet_product' );

// Also run on admin init (for manual setup)
add_action( 'admin_init', function() {
    if ( isset( $_GET['artly_setup_wallet_product'] ) && current_user_can( 'manage_options' ) ) {
        check_admin_referer( 'artly_setup_wallet_product' );
        artly_wc_setup_wallet_product();
        wp_redirect( admin_url( 'admin.php?page=nehtw-gateway&artly_setup_done=1' ) );
        exit;
    }
} );

/**
 * Admin notice to verify product setup
 */
function artly_wc_admin_notice_product_setup() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $product_id = get_option( 'artly_woocommerce_product_id', 25 );
    
    if ( ! $product_id ) {
        return;
    }
    
    $product = wc_get_product( $product_id );
    
    if ( ! $product ) {
        echo '<div class="notice notice-error"><p>';
        echo sprintf(
            __( 'Artly: WooCommerce product ID %d does not exist. Please update the product ID in settings.', 'nehtw-gateway' ),
            $product_id
        );
        echo '</p></div>';
        return;
    }
    
    $price = $product->get_price();
    
    // Warn if product price is not 0 (should be 0 for dynamic pricing)
    if ( $price != '0' && $price != '' ) {
        echo '<div class="notice notice-warning"><p>';
        echo sprintf(
            __( 'Artly: The wallet top-up product (ID: %d) has a price of %s. For dynamic pricing to work, the product price should be set to 0. <a href="%s">Edit Product</a>', 'nehtw-gateway' ),
            $product_id,
            wc_price( $price ),
            admin_url( 'post.php?post=' . $product_id . '&action=edit' )
        );
        echo '</p></div>';
    }
}
add_action( 'admin_notices', 'artly_wc_admin_notice_product_setup' );

