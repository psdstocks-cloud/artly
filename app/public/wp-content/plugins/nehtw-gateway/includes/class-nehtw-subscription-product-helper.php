<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Subscription_Product_Helper {

    const META_IS_SUBSCRIPTION       = '_nehtw_is_subscription';
    const META_POINTS                = '_nehtw_subscription_points';
    const META_INTERVAL              = '_nehtw_subscription_interval';
    const META_INTERVAL_COUNT        = '_nehtw_subscription_interval_count';
    const META_DESCRIPTION           = '_nehtw_subscription_description';

    /**
     * Determine if a product is configured as a subscription product.
     */
    public static function is_subscription_product( $product_id ) {
        $product_id = absint( $product_id );
        if ( $product_id <= 0 ) {
            return false;
        }
        $flag = get_post_meta( $product_id, self::META_IS_SUBSCRIPTION, true );
        return (bool) $flag;
    }

    /**
     * Retrieve subscription configuration stored on a product.
     */
    public static function get_subscription_config( $product_id ) {
        $product_id = absint( $product_id );
        $interval   = get_post_meta( $product_id, self::META_INTERVAL, true );
        $interval   = in_array( $interval, array( 'day', 'week', 'month', 'year' ), true ) ? $interval : 'month';

        $points = absint( get_post_meta( $product_id, self::META_POINTS, true ) );
        $interval_count = absint( get_post_meta( $product_id, self::META_INTERVAL_COUNT, true ) );
        $interval_count = max( 1, $interval_count );

        $config = array(
            'is_subscription' => self::is_subscription_product( $product_id ),
            'points'          => $points,
            'interval'        => $interval,
            'interval_count'  => $interval_count,
            'description'     => sanitize_text_field( get_post_meta( $product_id, self::META_DESCRIPTION, true ) ),
            'plan_key'        => self::get_plan_key_for_product( $product_id ),
            'plan_name'       => self::get_product_name( $product_id ),
        );

        return apply_filters( 'nehtw_subscription_product_config', $config, $product_id );
    }

    /**
     * Generate a deterministic plan key based on a product ID.
     */
    public static function get_plan_key_for_product( $product_id ) {
        $product_id = absint( $product_id );
        if ( $product_id <= 0 ) {
            return '';
        }
        return 'product_' . $product_id;
    }

    /**
     * Extract a product ID from a plan key if it matches the helper format.
     */
    public static function get_product_id_from_plan_key( $plan_key ) {
        $plan_key = (string) $plan_key;
        if ( 0 === strpos( $plan_key, 'product_' ) ) {
            return absint( substr( $plan_key, 8 ) );
        }
        return 0;
    }

    /**
     * Return a human readable product title for UI usage.
     */
    public static function get_product_name( $product_id ) {
        $product_id = absint( $product_id );
        if ( $product_id <= 0 ) {
            return '';
        }
        $title = get_the_title( $product_id );
        if ( $title ) {
            return $title;
        }
        return sprintf( __( 'Subscription #%d', 'nehtw-gateway' ), $product_id );
    }
}