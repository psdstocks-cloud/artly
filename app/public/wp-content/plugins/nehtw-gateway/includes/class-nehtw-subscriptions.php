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
}

