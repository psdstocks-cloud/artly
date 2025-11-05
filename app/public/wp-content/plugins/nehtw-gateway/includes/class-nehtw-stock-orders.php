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

        if ( ! is_array( $results ) ) {
            return array();
        }

        return array_map( array( __CLASS__, 'format_order_for_api' ), $results );
    }

    /**
     * Find the most recent order for a given user/site/stock combination.
     *
     * @param int    $user_id  User ID.
     * @param string $site     Stock provider site key.
     * @param string $stock_id Remote stock identifier.
     *
     * @return array|null Matching order array or null when not found/invalid.
     */
    public static function find_existing_user_order( $user_id, $site, $stock_id ) {
        global $wpdb;

        $table = nehtw_gateway_get_table_name( 'stock_orders' );
        if ( ! $table ) {
            return null;
        }

        $user_id  = (int) $user_id;
        $site     = sanitize_key( $site );
        $stock_id = sanitize_text_field( $stock_id );

        if ( $user_id <= 0 || '' === $site || '' === $stock_id ) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT *
             FROM {$table}
             WHERE user_id = %d
               AND site = %s
               AND stock_id = %s
             ORDER BY id DESC
             LIMIT 1",
            $user_id,
            $site,
            $stock_id
        );

        $row = $wpdb->get_row( $sql, ARRAY_A );

        return $row ?: null;
    }

    /**
     * Normalize any mixed value into an array.
     *
     * @param mixed $value Value to normalize.
     *
     * @return array Normalized array.
     */
    protected static function normalize_to_array( $value ) {
        if ( is_array( $value ) ) {
            return $value;
        }

        if ( is_object( $value ) ) {
            $encoded = wp_json_encode( $value );
            if ( false !== $encoded ) {
                $decoded = json_decode( $encoded, true );
                if ( is_array( $decoded ) ) {
                    return $decoded;
                }
            }
        }

        if ( is_string( $value ) ) {
            $decoded = json_decode( $value, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                return $decoded;
            }
        }

        return array();
    }

    /**
     * Parse the raw_response column into an array.
     *
     * @param mixed $raw Raw response value from the database.
     *
     * @return array Parsed array.
     */
    protected static function parse_raw_response_value( $raw ) {
        if ( is_string( $raw ) ) {
            $maybe_unserialized = maybe_unserialize( $raw );
            if ( $maybe_unserialized !== $raw || 'b:0;' === $raw ) {
                $raw = $maybe_unserialized;
            }
        }

        return self::normalize_to_array( $raw );
    }

    /**
     * Get parsed raw response data for an order array.
     *
     * @param array $order Order array.
     *
     * @return array Parsed raw data.
     */
    public static function get_order_raw_data( $order ) {
        if ( ! is_array( $order ) ) {
            return array();
        }

        if ( array_key_exists( 'raw_response', $order ) ) {
            return self::parse_raw_response_value( $order['raw_response'] );
        }

        return array();
    }

    /**
     * Recursively search for the first non-empty value that matches one of the provided keys.
     *
     * @param array $data Data to search.
     * @param array $keys Keys to look for.
     *
     * @return mixed|null Found value or null.
     */
    protected static function recursive_find_in_array( $data, $keys ) {
        if ( ! is_array( $data ) ) {
            return null;
        }

        foreach ( $keys as $key ) {
            if ( isset( $data[ $key ] ) && '' !== $data[ $key ] && null !== $data[ $key ] ) {
                return $data[ $key ];
            }
        }

        foreach ( $data as $value ) {
            if ( is_array( $value ) ) {
                $found = self::recursive_find_in_array( $value, $keys );
                if ( null !== $found ) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Extract a preview thumbnail URL from raw data.
     *
     * @param array $raw Raw data.
     *
     * @return string|null Preview URL or null.
     */
    protected static function extract_preview_thumb_from_raw( $raw ) {
        $value = self::recursive_find_in_array(
            $raw,
            array( 'preview_thumb', 'preview', 'preview_url', 'thumbnail', 'thumb' )
        );

        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $sanitized = esc_url_raw( $value );
            return '' !== $sanitized ? $sanitized : null;
        }

        return null;
    }

    /**
     * Normalize a timestamp-like value into a Unix timestamp.
     *
     * @param mixed $value Value to normalize.
     *
     * @return int|null Timestamp or null if unknown.
     */
    protected static function normalize_timestamp_value( $value ) {
        if ( is_numeric( $value ) ) {
            $int_value = (int) $value;
            if ( $int_value > 9999999999 ) {
                $int_value = (int) round( $int_value / 1000 );
            }

            return $int_value > 0 ? $int_value : null;
        }

        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $timestamp = strtotime( $value );
            if ( false !== $timestamp ) {
                return $timestamp;
            }
        }

        return null;
    }

    /**
     * Extract download link expiration timestamp from raw data.
     *
     * @param array $raw Raw data.
     *
     * @return int|null Expiration timestamp or null.
     */
    protected static function extract_download_expiration_from_raw( $raw ) {
        $value = self::recursive_find_in_array(
            $raw,
            array(
                'download_link_expires_at',
                'download_url_expires_at',
                'download_expires_at',
                'valid_until',
                'expires_at',
                'expiry',
                'expires'
            )
        );

        if ( null === $value ) {
            return null;
        }

        return self::normalize_timestamp_value( $value );
    }

    /**
     * Extract the first scalar string that matches the provided keys.
     *
     * @param mixed $data Data to inspect.
     * @param array $keys Keys to look for.
     *
     * @return string Empty string when nothing matches.
     */
    public static function extract_string_from_array( $data, $keys ) {
        $array = self::normalize_to_array( $data );
        $value = self::recursive_find_in_array( $array, $keys );

        if ( null === $value ) {
            return '';
        }

        if ( is_scalar( $value ) ) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Extract download link from data set.
     *
     * @param mixed $data Data to inspect.
     *
     * @return string Sanitized download link or empty string.
     */
    public static function extract_download_link_from_array( $data ) {
        $link = self::extract_string_from_array( $data, array( 'download_link', 'download_url', 'url', 'link' ) );

        if ( '' === $link ) {
            return '';
        }

        $sanitized = esc_url_raw( $link );
        return '' !== $sanitized ? $sanitized : '';
    }

    /**
     * Determine whether an order currently has a valid download link.
     *
     * @param array      $order    Order array.
     * @param array|null $raw_data Optional raw data to reuse.
     *
     * @return bool True when the download link exists and is not expired.
     */
    public static function order_download_is_valid( $order, $raw_data = null ) {
        if ( null === $raw_data ) {
            $raw_data = self::get_order_raw_data( $order );
        } else {
            $raw_data = self::normalize_to_array( $raw_data );
        }

        $link = '';
        if ( isset( $order['download_link'] ) && '' !== trim( (string) $order['download_link'] ) ) {
            $link = trim( (string) $order['download_link'] );
        } else {
            $candidate = self::extract_download_link_from_array( $raw_data );
            if ( '' !== $candidate ) {
                $link = $candidate;
            }
        }

        if ( '' === $link ) {
            return false;
        }

        $expires_at = self::extract_download_expiration_from_raw( $raw_data );
        if ( null === $expires_at ) {
            return true;
        }

        return $expires_at > current_time( 'timestamp' );
    }

    /**
     * Determine whether an order can be redownloaded without additional cost.
     *
     * @param array $order    Order array.
     * @param array $raw_data Parsed raw data.
     *
     * @return bool
     */
    protected static function determine_can_redownload( $order, $raw_data ) {
        $status = isset( $order['status'] ) ? strtolower( (string) $order['status'] ) : '';

        $failed_statuses = array( 'failed', 'cancelled', 'canceled', 'refunded', 'refused' );
        if ( in_array( $status, $failed_statuses, true ) ) {
            return false;
        }

        if ( self::order_download_is_valid( $order, $raw_data ) ) {
            return true;
        }

        $successful_statuses = array( 'completed', 'complete', 'ready', 'delivered', 'finished', 'success', 'succeeded', 'done' );
        if ( in_array( $status, $successful_statuses, true ) ) {
            return true;
        }

        return false;
    }

    /**
     * Merge an existing raw_response payload with additional download data.
     *
     * @param mixed $current_raw    Existing raw response.
     * @param mixed $download_data  Fresh download data.
     *
     * @return array Combined raw response data.
     */
    public static function merge_raw_response_with_download( $current_raw, $download_data ) {
        $raw_array       = self::parse_raw_response_value( $current_raw );
        $download_array  = self::normalize_to_array( $download_data );

        if ( ! empty( $download_array ) ) {
            $raw_array['last_download'] = $download_array;
        }

        return $raw_array;
    }

    /**
     * Format an order for REST API responses with normalized fields.
     *
     * @param array $order Order array.
     *
     * @return array Formatted order.
     */
    public static function format_order_for_api( $order ) {
        if ( ! is_array( $order ) ) {
            return array();
        }

        $raw_data = self::get_order_raw_data( $order );

        if ( isset( $order['download_link'] ) ) {
            $sanitized_link        = esc_url_raw( $order['download_link'] );
            $order['download_link'] = '' !== $sanitized_link ? $sanitized_link : null;
        } else {
            $order['download_link'] = null;
        }

        $preview_thumb            = self::extract_preview_thumb_from_raw( $raw_data );
        $order['preview_thumb']   = $preview_thumb ? $preview_thumb : null;
        $order['cost_points']     = isset( $order['cost_points'] ) ? floatval( $order['cost_points'] ) : 0.0;
        $order['stock_id']        = array_key_exists( 'stock_id', $order ) && null !== $order['stock_id'] ? (string) $order['stock_id'] : null;
        $order['site']            = isset( $order['site'] ) ? (string) $order['site'] : '';
        $order['status']          = isset( $order['status'] ) ? (string) $order['status'] : '';
        $order['created_at']      = isset( $order['created_at'] ) ? (string) $order['created_at'] : '';
        $order['can_redownload_free'] = self::determine_can_redownload( $order, $raw_data );

        return $order;
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