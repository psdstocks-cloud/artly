<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Gateway_Woo_Points {

    const OPTION_USD_RATE = 'nehtw_usd_egp_rate';
    const PRODUCT_SKU     = 'ARTLY-POINTS-TOPUP';
    const META_POINTS     = '_nehtw_points_amount';
    const META_CURRENCY   = '_nehtw_currency';
    const META_UNIT_PRICE = '_nehtw_unit_price';

    public static function init() {
        add_action( 'rest_api_init', [ __CLASS__, 'register_rest' ] );
        add_action( 'init', [ __CLASS__, 'ensure_hidden_product' ] );
        
        // Also ensure product is configured on admin init (for manual fixes)
        add_action( 'admin_init', [ __CLASS__, 'ensure_hidden_product' ] );

        add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'inject_dynamic_price' ], 20, 1 );
        add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_item_meta' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'persist_item_meta' ], 10, 4 );
        add_filter( 'woocommerce_cart_item_name', [ __CLASS__, 'filter_cart_item_name' ], 10, 3 );
        add_filter( 'woocommerce_order_item_name', [ __CLASS__, 'filter_order_item_name' ], 10, 3 );

        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'credit_wallet_on_complete' ] );
        add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'credit_wallet_on_complete' ] );
    }

    /**
     * Get price per point based on points amount (matches frontend pricing)
     * This matches the getPricePerPoint() function in artly-pricing-v2.js
     */
    protected static function get_price_per_point( $points ) {
        $points = (int) $points;
        if ( $points <= 5 ) {
            return 20.0;
        }
        if ( $points <= 10 ) {
            return 17.0;
        }
        if ( $points <= 30 ) {
            return 15.0;
        }
        if ( $points <= 70 ) {
            return 14.0;
        }
        if ( $points <= 100 ) {
            return 13.0;
        }
        if ( $points <= 250 ) {
            return 11.0;
        }
        // 251-500
        return 9.5;
    }

    protected static function get_tiers_points_to_price( $currency = 'EGP' ) {
        // Legacy method - keeping for backward compatibility
        // But we'll use get_price_per_point() instead
        if ( strtoupper( $currency ) === 'USD' ) {
            return [
                100    => 10.00,
                500    => 45.00,
                1000   => 80.00,
                5000   => 350.00,
                999999 => 650.00,
            ];
        }

        return [
            100    => 300.00,
            500    => 1250.00,
            1000   => 2200.00,
            5000   => 9500.00,
            999999 => 17500.00,
        ];
    }

    protected static function compute_price( $points, $currency = 'EGP' ) {
        $points   = max( 0, intval( $points ) );
        $currency = strtoupper( $currency );

        if ( $points <= 0 ) {
            return [
                'store_price'          => 0.0,
                'store_unit_price'     => 0.0,
                'display_price'        => 0.0,
                'display_unit_price'   => 0.0,
                'display_currency'     => $currency,
            ];
        }

        // Use the same pricing calculation as the frontend (getPricePerPoint)
        $price_per_point_egp = self::get_price_per_point( $points );
        $total_price_egp    = $price_per_point_egp * $points;

        // Convert to display currency if needed
        // Use the admin setting for conversion rate, or default to 0.020 (1 EGP = 0.020 USD, or 1 USD = 50 EGP)
        $usd_egp_rate = get_option( self::OPTION_USD_RATE, 50 );
        $conversion_rate = 1 / $usd_egp_rate; // Convert from EGP/USD to USD/EGP
        
        if ( $currency === 'USD' ) {
            $display_price = $total_price_egp * $conversion_rate;
            $display_unit  = $price_per_point_egp * $conversion_rate;
        } else {
            $display_price = $total_price_egp;
            $display_unit  = $price_per_point_egp;
        }

        // Store price is always in EGP (WooCommerce base currency)
        $store_price = $total_price_egp;
        $store_unit  = $price_per_point_egp;

        return [
            'store_price'          => $store_price,
            'store_unit_price'     => $store_unit,
            'display_price'        => $display_price,
            'display_unit_price'   => $display_unit,
            'display_currency'     => $currency,
        ];
    }

    protected static function convert_to_store_currency( $amount, $currency ) {
        $amount   = (float) $amount;
        $currency = strtoupper( $currency );

        if ( ! function_exists( 'get_woocommerce_currency' ) ) {
            return $amount;
        }

        $store_currency = strtoupper( get_woocommerce_currency() );

        if ( $currency === $store_currency ) {
            return $amount;
        }

        $rate = (float) get_option( self::OPTION_USD_RATE, 50 );

        if ( $rate <= 0 ) {
            return $amount;
        }

        if ( 'USD' === $currency && 'EGP' === $store_currency ) {
            return round( $amount * $rate, 2 );
        }

        if ( 'EGP' === $currency && 'USD' === $store_currency ) {
            return round( $amount / $rate, 2 );
        }

        return $amount;
    }

    public static function register_rest() {
        // Test endpoint for debugging
        register_rest_route(
            'artly/v1',
            '/points/test',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ __CLASS__, 'rest_test' ],
                'permission_callback' => '__return_true',
            ]
        );
        
        register_rest_route(
            'artly/v1',
            '/points/add-to-cart',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'rest_add_to_cart' ],
                'permission_callback' => function() {
                    // Allow logged-in users only
                    if ( ! is_user_logged_in() ) {
                        return new WP_Error(
                            'rest_forbidden',
                            __( 'You must be logged in to add items to cart.', 'nehtw-gateway' ),
                            [ 'status' => 401 ]
                        );
                    }
                    return true;
                },
                'args'                => [
                    'points'   => [
                        'type'              => 'integer',
                        'required'          => true,
                        'validate_callback' => function( $param ) {
                            return is_numeric( $param ) && $param > 0 && $param <= 500;
                        },
                        'sanitize_callback' => 'absint',
                    ],
                    'currency' => [
                        'type'              => 'string',
                        'required'          => true,
                        'enum'              => [ 'EGP', 'USD' ],
                        'sanitize_callback' => function( $value, $request, $param ) {
                            return strtoupper( sanitize_text_field( $value ) );
                        },
                    ],
                    'redirect' => [
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                ],
            ]
        );
    }
    
    /**
     * Test endpoint to verify REST API is working
     */
    public static function rest_test( WP_REST_Request $request ) {
        return new WP_REST_Response(
            [
                'success' => true,
                'message' => 'REST API is working',
                'woocommerce_active' => class_exists( 'WooCommerce' ),
                'wc_function_exists' => function_exists( 'WC' ),
                'product_id_option' => get_option( 'artly_woocommerce_product_id', 'not set' ),
                'hidden_product_id' => self::get_hidden_product_id(),
            ],
            200
        );
    }

    public static function rest_add_to_cart( WP_REST_Request $request ) {
        // Debug logging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Nehtw REST: rest_add_to_cart called' );
            error_log( 'Nehtw REST: Request params: ' . print_r( $request->get_params(), true ) );
        }
        
        // Wrap in try-catch to catch any fatal errors
        try {
            // Check if WooCommerce is active
            if ( ! class_exists( 'WooCommerce' ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Nehtw REST: WooCommerce class not found' );
                }
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'message' => 'WooCommerce not active',
                    ],
                    400
                );
            }

            // Ensure WooCommerce is loaded and WC() function exists
            if ( ! function_exists( 'WC' ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Nehtw REST: WC() function not available' );
                }
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'message' => 'WooCommerce functions not available',
                    ],
                    500
                );
            }
            
            // Verify WC() returns an instance
            $wc_instance = WC();
            if ( ! $wc_instance ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Nehtw REST: WC() returned null' );
                }
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'message' => 'WooCommerce instance not available',
                    ],
                    500
                );
            }

            // Load cart if needed - ensure WooCommerce is fully loaded
            if ( ! did_action( 'woocommerce_init' ) ) {
                do_action( 'woocommerce_init' );
            }
            
            if ( function_exists( 'wc_load_cart' ) ) {
                wc_load_cart();
            }
            
            // Ensure cart session is started
            if ( WC()->session && ! WC()->session->has_session() ) {
                WC()->session->set_customer_session_cookie( true );
            }

            $points   = max( 0, intval( $request->get_param( 'points' ) ) );
            $currency = strtoupper( sanitize_text_field( $request->get_param( 'currency' ) ) );

            if ( $points <= 0 ) {
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'message' => 'Select points > 0',
                    ],
                    422
                );
            }

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Nehtw REST: Computing price for ' . $points . ' points in ' . $currency );
            }
            
            $pricing = self::compute_price( $points, $currency );
            $price   = (float) ( $pricing['store_price'] ?? 0 );
            $unit_price = (float) ( $pricing['store_unit_price'] ?? 0 );
            $display_price = (float) ( $pricing['display_price'] ?? 0 );
            $display_unit  = (float) ( $pricing['display_unit_price'] ?? 0 );
            $display_currency = $pricing['display_currency'] ?? $currency;

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Nehtw REST: Computed price: ' . $price . ', unit: ' . $unit_price );
            }

            if ( $price <= 0 ) {
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'message' => 'Computed price is 0',
                    ],
                    422
                );
            }

            // Try to get product ID - first from option, then from SKU
            $product_id = get_option( 'artly_woocommerce_product_id', 0 );
            
            // If no product ID from option, try to get from SKU
            if ( ! $product_id ) {
                $product_id = self::get_hidden_product_id();
            }
            
            // If still no product, try to create it
            if ( ! $product_id ) {
                self::ensure_hidden_product();
                $product_id = self::get_hidden_product_id();
            }
            
            // Verify product exists
            if ( ! $product_id ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Nehtw REST: Product ID not found, tried option and SKU lookup' );
                }
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'message' => 'Top-up product missing. Please contact support.',
                    ],
                    500
                );
            }
            
            // Verify product is valid
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Nehtw REST: Getting product ID ' . $product_id );
            }
            
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Nehtw REST: Product ID ' . $product_id . ' does not exist' );
                }
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'message' => 'Product not found. Please contact support.',
                    ],
                    500
                );
            }
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Nehtw REST: Product found: ' . $product->get_name() . ', Price: ' . $product->get_price() );
            }

            // Ensure WooCommerce cart is initialized
            if ( ! WC()->cart ) {
                if ( function_exists( 'wc_load_cart' ) ) {
                    wc_load_cart();
                }
                
                if ( ! WC()->cart ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'Nehtw REST: Cart could not be initialized' );
                    }
                    return new WP_REST_Response(
                        [
                            'success' => false,
                            'message' => 'Cart is unavailable. Please refresh the page.',
                        ],
                        500
                    );
                }
            }

            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                if ( ! empty( $cart_item['nehtw_is_points'] ) ) {
                    WC()->cart->remove_cart_item( $cart_item_key );
                }
            }

            $cart_item_data = [
                'nehtw_is_points'          => true,
                'nehtw_points_amount'      => $points,
                'nehtw_currency'           => $display_currency,
                'nehtw_dynamic_price'      => $price,
                'nehtw_dynamic_unit_price' => $unit_price,
                'nehtw_display_price'      => $display_price,
                'nehtw_display_unit_price' => $display_unit,
                'nehtw_display_currency'   => $display_currency,
                'unique_key'               => md5( $points . '|' . $currency . '|' . $price . '|' . time() ),
            ];

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Nehtw REST: Attempting to add to cart. Product ID: ' . $product_id . ', Cart item data: ' . print_r( $cart_item_data, true ) );
            }
            
            $cart_key = WC()->cart->add_to_cart( $product_id, 1, 0, [], $cart_item_data );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Nehtw REST: add_to_cart returned: ' . ( $cart_key ? $cart_key : 'false' ) );
            }

            if ( ! $cart_key ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Nehtw REST: add_to_cart returned false for product ID ' . $product_id );
                    $notices = wc_get_notices();
                    error_log( 'Nehtw REST: WooCommerce notices: ' . print_r( $notices, true ) );
                }
                return new WP_REST_Response(
                    [
                        'success' => false,
                        'message' => 'Could not add to cart. Please try again.',
                    ],
                    500
                );
            }

            WC()->cart->calculate_totals();
            if ( method_exists( WC()->cart, 'set_session' ) ) {
                WC()->cart->set_session();
            }

            $redirect = $request->get_param( 'redirect' );

            // Safely get cart URL and formatted price
            $cart_url = '';
            if ( function_exists( 'wc_get_cart_url' ) ) {
                $cart_url = wc_get_cart_url();
            } else {
                $cart_url = home_url( '/cart/' );
            }

            $formatted_price = '';
            if ( function_exists( 'wc_price' ) ) {
                $formatted_price = wc_price( $price );
            } else {
                $formatted_price = number_format( $price, 2 ) . ' ' . $display_currency;
            }

            return new WP_REST_Response(
                [
                    'success'    => true,
                    'cart_key'   => $cart_key,
                    'price'      => $formatted_price,
                    'unit_price' => $unit_price,
                    'display_price' => $display_price,
                    'display_unit_price' => $display_unit,
                    'currency'   => $display_currency,
                    'redirect'   => $redirect ? esc_url_raw( $redirect ) : $cart_url,
                ],
                200
            );
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Nehtw REST: Fatal error in rest_add_to_cart: ' . $e->getMessage() );
                error_log( 'Nehtw REST: Stack trace: ' . $e->getTraceAsString() );
            }
            
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'An error occurred: ' . $e->getMessage(),
                ],
                500
            );
        } catch ( Error $e ) {
            // Catch PHP 7+ fatal errors
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Nehtw REST: Fatal error in rest_add_to_cart: ' . $e->getMessage() );
                error_log( 'Nehtw REST: Stack trace: ' . $e->getTraceAsString() );
            }
            
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'A critical error occurred. Please check server logs.',
                ],
                500
            );
        }
    }

    public static function ensure_hidden_product() {
        $product_id = self::get_hidden_product_id();
        
        if ( $product_id ) {
            // Product exists, but ensure it's configured correctly
            $product = wc_get_product( $product_id );
            if ( $product ) {
                // Set price to 0 for dynamic pricing
                $product->set_price( '0' );
                $product->set_regular_price( '0' );
                $product->set_sale_price( '' );
                $product->set_catalog_visibility( 'hidden' );
                $product->set_virtual( true );
                $product->set_sold_individually( true );
                $product->save();
                
                // Update the option to use this product ID
                update_option( 'artly_woocommerce_product_id', $product_id );
            }
            return;
        }

        if ( ! class_exists( 'WC_Product_Simple' ) ) {
            return;
        }

        $product = new WC_Product_Simple();
        $product->set_name( 'Points Top-Up' );
        $product->set_sku( self::PRODUCT_SKU );
        $product->set_price( '0' );
        $product->set_regular_price( '0' );
        $product->set_sale_price( '' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        $product_id = $product->save();
        
        // Save the product ID to option
        if ( $product_id ) {
            update_option( 'artly_woocommerce_product_id', $product_id );
        }
    }

    protected static function get_hidden_product_id() {
        if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
            return 0;
        }

        $product_id = wc_get_product_id_by_sku( self::PRODUCT_SKU );

        return $product_id ? intval( $product_id ) : 0;
    }

    public static function inject_dynamic_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( ! did_action( 'woocommerce_before_calculate_totals' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( empty( $cart_item['nehtw_is_points'] ) ) {
                continue;
            }

            $points   = intval( $cart_item['nehtw_points_amount'] ?? 0 );
            $currency = strtoupper( $cart_item['nehtw_currency'] ?? 'EGP' );
            $pricing  = self::compute_price( $points, $currency );
            $price    = (float) ( $pricing['store_price'] ?? 0 );
            $unit     = (float) ( $pricing['store_unit_price'] ?? 0 );
            $display_price = (float) ( $pricing['display_price'] ?? 0 );
            $display_unit  = (float) ( $pricing['display_unit_price'] ?? 0 );
            $display_currency = $pricing['display_currency'] ?? $currency;

            if ( $price <= 0 ) {
                $price = 1;
            }

            if ( isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
                $cart_item['data']->set_price( $price );
                $cart_item['data']->set_regular_price( $price );
                $cart_item['data']->set_name( self::format_points_name( $points ) );
            }

            $cart->cart_contents[ $cart_item_key ][ self::META_POINTS ]     = $points;
            $cart->cart_contents[ $cart_item_key ][ self::META_CURRENCY ]   = $display_currency;
            $cart->cart_contents[ $cart_item_key ][ self::META_UNIT_PRICE ] = $unit;
            $cart->cart_contents[ $cart_item_key ]['nehtw_display_price']      = $display_price;
            $cart->cart_contents[ $cart_item_key ]['nehtw_display_unit_price'] = $display_unit;
            $cart->cart_contents[ $cart_item_key ]['nehtw_display_currency']   = $display_currency;
        }
    }

    public static function display_item_meta( $item_data, $cart_item ) {
        if ( empty( $cart_item['nehtw_is_points'] ) ) {
            return $item_data;
        }

        $points            = intval( $cart_item['nehtw_points_amount'] ?? 0 );
        $currency          = strtoupper( $cart_item['nehtw_display_currency'] ?? ( $cart_item['nehtw_currency'] ?? 'EGP' ) );
        $display_unit      = isset( $cart_item['nehtw_display_unit_price'] ) ? (float) $cart_item['nehtw_display_unit_price'] : 0;
        $store_unit        = isset( $cart_item['nehtw_dynamic_unit_price'] ) ? (float) $cart_item['nehtw_dynamic_unit_price'] : 0;
        $formatted_unit    = $display_unit > 0 ? number_format_i18n( $display_unit, 4 ) . ' ' . $currency : number_format_i18n( $store_unit, 4 );

        $item_data[] = [
            'name'  => __( 'Points', 'nehtw-gateway' ),
            'value' => esc_html( $points ),
        ];
        $item_data[] = [
            'name'  => __( 'Currency', 'nehtw-gateway' ),
            'value' => esc_html( $currency ),
        ];
        $item_data[] = [
            'name'  => __( 'Unit price', 'nehtw-gateway' ),
            'value' => esc_html( $formatted_unit ),
        ];

        return $item_data;
    }

    public static function persist_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( empty( $values['nehtw_is_points'] ) ) {
            return;
        }

        $points          = intval( $values['nehtw_points_amount'] ?? 0 );
        $currency        = strtoupper( $values['nehtw_currency'] ?? 'EGP' );
        $unit            = isset( $values['nehtw_dynamic_unit_price'] ) ? (float) $values['nehtw_dynamic_unit_price'] : 0;
        $display_price   = isset( $values['nehtw_display_price'] ) ? (float) $values['nehtw_display_price'] : 0;
        $display_unit    = isset( $values['nehtw_display_unit_price'] ) ? (float) $values['nehtw_display_unit_price'] : 0;

        $item->add_meta_data( 'Points', $points, true );
        $item->add_meta_data( 'Currency', $currency, true );
        $item->add_meta_data( 'Unit price', $unit, true );
        $item->add_meta_data( self::META_POINTS, $points, true );
        $item->add_meta_data( self::META_CURRENCY, $currency, true );
        $item->add_meta_data( self::META_UNIT_PRICE, $unit, true );
        $item->add_meta_data( '_nehtw_display_price', $display_price, true );
        $item->add_meta_data( '_nehtw_display_unit_price', $display_unit, true );
    }

    public static function filter_cart_item_name( $name, $cart_item, $cart_item_key ) {
        if ( empty( $cart_item['nehtw_is_points'] ) ) {
            return $name;
        }

        $points = intval( $cart_item['nehtw_points_amount'] ?? 0 );

        return self::format_points_name( $points );
    }

    public static function filter_order_item_name( $name, $item, $is_visible ) {
        $points = intval( $item->get_meta( self::META_POINTS, true ) );

        if ( $points <= 0 ) {
            return $name;
        }

        return self::format_points_name( $points );
    }

    protected static function format_points_name( $points ) {
        $points = max( 0, intval( $points ) );

        if ( $points <= 0 ) {
            return __( 'Points Top-Up', 'nehtw-gateway' );
        }

        return sprintf( _n( '%d point', '%d points', $points, 'nehtw-gateway' ), $points );
    }

    public static function credit_wallet_on_complete( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $user_id = $order->get_user_id();

        if ( ! $user_id ) {
            return;
        }

        $total_points = 0;

        foreach ( $order->get_items() as $item_id => $item ) {
            $points = intval( $item->get_meta( self::META_POINTS, true ) );
            if ( $points > 0 ) {
                $total_points += $points;
            }
        }

        if ( $total_points <= 0 ) {
            return;
        }

        if ( function_exists( 'nehtw_gateway_add_transaction' ) ) {
            $transaction_id = nehtw_gateway_add_transaction(
                $user_id,
                'topup_woocommerce',
                $total_points,
                [
                    'meta' => [
                        'order_id' => $order_id,
                        'reason'   => 'WooCommerce wallet top-up',
                    ],
                ]
            );

            if ( $transaction_id ) {
                $order->add_order_note(
                    sprintf(
                        __( 'Wallet credited with %1$d points (Transaction ID: %2$d).', 'nehtw-gateway' ),
                        $total_points,
                        $transaction_id
                    )
                );
            }
        }
    }
}