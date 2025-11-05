<?php
/**
 * Unified download history helpers for Nehtw Gateway.
 *
 * @package Nehtw_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'nehtw_gateway_history_sanitize_type' ) ) {
    /**
     * Sanitize the requested history type.
     *
     * @param string $type Raw type.
     *
     * @return string
     */
    function nehtw_gateway_history_sanitize_type( $type ) {
        $type = is_string( $type ) ? strtolower( $type ) : 'all';
        $allowed = array( 'all', 'stock', 'ai' );
        return in_array( $type, $allowed, true ) ? $type : 'all';
    }
}

if ( ! function_exists( 'nehtw_gateway_history_parse_datetime' ) ) {
    /**
     * Convert a MySQL datetime or timestamp-like value to a Unix timestamp.
     *
     * @param mixed $value Raw value.
     *
     * @return int Timestamp (0 when unknown).
     */
    function nehtw_gateway_history_parse_datetime( $value ) {
        if ( $value instanceof DateTimeInterface ) {
            return $value->getTimestamp();
        }

        if ( is_numeric( $value ) ) {
            $int = (int) $value;
            if ( $int > 9999999999 ) {
                $int = (int) round( $int / 1000 );
            }
            return max( 0, $int );
        }

        if ( is_string( $value ) && '' !== trim( $value ) ) {
            $timestamp = strtotime( $value );
            if ( false !== $timestamp ) {
                return $timestamp;
            }
        }

        return 0;
    }
}

if ( ! function_exists( 'nehtw_gateway_history_trim_text' ) ) {
    /**
     * Trim text for titles/prompts.
     *
     * @param string $text Raw text.
     * @param int    $length Number of words.
     *
     * @return string
     */
    function nehtw_gateway_history_trim_text( $text, $length = 12 ) {
        $text = wp_strip_all_tags( (string) $text );
        $text = trim( $text );
        if ( '' === $text ) {
            return '';
        }
        return wp_trim_words( $text, $length, '…' );
    }
}

if ( ! function_exists( 'nehtw_gateway_history_format_status' ) ) {
    /**
     * Convert internal status codes into human readable labels.
     *
     * @param string $status Raw status.
     *
     * @return string
     */
    function nehtw_gateway_history_format_status( $status ) {
        $status = strtolower( (string) $status );

        if ( '' === $status ) {
            return __( 'Processing', 'nehtw-gateway' );
        }

        $map = array(
            'completed'  => __( 'Ready', 'nehtw-gateway' ),
            'complete'   => __( 'Ready', 'nehtw-gateway' ),
            'ready'      => __( 'Ready', 'nehtw-gateway' ),
            'finished'   => __( 'Ready', 'nehtw-gateway' ),
            'success'    => __( 'Ready', 'nehtw-gateway' ),
            'succeeded'  => __( 'Ready', 'nehtw-gateway' ),
            'processing' => __( 'Processing', 'nehtw-gateway' ),
            'pending'    => __( 'Processing', 'nehtw-gateway' ),
            'queued'     => __( 'Processing', 'nehtw-gateway' ),
            'running'    => __( 'Processing', 'nehtw-gateway' ),
            'failed'     => __( 'Failed', 'nehtw-gateway' ),
            'error'      => __( 'Failed', 'nehtw-gateway' ),
            'cancelled'  => __( 'Failed', 'nehtw-gateway' ),
            'canceled'   => __( 'Failed', 'nehtw-gateway' ),
        );

        return isset( $map[ $status ] ) ? $map[ $status ] : ucwords( $status );
    }
}

if ( ! function_exists( 'nehtw_gateway_history_decode_maybe_json' ) ) {
    /**
     * Decode JSON or maybe serialized payloads into arrays.
     *
     * @param mixed $value Raw value.
     *
     * @return array
     */
    function nehtw_gateway_history_decode_maybe_json( $value ) {
        if ( is_array( $value ) ) {
            return $value;
        }

        if ( is_string( $value ) ) {
            $maybe_unserialized = maybe_unserialize( $value );
            if ( $maybe_unserialized !== $value || 'b:0;' === $value ) {
                $value = $maybe_unserialized;
                if ( is_array( $value ) ) {
                    return $value;
                }
            }

            $decoded = json_decode( $value, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                return $decoded;
            }
        }

        if ( is_object( $value ) ) {
            $encoded = wp_json_encode( $value );
            if ( false !== $encoded ) {
                $decoded = json_decode( $encoded, true );
                if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                    return $decoded;
                }
            }
        }

        return array();
    }
}

if ( ! function_exists( 'nehtw_gateway_history_extract_ai_thumbnail' ) ) {
    /**
     * Extract a thumbnail URL from AI job files payload.
     *
     * @param array $files Files payload.
     *
     * @return string
     */
    function nehtw_gateway_history_extract_ai_thumbnail( $files ) {
        $files = nehtw_gateway_history_decode_maybe_json( $files );
        if ( isset( $files[0] ) && is_array( $files[0] ) ) {
            $file = $files[0];
            $candidates = array( 'thumb_sm', 'thumbnail', 'thumb', 'preview', 'preview_url' );
            foreach ( $candidates as $key ) {
                if ( ! empty( $file[ $key ] ) ) {
                    $url = esc_url_raw( (string) $file[ $key ] );
                    if ( '' !== $url ) {
                        return $url;
                    }
                }
            }
        }

        if ( isset( $files['files'] ) ) {
            return nehtw_gateway_history_extract_ai_thumbnail( $files['files'] );
        }

        return '';
    }
}

if ( ! function_exists( 'nehtw_gateway_history_extract_ai_download_link' ) ) {
    /**
     * Extract download link from AI job files payload.
     *
     * @param array $files Files payload.
     *
     * @return string
     */
    function nehtw_gateway_history_extract_ai_download_link( $files ) {
        $files = nehtw_gateway_history_decode_maybe_json( $files );
        if ( isset( $files[0] ) && is_array( $files[0] ) ) {
            $file = $files[0];
            $candidates = array( 'download', 'url', 'link' );
            foreach ( $candidates as $key ) {
                if ( ! empty( $file[ $key ] ) ) {
                    $url = esc_url_raw( (string) $file[ $key ] );
                    if ( '' !== $url ) {
                        return $url;
                    }
                }
            }
        }

        if ( isset( $files['files'] ) ) {
            return nehtw_gateway_history_extract_ai_download_link( $files['files'] );
        }

        return '';
    }
}

if ( ! function_exists( 'nehtw_gateway_history_format_stock_item' ) ) {
    /**
     * Normalize a stock order row into the unified history structure.
     *
     * @param array $row Stock order row.
     *
     * @return array
     */
    function nehtw_gateway_history_format_stock_item( $row ) {
        $order = $row;
        if ( class_exists( 'Nehtw_Gateway_Stock_Orders' ) ) {
            $order = Nehtw_Gateway_Stock_Orders::format_order_for_api( $row );
        }

        $title = '';
        if ( ! empty( $order['file_name'] ) ) {
            $title = (string) $order['file_name'];
        } elseif ( ! empty( $order['stock_id'] ) ) {
            $title = (string) $order['stock_id'];
        }
        if ( '' === $title ) {
            $title = __( 'Stock download', 'nehtw-gateway' );
        }

        $created_at = 0;
        if ( isset( $row['created_at'] ) ) {
            $created_at = nehtw_gateway_history_parse_datetime( $row['created_at'] );
        }

        $status = isset( $order['status'] ) ? $order['status'] : '';

        $thumbnail = '';
        if ( isset( $order['preview_thumb'] ) && $order['preview_thumb'] ) {
            $thumbnail = esc_url_raw( (string) $order['preview_thumb'] );
        }

        return array(
            'kind'       => 'stock',
            'id'         => (string) ( $row['task_id'] ?? '' ),
            'title'      => $title,
            'site'       => isset( $order['site'] ) ? (string) $order['site'] : '',
            'thumbnail'  => $thumbnail,
            'status'     => nehtw_gateway_history_format_status( $status ),
            'points'     => isset( $order['cost_points'] ) ? floatval( $order['cost_points'] ) : 0.0,
            'created_at' => $created_at,
            'task_id'    => isset( $row['task_id'] ) ? (string) $row['task_id'] : '',
            'download_link' => isset( $order['download_link'] ) ? (string) $order['download_link'] : '',
        );
    }
}

if ( ! function_exists( 'nehtw_gateway_history_format_ai_item' ) ) {
    /**
     * Normalize an AI job row into the unified history structure.
     *
     * @param array $row AI job row.
     *
     * @return array
     */
    function nehtw_gateway_history_format_ai_item( $row ) {
        $created_at = 0;
        if ( isset( $row['created_at'] ) ) {
            $created_at = nehtw_gateway_history_parse_datetime( $row['created_at'] );
        }

        $prompt = isset( $row['prompt'] ) ? $row['prompt'] : '';
        $title  = nehtw_gateway_history_trim_text( $prompt, 18 );
        if ( '' === $title ) {
            $title = __( 'AI generation', 'nehtw-gateway' );
        }

        $status = isset( $row['status'] ) ? $row['status'] : '';
        $files  = isset( $row['files'] ) ? $row['files'] : array();

        return array(
            'kind'       => 'ai',
            'id'         => isset( $row['job_id'] ) ? (string) $row['job_id'] : '',
            'title'      => $title,
            'site'       => 'ai',
            'thumbnail'  => nehtw_gateway_history_extract_ai_thumbnail( $files ),
            'status'     => nehtw_gateway_history_format_status( $status ),
            'points'     => isset( $row['cost_points'] ) ? floatval( $row['cost_points'] ) : 0.0,
            'created_at' => $created_at,
            'job_id'     => isset( $row['job_id'] ) ? (string) $row['job_id'] : '',
        );
    }
}

if ( ! function_exists( 'nehtw_gateway_get_user_download_history' ) ) {
    /**
     * Fetch paginated unified download history for a user.
     *
     * @param int    $user_id  User ID.
     * @param int    $page     Page number (1-based).
     * @param int    $per_page Items per page.
     * @param string $type     Filter type: all|stock|ai.
     *
     * @return array
     */
    function nehtw_gateway_get_user_download_history( $user_id, $page = 1, $per_page = 20, $type = 'all' ) {
        global $wpdb;

        $user_id  = intval( $user_id );
        $page     = max( 1, intval( $page ) );
        $per_page = max( 1, intval( $per_page ) );
        $type     = nehtw_gateway_history_sanitize_type( $type );

        if ( $user_id <= 0 ) {
            return array(
                'items'       => array(),
                'total'       => 0,
                'total_pages' => 0,
            );
        }

        $table_stock = nehtw_gateway_get_table_name( 'stock_orders' );
        $table_ai    = nehtw_gateway_get_table_name( 'ai_jobs' );

        $selects = array();

        if ( ( 'all' === $type || 'stock' === $type ) && $table_stock ) {
            $selects[] = $wpdb->prepare(
                "SELECT 'stock' AS kind, id AS db_id, task_id, site, file_name, stock_id, status, cost_points, created_at, raw_response, download_link, NULL AS prompt, NULL AS files FROM {$table_stock} WHERE user_id = %d",
                $user_id
            );
        }

        if ( ( 'all' === $type || 'ai' === $type ) && $table_ai ) {
            $selects[] = $wpdb->prepare(
                "SELECT 'ai' AS kind, id AS db_id, job_id, NULL AS site, NULL AS file_name, NULL AS stock_id, status, cost_points, created_at, NULL AS raw_response, NULL AS download_link, prompt, files FROM {$table_ai} WHERE user_id = %d",
                $user_id
            );
        }

        if ( empty( $selects ) ) {
            return array(
                'items'       => array(),
                'total'       => 0,
                'total_pages' => 0,
            );
        }

        $union = implode( ' UNION ALL ', $selects );

        $offset = ( $page - 1 ) * $per_page;
        $order_clause = $wpdb->prepare( 'ORDER BY created_at DESC, db_id DESC LIMIT %d OFFSET %d', $per_page, $offset );
        $sql          = 'SELECT * FROM ( ' . $union . ' ) AS history ' . $order_clause;
        $rows         = $wpdb->get_results( $sql, ARRAY_A );

        $items = array();
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                if ( 'stock' === $row['kind'] ) {
                    $items[] = nehtw_gateway_history_format_stock_item( $row );
                } elseif ( 'ai' === $row['kind'] ) {
                    $items[] = nehtw_gateway_history_format_ai_item( $row );
                }
            }
        }

        $count_stock = 0;
        $count_ai    = 0;

        if ( $table_stock && ( 'all' === $type || 'stock' === $type ) ) {
            $count_stock = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table_stock} WHERE user_id = %d", $user_id )
            );
        }

        if ( $table_ai && ( 'all' === $type || 'ai' === $type ) ) {
            $count_ai = (int) $wpdb->get_var(
                $wpdb->prepare( "SELECT COUNT(*) FROM {$table_ai} WHERE user_id = %d", $user_id )
            );
        }

        if ( 'stock' === $type ) {
            $total = $count_stock;
        } elseif ( 'ai' === $type ) {
            $total = $count_ai;
        } else {
            $total = $count_stock + $count_ai;
        }

        $total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 0;

        return array(
            'items'       => $items,
            'total'       => $total,
            'total_pages' => $total_pages,
        );
    }
}

if ( ! function_exists( 'nehtw_get_user_download_history' ) ) {
    /**
     * Wrapper for backwards compatibility – exposed helper function.
     *
     * @param int    $user_id  User ID.
     * @param int    $page     Page number.
     * @param int    $per_page Items per page.
     * @param string $type     Type filter.
     *
     * @return array
     */
    function nehtw_get_user_download_history( $user_id, $page = 1, $per_page = 20, $type = 'all' ) {
        return nehtw_gateway_get_user_download_history( $user_id, $page, $per_page, $type );
    }
}

if ( ! function_exists( 'nehtw_gateway_extract_ai_download_url' ) ) {
    /**
     * Helper for reuse by REST endpoints – returns AI download URL if present.
     *
     * @param mixed $files Files payload.
     *
     * @return string
     */
    function nehtw_gateway_extract_ai_download_url( $files ) {
        return nehtw_gateway_history_extract_ai_download_link( $files );
    }
}

if ( ! function_exists( 'nehtw_gateway_extract_ai_thumbnail_url' ) ) {
    /**
     * Helper for reuse – returns AI thumbnail URL.
     *
     * @param mixed $files Files payload.
     *
     * @return string
     */
    function nehtw_gateway_extract_ai_thumbnail_url( $files ) {
        return nehtw_gateway_history_extract_ai_thumbnail( $files );
    }
}
