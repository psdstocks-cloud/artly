<?php
/**
 * Subscription management helpers for Nehtw Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the subscriptions table name.
 */
function nehtw_gateway_get_subscriptions_table() {
    return nehtw_gateway_get_table_name( 'subscriptions' );
}

/**
 * Get a single active subscription for a user (if any).
 *
 * @param int $user_id
 * @return array|null
 */
function nehtw_gateway_get_user_active_subscription( $user_id ) {
    global $wpdb;

    $table   = nehtw_gateway_get_subscriptions_table();
    $user_id = intval( $user_id );

    if ( ! $table || $user_id <= 0 ) {
        return null;
    }

    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d AND status = %s LIMIT 1",
        $user_id,
        'active'
    );

    $row = $wpdb->get_row( $sql, ARRAY_A );

    if ( ! $row ) {
        return null;
    }

    // Decode meta
    if ( ! empty( $row['meta'] ) ) {
        $meta = json_decode( $row['meta'], true );
        if ( is_array( $meta ) ) {
            $row['meta'] = $meta;
        }
    } else {
        $row['meta'] = array();
    }

    return $row;
}

/**
 * Get all subscriptions for a user (for admin).
 *
 * @param int $user_id
 * @return array
 */
function nehtw_gateway_get_user_subscriptions( $user_id ) {
    global $wpdb;

    $table   = nehtw_gateway_get_subscriptions_table();
    $user_id = intval( $user_id );

    if ( ! $table || $user_id <= 0 ) {
        return array();
    }

    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC",
        $user_id
    );

    $rows = $wpdb->get_results( $sql, ARRAY_A );

    foreach ( $rows as &$row ) {
        if ( ! empty( $row['meta'] ) ) {
            $meta = json_decode( $row['meta'], true );
            if ( is_array( $meta ) ) {
                $row['meta'] = $meta;
            }
        } else {
            $row['meta'] = array();
        }
    }

    return $rows;
}

/**
 * Create or update a subscription for a user.
 *
 * @param array $data
 * @return int|false Subscription ID on success, false on failure
 */
function nehtw_gateway_save_subscription( $data ) {
    global $wpdb;

    $table = nehtw_gateway_get_subscriptions_table();

    if ( ! $table ) {
        return false;
    }

    $defaults = array(
        'id'                 => 0,
        'user_id'            => 0,
        'plan_key'           => '',
        'points_per_interval' => 0,
        'interval'           => 'month',
        'status'             => 'active',
        'next_renewal_at'    => gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) ),
        'meta'               => array(),
    );

    $data = wp_parse_args( $data, $defaults );

    $data['user_id']             = intval( $data['user_id'] );
    $data['points_per_interval'] = floatval( $data['points_per_interval'] );
    $data['meta']                = ! empty( $data['meta'] ) ? wp_json_encode( $data['meta'] ) : null;

    $now = gmdate( 'Y-m-d H:i:s' );

    if ( $data['id'] ) {
        $updated = $wpdb->update(
            $table,
            array(
                'plan_key'            => $data['plan_key'],
                'points_per_interval' => $data['points_per_interval'],
                'interval'           => $data['interval'],
                'status'             => $data['status'],
                'next_renewal_at'    => $data['next_renewal_at'],
                'meta'               => $data['meta'],
                'updated_at'         => $now,
            ),
            array( 'id' => $data['id'] ),
            array( '%s', '%f', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( false !== $updated ) {
            return $data['id'];
        }
        return false;
    }

    $inserted = $wpdb->insert(
        $table,
        array(
            'user_id'             => $data['user_id'],
            'plan_key'            => $data['plan_key'],
            'points_per_interval' => $data['points_per_interval'],
            'interval'           => $data['interval'],
            'status'             => $data['status'],
            'next_renewal_at'    => $data['next_renewal_at'],
            'meta'               => $data['meta'],
            'created_at'         => $now,
            'updated_at'         => $now,
        ),
        array( '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
    );

    if ( $inserted ) {
        return $wpdb->insert_id;
    }

    return false;
}

/**
 * Get subscription plans (from an option).
 *
 * @return array
 */
function nehtw_gateway_get_subscription_plans() {
    $plans = get_option( 'nehtw_gateway_subscription_plans', array() );

    if ( ! is_array( $plans ) ) {
        $plans = array();
    }

    return $plans;
}

/**
 * Save subscription plans.
 *
 * @param array $plans
 */
function nehtw_gateway_save_subscription_plans( $plans ) {
    if ( ! is_array( $plans ) ) {
        $plans = array();
    }

    update_option( 'nehtw_gateway_subscription_plans', $plans );
    
    // Auto-create/update WooCommerce products for each plan
    if ( class_exists( 'WooCommerce' ) ) {
        foreach ( $plans as $plan ) {
            nehtw_gateway_ensure_subscription_product( $plan );
        }
    }
}

/**
 * Ensure a WooCommerce product exists for a subscription plan.
 * Creates or updates the product based on plan data.
 *
 * @param array $plan Plan data array with key, name, points, price_label, etc.
 * @return int|false Product ID on success, false on failure.
 */
function nehtw_gateway_ensure_subscription_product( $plan ) {
    if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Product_Simple' ) ) {
        return false;
    }
    
    $plan_key = isset( $plan['key'] ) ? sanitize_key( $plan['key'] ) : '';
    $plan_name = isset( $plan['name'] ) ? sanitize_text_field( $plan['name'] ) : '';
    
    if ( empty( $plan_key ) || empty( $plan_name ) ) {
        return false;
    }
    
    $sku = 'ARTLY-SUB-' . strtoupper( $plan_key );
    
    // Check if product already exists by SKU
    $existing_product_id = 0;
    if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
        $existing_product_id = wc_get_product_id_by_sku( $sku );
    }
    
    // Also check if plan already has a product_id
    $plan_product_id = isset( $plan['product_id'] ) ? intval( $plan['product_id'] ) : 0;
    if ( $plan_product_id > 0 ) {
        $existing_product = wc_get_product( $plan_product_id );
        if ( $existing_product ) {
            $existing_product_id = $plan_product_id;
        }
    }
    
    // Parse price from price_label
    $price = 0;
    $price_label = isset( $plan['price_label'] ) ? $plan['price_label'] : '';
    if ( ! empty( $price_label ) ) {
        // Try to extract EGP price first (store currency)
        if ( preg_match( '/EGP\s*([\d,]+)/i', $price_label, $egp_matches ) ) {
            $price = floatval( str_replace( ',', '', $egp_matches[1] ) );
        } elseif ( preg_match( '/\$?\s*([\d,]+)/', $price_label, $usd_matches ) ) {
            // If USD, convert to EGP (assuming 50 EGP = 1 USD)
            $usd_price = floatval( str_replace( ',', '', $usd_matches[1] ) );
            $conversion_rate = get_option( 'nehtw_usd_egp_rate', 50 );
            $price = $usd_price * $conversion_rate;
        }
    }
    
    if ( $existing_product_id > 0 ) {
        // Update existing product
        $product = wc_get_product( $existing_product_id );
        if ( ! $product ) {
            return false;
        }
        
        $product->set_name( $plan_name . ' Subscription' );
        $product->set_sku( $sku );
        $product->set_price( $price > 0 ? strval( $price ) : '0' );
        $product->set_regular_price( $price > 0 ? strval( $price ) : '0' );
        $product->set_sale_price( '' );
        $product->set_catalog_visibility( 'visible' );
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        
        // Set description
        $description = isset( $plan['description'] ) ? sanitize_textarea_field( $plan['description'] ) : '';
        if ( ! empty( $description ) ) {
            $product->set_short_description( $description );
        }
        
        $product->save();
        
        // Update plan with product_id if not already set
        if ( $plan_product_id !== $existing_product_id ) {
            $plans = nehtw_gateway_get_subscription_plans();
            foreach ( $plans as &$p ) {
                if ( isset( $p['key'] ) && $p['key'] === $plan_key ) {
                    $p['product_id'] = $existing_product_id;
                    break;
                }
            }
            nehtw_gateway_save_subscription_plans( $plans );
        }
        
        return $existing_product_id;
    } else {
        // Create new product
        $product = new WC_Product_Simple();
        $product->set_name( $plan_name . ' Subscription' );
        $product->set_sku( $sku );
        $product->set_price( $price > 0 ? strval( $price ) : '0' );
        $product->set_regular_price( $price > 0 ? strval( $price ) : '0' );
        $product->set_sale_price( '' );
        $product->set_catalog_visibility( 'visible' );
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        
        // Set description
        $description = isset( $plan['description'] ) ? sanitize_textarea_field( $plan['description'] ) : '';
        if ( ! empty( $description ) ) {
            $product->set_short_description( $description );
        }
        
        $product_id = $product->save();
        
        if ( $product_id ) {
            // Update plan with new product_id
            $plans = nehtw_gateway_get_subscription_plans();
            foreach ( $plans as &$p ) {
                if ( isset( $p['key'] ) && $p['key'] === $plan_key ) {
                    $p['product_id'] = $product_id;
                    break;
                }
            }
            nehtw_gateway_save_subscription_plans( $plans );
            
            return $product_id;
        }
    }
    
    return false;
}

