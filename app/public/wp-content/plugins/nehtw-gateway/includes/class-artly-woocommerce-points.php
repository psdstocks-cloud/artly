<?php
/**
 * WooCommerce integration for Artly points top-ups
 *
 * - Reads ?artly_points & ?artly_total from the pricing page redirect
 * - Stores them in the cart item
 * - Sets dynamic price from artly_total
 * - Copies meta into the order item
 * - On order completion, credits the wallet via nehtw_gateway_add_transaction()
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
 * 1) Store points + total in cart item data when added from pricing page
 * Use both GET params and session to handle redirects
 */
function artly_wc_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
    $wallet_product_id = get_option( 'artly_woocommerce_product_id', 25 );
    
    // Only process our wallet product
    if ( $product_id != $wallet_product_id ) {
        return $cart_item_data;
    }
    
    // Ensure we have an array for cart item data
    if ( ! is_array( $cart_item_data ) ) {
        $cart_item_data = array();
    }
    
    // Check GET parameters first (direct URL access)
    $points = 0;
    $total = 0;
    
    if ( isset( $_GET['artly_points'] ) ) {
        $points = (int) $_GET['artly_points'];
        if ( $points > 0 ) {
            $cart_item_data['artly_points'] = $points;
            // Store in session for later retrieval if needed
            if ( function_exists( 'WC' ) && WC()->session ) {
                WC()->session->set( 'artly_points_' . $product_id, $points );
            }
        }
    }

    if ( isset( $_GET['artly_total'] ) ) {
        $total = (float) $_GET['artly_total'];
        if ( $total > 0 ) {
            $cart_item_data['artly_total'] = $total;
            // Store in session for later retrieval if needed
            if ( function_exists( 'WC' ) && WC()->session ) {
                WC()->session->set( 'artly_total_' . $product_id, $total );
            }
        }
    }
    
    // If GET params not available, try to retrieve from session
    if ( ! isset( $cart_item_data['artly_points'] ) && function_exists( 'WC' ) && WC()->session ) {
        $session_points = WC()->session->get( 'artly_points_' . $product_id );
        if ( $session_points && $session_points > 0 ) {
            $cart_item_data['artly_points'] = (int) $session_points;
        }
    }
    
    if ( ! isset( $cart_item_data['artly_total'] ) && function_exists( 'WC' ) && WC()->session ) {
        $session_total = WC()->session->get( 'artly_total_' . $product_id );
        if ( $session_total && $session_total > 0 ) {
            $cart_item_data['artly_total'] = (float) $session_total;
        }
    }
    
    // Debug logging (remove in production if needed)
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'Artly WC Cart Data: Points=' . ( isset( $cart_item_data['artly_points'] ) ? $cart_item_data['artly_points'] : '0' ) . ', Total=' . ( isset( $cart_item_data['artly_total'] ) ? $cart_item_data['artly_total'] : '0' ) );
    }

    return $cart_item_data;
}
add_filter( 'woocommerce_add_cart_item_data', 'artly_wc_add_cart_item_data', 10, 3 );

/**
 * 2) Change product price in cart dynamically based on artly_total
 * Use higher priority to ensure it runs after other price modifications
 */
function artly_wc_before_calculate_totals( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    if ( empty( $cart->get_cart() ) ) {
        return;
    }

    $wallet_product_id = get_option( 'artly_woocommerce_product_id', 25 );

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        // Only process our wallet product
        if ( ! isset( $cart_item['product_id'] ) || $cart_item['product_id'] != $wallet_product_id ) {
            continue;
        }
        
        $price_to_set = null;
        
        // Check cart item data first
        if ( isset( $cart_item['artly_total'] ) && $cart_item['artly_total'] > 0 ) {
            $price_to_set = (float) $cart_item['artly_total'];
        }
        // Check session if cart item data missing
        elseif ( function_exists( 'WC' ) && WC()->session ) {
            $session_total = WC()->session->get( 'artly_total_' . $wallet_product_id );
            if ( $session_total && $session_total > 0 ) {
                $price_to_set = (float) $session_total;
                // Store in cart item for next time
                $cart->cart_contents[ $cart_item_key ]['artly_total'] = $price_to_set;
            }
        }
        // Last resort: check GET parameters
        elseif ( isset( $_GET['artly_total'] ) && $_GET['artly_total'] > 0 ) {
            $price_to_set = (float) $_GET['artly_total'];
            // Store in cart item and session
            $cart->cart_contents[ $cart_item_key ]['artly_total'] = $price_to_set;
            if ( function_exists( 'WC' ) && WC()->session ) {
                WC()->session->set( 'artly_total_' . $wallet_product_id, $price_to_set );
            }
        }
        
        // Set the price if we found one
        if ( $price_to_set && $price_to_set > 0 && isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
            // Set all price types to ensure WooCommerce uses the correct price
            $cart_item['data']->set_price( $price_to_set );
            $cart_item['data']->set_regular_price( $price_to_set );
            $cart_item['data']->set_sale_price( '' );
            
            // Also update the cart contents directly (critical for cart display)
            $cart->cart_contents[ $cart_item_key ]['data']->set_price( $price_to_set );
            $cart->cart_contents[ $cart_item_key ]['data']->set_regular_price( $price_to_set );
            $cart->cart_contents[ $cart_item_key ]['data']->set_sale_price( '' );
            
            // Force update the line total
            $quantity = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
            $cart->cart_contents[ $cart_item_key ]['line_total'] = $price_to_set * $quantity;
            $cart->cart_contents[ $cart_item_key ]['line_subtotal'] = $price_to_set * $quantity;
            $cart->cart_contents[ $cart_item_key ]['line_tax'] = 0;
            $cart->cart_contents[ $cart_item_key ]['line_subtotal_tax'] = 0;
            
            // Debug logging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Artly WC Price Set: Item=' . $cart_item_key . ', Price=' . $price_to_set . ', Quantity=' . $quantity . ', LineTotal=' . ( $price_to_set * $quantity ) );
            }
        }
    }
}
add_action( 'woocommerce_before_calculate_totals', 'artly_wc_before_calculate_totals', 999, 1 );

/**
 * Store cart item data in session and set price when cart item is retrieved
 * CRITICAL: This runs when cart is loaded from session
 */
function artly_wc_get_cart_item_from_session( $cart_item, $values, $cart_item_key ) {
    $wallet_product_id = get_option( 'artly_woocommerce_product_id', 25 );
    
    // Only process our wallet product
    if ( ! isset( $cart_item['product_id'] ) || $cart_item['product_id'] != $wallet_product_id ) {
        return $cart_item;
    }
    
    // Restore cart item data from session values (this is how WooCommerce stores custom data)
    if ( isset( $values['artly_total'] ) ) {
        $cart_item['artly_total'] = (float) $values['artly_total'];
    }
    if ( isset( $values['artly_points'] ) ) {
        $cart_item['artly_points'] = (int) $values['artly_points'];
    }
    
    // Also check session directly if values not set (backup method)
    if ( ! isset( $cart_item['artly_total'] ) && function_exists( 'WC' ) && WC()->session ) {
        $session_total = WC()->session->get( 'artly_total_' . $wallet_product_id );
        if ( $session_total && $session_total > 0 ) {
            $cart_item['artly_total'] = (float) $session_total;
        }
    }
    
    // Set price if we have artly_total
    if ( isset( $cart_item['artly_total'] ) && $cart_item['artly_total'] > 0 ) {
        $price = (float) $cart_item['artly_total'];
        if ( $price > 0 && isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
            $cart_item['data']->set_price( $price );
            // Force price update on all price fields
            $cart_item['data']->set_sale_price( '' );
            $cart_item['data']->set_regular_price( $price );
        }
    }
    
    return $cart_item;
}
add_filter( 'woocommerce_get_cart_item_from_session', 'artly_wc_get_cart_item_from_session', 10, 3 );

/**
 * Additional hook to ensure price is set when cart is displayed
 * This runs after cart is fully loaded
 */
function artly_wc_after_cart_item_quantity_update( $cart_item_key, $quantity = 0, $old_quantity = 0 ) {
    $cart = WC()->cart;
    if ( ! $cart ) {
        return;
    }
    
    $cart_item = $cart->get_cart_item( $cart_item_key );
    if ( ! $cart_item ) {
        return;
    }
    
    $wallet_product_id = get_option( 'artly_woocommerce_product_id', 25 );
    if ( ! isset( $cart_item['product_id'] ) || $cart_item['product_id'] != $wallet_product_id ) {
        return;
    }
    
    if ( isset( $cart_item['artly_total'] ) && $cart_item['artly_total'] > 0 ) {
        $price = (float) $cart_item['artly_total'];
        if ( $price > 0 && isset( $cart_item['data'] ) ) {
            $cart_item['data']->set_price( $price );
            $cart_item['data']->set_regular_price( $price );
        }
    }
}
add_action( 'woocommerce_after_cart_item_quantity_update', 'artly_wc_after_cart_item_quantity_update', 10, 3 );

/**
 * Filter product price directly (most reliable method)
 */
function artly_wc_product_get_price( $price, $product ) {
    // Only process our wallet top-up product
    $wallet_product_id = get_option( 'artly_woocommerce_product_id', 25 );
    if ( $product->get_id() != $wallet_product_id ) {
        return $price;
    }
    
    // Check if we're in cart context
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return $price;
    }
    
    // Try to get price from cart item
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['product_id'] ) && $cart_item['product_id'] == $wallet_product_id ) {
            if ( isset( $cart_item['artly_total'] ) && $cart_item['artly_total'] > 0 ) {
                return (float) $cart_item['artly_total'];
            }
        }
        // Also check variation ID
        if ( isset( $cart_item['variation_id'] ) && $cart_item['variation_id'] == $wallet_product_id ) {
            if ( isset( $cart_item['artly_total'] ) && $cart_item['artly_total'] > 0 ) {
                return (float) $cart_item['artly_total'];
            }
        }
    }
    
    // Check session as fallback
    if ( function_exists( 'WC' ) && WC()->session ) {
        $session_total = WC()->session->get( 'artly_total_' . $wallet_product_id );
        if ( $session_total && $session_total > 0 ) {
            return (float) $session_total;
        }
    }
    
    // Last resort: check GET parameters if available
    if ( isset( $_GET['artly_total'] ) && $_GET['artly_total'] > 0 ) {
        return (float) $_GET['artly_total'];
    }
    
    return $price;
}
add_filter( 'woocommerce_product_get_price', 'artly_wc_product_get_price', 99, 2 );
add_filter( 'woocommerce_product_get_regular_price', 'artly_wc_product_get_price', 99, 2 );
add_filter( 'woocommerce_product_get_sale_price', 'artly_wc_product_get_price', 99, 2 );

/**
 * Force price update on cart page load - runs after all other hooks
 */
function artly_wc_cart_loaded_from_session() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return;
    }
    
    $wallet_product_id = get_option( 'artly_woocommerce_product_id', 25 );
    $needs_recalc = false;
    
    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( ! isset( $cart_item['product_id'] ) || $cart_item['product_id'] != $wallet_product_id ) {
            continue;
        }
        
        $price_to_set = null;
        
        // Priority 1: Check cart item data
        if ( isset( $cart_item['artly_total'] ) && $cart_item['artly_total'] > 0 ) {
            $price_to_set = (float) $cart_item['artly_total'];
        }
        // Priority 2: Check session
        elseif ( WC()->session ) {
            $session_total = WC()->session->get( 'artly_total_' . $wallet_product_id );
            if ( $session_total && $session_total > 0 ) {
                $price_to_set = (float) $session_total;
                WC()->cart->cart_contents[ $cart_item_key ]['artly_total'] = $price_to_set;
            }
        }
        // Priority 3: Check GET parameters (if still on cart page with params)
        elseif ( isset( $_GET['artly_total'] ) && $_GET['artly_total'] > 0 ) {
            $price_to_set = (float) $_GET['artly_total'];
            WC()->cart->cart_contents[ $cart_item_key ]['artly_total'] = $price_to_set;
            if ( WC()->session ) {
                WC()->session->set( 'artly_total_' . $wallet_product_id, $price_to_set );
            }
        }
        
        // Force set price
        if ( $price_to_set && $price_to_set > 0 && isset( $cart_item['data'] ) ) {
            $current_price = $cart_item['data']->get_price();
            
            // Only update if price is different (avoid unnecessary updates)
            if ( $current_price != $price_to_set ) {
                $cart_item['data']->set_price( $price_to_set );
                $cart_item['data']->set_regular_price( $price_to_set );
                $cart_item['data']->set_sale_price( '' );
                
                // Also update in cart contents directly
                WC()->cart->cart_contents[ $cart_item_key ]['data']->set_price( $price_to_set );
                WC()->cart->cart_contents[ $cart_item_key ]['data']->set_regular_price( $price_to_set );
                WC()->cart->cart_contents[ $cart_item_key ]['data']->set_sale_price( '' );
                
                $needs_recalc = true;
            }
        }
    }
    
    // Force cart recalculation if we updated prices
    if ( $needs_recalc ) {
        WC()->cart->calculate_totals();
    }
}
add_action( 'wp_loaded', 'artly_wc_cart_loaded_from_session', 20 );
add_action( 'woocommerce_cart_loaded_from_session', 'artly_wc_cart_loaded_from_session', 20 );
add_action( 'woocommerce_after_cart_item_quantity_update', 'artly_wc_cart_loaded_from_session', 20 );
add_action( 'woocommerce_cart_item_removed', 'artly_wc_cart_loaded_from_session', 20 );

/**
 * Also hook into add to cart action to capture data immediately
 */
function artly_wc_add_to_cart( $cart_item_key, $product_id, $quantity, $variation_id = 0 ) {
    // Only process if this is our wallet top-up product
    $wallet_product_id = get_option( 'artly_woocommerce_product_id', 25 );
    if ( $product_id != $wallet_product_id ) {
        return;
    }
    
    // Get data from GET parameters (they should be available when adding to cart)
    $points = 0;
    $total = 0;
    
    if ( isset( $_GET['artly_points'] ) ) {
        $points = (int) $_GET['artly_points'];
    }
    
    if ( isset( $_GET['artly_total'] ) ) {
        $total = (float) $_GET['artly_total'];
    }
    
    // If GET params not available, try session
    if ( $points == 0 && function_exists( 'WC' ) && WC()->session ) {
        $points = (int) WC()->session->get( 'artly_points_' . $product_id, 0 );
    }
    
    if ( $total == 0 && function_exists( 'WC' ) && WC()->session ) {
        $total = (float) WC()->session->get( 'artly_total_' . $product_id, 0 );
    }
    
    // Update cart item data and price immediately
    if ( $total > 0 ) {
        $cart = WC()->cart;
        if ( $cart && isset( $cart->cart_contents[ $cart_item_key ] ) ) {
            if ( $points > 0 ) {
                $cart->cart_contents[ $cart_item_key ]['artly_points'] = $points;
            }
            if ( $total > 0 ) {
                $cart->cart_contents[ $cart_item_key ]['artly_total'] = $total;
                // Immediately set price on the product object
                if ( isset( $cart->cart_contents[ $cart_item_key ]['data'] ) ) {
                    $cart->cart_contents[ $cart_item_key ]['data']->set_price( $total );
                    $cart->cart_contents[ $cart_item_key ]['data']->set_regular_price( $total );
                    $cart->cart_contents[ $cart_item_key ]['data']->set_sale_price( '' );
                    
                    // Update line totals
                    $quantity = isset( $cart->cart_contents[ $cart_item_key ]['quantity'] ) ? $cart->cart_contents[ $cart_item_key ]['quantity'] : 1;
                    $cart->cart_contents[ $cart_item_key ]['line_total'] = $total * $quantity;
                    $cart->cart_contents[ $cart_item_key ]['line_subtotal'] = $total * $quantity;
                }
            }
        }
        
        // Force cart recalculation after adding item
        if ( $cart ) {
            $cart->calculate_totals();
        }
    }
}
add_action( 'woocommerce_add_to_cart', 'artly_wc_add_to_cart', 20, 4 );


/**
 * 3) Copy cart meta into the order item (so we can read it later)
 */
function artly_wc_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['artly_points'] ) ) {
        $item->add_meta_data( 'artly_points', (int) $values['artly_points'], true );
    }

    if ( isset( $values['artly_total'] ) ) {
        $item->add_meta_data( 'artly_total', (float) $values['artly_total'], true );
    }
}
add_action( 'woocommerce_checkout_create_order_line_item', 'artly_wc_checkout_create_order_line_item', 10, 4 );

/**
 * 4) On order completion, credit user wallet with the purchased points
 */
function artly_wc_order_status_completed_add_points( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $user_id = $order->get_user_id();
    if ( ! $user_id ) {
        // Guest checkout - points cannot be credited without user account
        // Optionally: log this for manual processing or force login before checkout
        return;
    }

    $total_points = 0;

    foreach ( $order->get_items() as $item_id => $item ) {
        $points = (int) wc_get_order_item_meta( $item_id, 'artly_points', true );
        if ( $points > 0 ) {
            $total_points += $points;
        }
    }

    if ( $total_points <= 0 ) {
        return;
    }

    // Use your existing wallet transaction function
    $transaction_id = nehtw_gateway_add_transaction(
        $user_id,
        'topup_woocommerce',
        $total_points,
        array(
            'meta' => array(
                'order_id' => $order_id,
                'reason'   => 'WooCommerce wallet top-up',
            ),
        )
    );

    // Optional: Add order note
    if ( $transaction_id ) {
        $order->add_order_note(
            sprintf(
                __( 'Artly wallet credited with %d points via Nehtw Gateway (Transaction ID: %d)', 'nehtw-gateway' ),
                $total_points,
                $transaction_id
            )
        );
        
        // Store success message in order meta for potential redirect
        $order->update_meta_data( '_artly_points_added', $total_points );
        $order->save();
    }
}
add_action( 'woocommerce_order_status_completed', 'artly_wc_order_status_completed_add_points', 10, 1 );

/**
 * Also handle payment complete status (for payment gateways that mark orders as processing then completed)
 */
add_action( 'woocommerce_payment_complete', 'artly_wc_order_status_completed_add_points', 10, 1 );

/**
 * Display points in cart and checkout
 */
function artly_wc_cart_item_name( $name, $cart_item, $cart_item_key ) {
    if ( isset( $cart_item['artly_points'] ) && $cart_item['artly_points'] > 0 ) {
        $points = (int) $cart_item['artly_points'];
        $name .= ' <small class="artly-points-badge">(' . sprintf( __( '%d points', 'nehtw-gateway' ), $points ) . ')</small>';
    }
    return $name;
}
add_filter( 'woocommerce_cart_item_name', 'artly_wc_cart_item_name', 10, 3 );

/**
 * Ensure cart item price displays correctly (override any cached price)
 */
function artly_wc_cart_item_price( $price, $cart_item, $cart_item_key ) {
    $wallet_product_id = get_option( 'artly_woocommerce_product_id', 25 );
    
    if ( isset( $cart_item['product_id'] ) && $cart_item['product_id'] == $wallet_product_id ) {
        if ( isset( $cart_item['artly_total'] ) && $cart_item['artly_total'] > 0 ) {
            $total = (float) $cart_item['artly_total'];
            $price = wc_price( $total );
        }
    }
    
    return $price;
}
add_filter( 'woocommerce_cart_item_price', 'artly_wc_cart_item_price', 10, 3 );

/**
 * Ensure cart item subtotal displays correctly
 */
function artly_wc_cart_item_subtotal( $subtotal, $cart_item, $cart_item_key ) {
    $wallet_product_id = get_option( 'artly_woocommerce_product_id', 25 );
    
    if ( isset( $cart_item['product_id'] ) && $cart_item['product_id'] == $wallet_product_id ) {
        if ( isset( $cart_item['artly_total'] ) && $cart_item['artly_total'] > 0 ) {
            $total = (float) $cart_item['artly_total'];
            $quantity = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;
            $subtotal = wc_price( $total * $quantity );
        }
    }
    
    return $subtotal;
}
add_filter( 'woocommerce_cart_item_subtotal', 'artly_wc_cart_item_subtotal', 10, 3 );

/**
 * Display points in order details
 */
function artly_wc_order_item_name( $name, $item, $is_visible ) {
    $points = wc_get_order_item_meta( $item->get_id(), 'artly_points', true );
    if ( $points && $points > 0 ) {
        $name .= ' <small class="artly-points-badge">(' . sprintf( __( '%d points', 'nehtw-gateway' ), (int) $points ) . ')</small>';
    }
    return $name;
}
add_filter( 'woocommerce_order_item_name', 'artly_wc_order_item_name', 10, 3 );

