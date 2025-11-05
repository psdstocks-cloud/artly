<?php
/**
 * Nehtw Gateway Stock Orders Helper Class
 *
 * @package Nehtw_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper class for managing stock orders.
 */
class Nehtw_Gateway_Stock_Orders {

    /**
     * Get stock orders for a specific user.
     *
     * @param int $user_id User ID.
     * @param int $limit   Number of orders to retrieve.
     * @param int $offset  Offset for pagination.
     *
     * @return array Array of order records.
     */
    public static function get_user_orders( $user_id, $limit = 10, $offset = 0 ) {
        global $wpdb;
        
        $table   = $wpdb->prefix . 'nehtw_stock_orders';
        $user_id = intval( $user_id );
        $limit   = max( 1, intval( $limit ) );
        $offset  = max( 0, intval( $offset ) );

        if ( $user_id <= 0 ) {
            return array();
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE user_id = %d
             ORDER BY created_at DESC
             LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        );

        $results = $wpdb->get_results( $sql, ARRAY_A );

        return is_array( $results ) ? $results : array();
    }

    /**
     * Update the status of a stock order by task_id.
     *
     * @param string $task_id Task ID from Nehtw API.
     * @param string $status  New status.
     * @param array  $data    Additional data to update.
     *
     * @return bool True on success, false on failure.
     */
    public static function update_status( $task_id, $status, $data = array() ) {
        global $wpdb;
        
        $table   = $wpdb->prefix . 'nehtw_stock_orders';
        $task_id = sanitize_text_field( $task_id );
        $status  = sanitize_key( $status );

        if ( '' === $task_id ) {
            return false;
        }

        $update_data = array_merge(
            array(
                'status'     => $status,
                'updated_at' => current_time( 'mysql' ),
            ),
            $data
        );

        $result = $wpdb->update(
            $table,
            $update_data,
            array( 'task_id' => $task_id ),
            null,
            array( '%s' )
        );

        return false !== $result;
    }

    /**
     * Get a single order by task_id.
     *
     * @param string $task_id Task ID from Nehtw API.
     *
     * @return array|null Order data or null if not found.
     */
    public static function get_by_task_id( $task_id ) {
        global $wpdb;
        
        $table   = $wpdb->prefix . 'nehtw_stock_orders';
        $task_id = sanitize_text_field( $task_id );

        if ( '' === $task_id ) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE task_id = %s LIMIT 1",
            $task_id
        );

        $row = $wpdb->get_row( $sql, ARRAY_A );

        return $row ?: null;
    }
}