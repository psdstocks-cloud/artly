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

        add_action( 'woocommerce_before_calculate_totals', [ __CLASS__, 'inject_dynamic_price' ], 20, 1 );
        add_filter( 'woocommerce_get_item_data', [ __CLASS__, 'display_item_meta' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ __CLASS__, 'persist_item_meta' ], 10, 4 );
        add_filter( 'woocommerce_cart_item_name', [ __CLASS__, 'filter_cart_item_name' ], 10, 3 );
        add_filter( 'woocommerce_order_item_name', [ __CLASS__, 'filter_order_item_name' ], 10, 3 );

        add_action( 'woocommerce_order_status_completed', [ __CLASS__, 'credit_wallet_on_complete' ] );
        add_action( 'woocommerce_order_status_processing', [ __CLASS__, 'credit_wallet_on_complete' ] );
    }

    protected static function get_tiers_points_to_price( $currency = 'EGP' ) {
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

        $tiers         = self::get_tiers_points_to_price( $currency );
        $display_price = 0.0;

        foreach ( $tiers as $cap => $tier_price ) {
            if ( $points <= $cap ) {
                $display_price = (float) $tier_price;
                break;
            }
        }

        $display_unit = $points > 0 ? round( $display_price / $points, 4 ) : 0.0;
        $store_price  = self::convert_to_store_currency( $display_price, $currency );
        $store_unit   = $points > 0 ? round( $store_price / $points, 4 ) : 0.0;

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
        register_rest_route(
            'artly/v1',
            '/points/add-to-cart',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ __CLASS__, 'rest_add_to_cart' ],
                'permission_callback' => function() {
                    return is_user_logged_in();
                },
                'args'                => [
                    'points'   => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                    'currency' => [
                        'type'     => 'string',
                        'required' => true,
                        'enum'     => [ 'EGP', 'USD' ],
                    ],
                    'redirect' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );
    }

    public static function rest_add_to_cart( WP_REST_Request $request ) {
        if ( ! class_exists( 'WC' ) ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'WooCommerce not active',
                ],
                400
            );
        }

        if ( function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
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

        $pricing = self::compute_price( $points, $currency );
        $price   = (float) ( $pricing['store_price'] ?? 0 );
        $unit_price = (float) ( $pricing['store_unit_price'] ?? 0 );
        $display_price = (float) ( $pricing['display_price'] ?? 0 );
        $display_unit  = (float) ( $pricing['display_unit_price'] ?? 0 );
        $display_currency = $pricing['display_currency'] ?? $currency;

        if ( $price <= 0 ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Computed price is 0',
                ],
                422
            );
        }

        $product_id = self::get_hidden_product_id();

        if ( ! $product_id ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Top-up product missing',
                ],
                500
            );
        }

        if ( ! WC()->cart ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Cart is unavailable',
                ],
                500
            );
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

        $cart_key = WC()->cart->add_to_cart( $product_id, 1, 0, [], $cart_item_data );

        if ( ! $cart_key ) {
            return new WP_REST_Response(
                [
                    'success' => false,
                    'message' => 'Could not add to cart',
                ],
                500
            );
        }

        WC()->cart->calculate_totals();
        if ( method_exists( WC()->cart, 'set_session' ) ) {
            WC()->cart->set_session();
        }

        $redirect = $request->get_param( 'redirect' );

        return new WP_REST_Response(
            [
                'success'    => true,
                'cart_key'   => $cart_key,
                'price'      => wc_price( $price ),
                'unit_price' => $unit_price,
                'display_price' => $display_price,
                'display_unit_price' => $display_unit,
                'currency'   => $display_currency,
                'redirect'   => $redirect ? esc_url_raw( $redirect ) : wc_get_cart_url(),
            ],
            200
        );
    }

    public static function ensure_hidden_product() {
        if ( self::get_hidden_product_id() ) {
            return;
        }

        if ( ! class_exists( 'WC_Product_Simple' ) ) {
            return;
        }

        $product = new WC_Product_Simple();
        $product->set_name( 'Points Top-Up' );
        $product->set_sku( self::PRODUCT_SKU );
        $product->set_price( 1 );
        $product->set_regular_price( 1 );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_virtual( true );
        $product->set_sold_individually( true );
        $product->save();
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

Nehtw_Gateway_Woo_Points::init();
