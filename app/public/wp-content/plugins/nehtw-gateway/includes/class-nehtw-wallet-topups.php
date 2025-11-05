<?php
/**
 * Wallet top-up pack management helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get wallet top-up packs (configured in admin).
 *
 * Structure of each pack:
 * [
 *   'key'         => 'wallet_100',
 *   'name'        => 'Starter',
 *   'points'      => 100,
 *   'price_label' => 'EGP 99',
 *   'product_id'  => 123,     // WooCommerce product ID
 *   'highlight'   => bool,
 *   'description' => 'For trying Artly',
 * ]
 *
 * @return array
 */
function nehtw_gateway_get_wallet_topup_packs() {
    $packs = get_option( 'nehtw_gateway_wallet_topup_packs', array() );

    if ( ! is_array( $packs ) ) {
        $packs = array();
    }

    return $packs;
}

/**
 * Save wallet top-up packs.
 *
 * @param array $packs
 */
function nehtw_gateway_save_wallet_topup_packs( $packs ) {
    if ( ! is_array( $packs ) ) {
        $packs = array();
    }

    update_option( 'nehtw_gateway_wallet_topup_packs', $packs );
}

