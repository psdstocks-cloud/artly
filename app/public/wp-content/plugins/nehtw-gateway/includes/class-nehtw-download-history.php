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

if ( ! function_exists( 'nehtw_gateway_history_validate_image_url' ) ) {
    /**
     * Validate and sanitize a possible image URL.
     *
     * @param mixed $url Raw value.
     *
     * @return string
     */
    function nehtw_gateway_history_validate_image_url( $url ) {
        $url = esc_url_raw( (string) $url );

        if ( '' === $url ) {
            return '';
        }

        if ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $url ) ) {
            return '';
        }

        $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
        if ( $scheme && ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
            return '';
        }

        return $url;
    }
}

if ( ! function_exists( 'nehtw_gateway_history_search_thumbnail_candidate' ) ) {
    /**
     * Recursively search a payload for a thumbnail candidate.
     *
     * @param mixed $payload Payload to search.
     * @param array $keys     Candidate keys.
     *
     * @return string
     */
    function nehtw_gateway_history_search_thumbnail_candidate( $payload, $keys = array() ) {
        if ( empty( $payload ) ) {
            return '';
        }

        if ( empty( $keys ) ) {
            $keys = array( 'thumb_sm', 'thumbnail', 'thumb', 'preview', 'preview_url', 'image_thumb', 'image', 'small', 'url', 'src' );
        }

        if ( is_object( $payload ) ) {
            $payload = nehtw_gateway_history_decode_maybe_json( $payload );
        }

        if ( is_string( $payload ) ) {
            $candidate = nehtw_gateway_history_validate_image_url( $payload );
            return $candidate;
        }

        if ( ! is_array( $payload ) ) {
            return '';
        }

        foreach ( $keys as $key ) {
            if ( isset( $payload[ $key ] ) ) {
                $candidate = nehtw_gateway_history_validate_image_url( $payload[ $key ] );
                if ( '' !== $candidate ) {
                    return $candidate;
                }
            }
        }

        foreach ( $payload as $value ) {
            if ( is_array( $value ) || is_object( $value ) || is_string( $value ) ) {
                $candidate = nehtw_gateway_history_search_thumbnail_candidate( $value, $keys );
                if ( '' !== $candidate ) {
                    return $candidate;
                }
            }
        }

        return '';
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

        if ( isset( $files[0] ) ) {
            $candidate = nehtw_gateway_history_search_thumbnail_candidate( $files[0] );
            if ( '' !== $candidate ) {
                return $candidate;
            }
        }

        if ( is_array( $files ) ) {
            $candidate = nehtw_gateway_history_search_thumbnail_candidate( $files );
            if ( '' !== $candidate ) {
                return $candidate;
            }
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
        if ( isset( $row['preview_thumb'] ) && $row['preview_thumb'] ) {
            $thumbnail = nehtw_gateway_history_validate_image_url( $row['preview_thumb'] );
        }

        if ( '' === $thumbnail && isset( $order['preview_thumb'] ) && $order['preview_thumb'] ) {
            $thumbnail = nehtw_gateway_history_validate_image_url( $order['preview_thumb'] );
        }

        if ( '' === $thumbnail && isset( $row['raw_response'] ) ) {
            $thumbnail = nehtw_gateway_history_search_thumbnail_candidate( $row['raw_response'] );
        }

        if ( '' === $thumbnail && isset( $order['raw_response'] ) ) {
            $thumbnail = nehtw_gateway_history_search_thumbnail_candidate( $order['raw_response'] );
        }

        if ( '' === $thumbnail && isset( $order['files'] ) ) {
            $thumbnail = nehtw_gateway_history_search_thumbnail_candidate( $order['files'] );
        }
        
        $provider_label = '';
        if ( isset( $row['provider_label'] ) && $row['provider_label'] ) {
            $provider_label = sanitize_text_field( (string) $row['provider_label'] );
        } elseif ( isset( $order['site'] ) ) {
            // Fallback: try to get label from config
            if ( function_exists( 'nehtw_gateway_get_stock_sites_config' ) ) {
                $sites_config = nehtw_gateway_get_stock_sites_config();
                if ( isset( $sites_config[ $order['site'] ]['label'] ) ) {
                    $provider_label = sanitize_text_field( $sites_config[ $order['site'] ]['label'] );
                }
            }
            // If still empty, use site key formatted
            if ( empty( $provider_label ) ) {
                $provider_label = ucwords( str_replace( array( '-', '_' ), ' ', (string) $order['site'] ) );
            }
        }
        
        $stock_url = '';
        if ( isset( $row['source_url'] ) && $row['source_url'] ) {
            $stock_url = esc_url_raw( (string) $row['source_url'] );
        } elseif ( isset( $order['source_url'] ) && $order['source_url'] ) {
            $stock_url = esc_url_raw( (string) $order['source_url'] );
        }
        
        $updated_at = 0;
        if ( isset( $row['updated_at'] ) ) {
            $updated_at = nehtw_gateway_history_parse_datetime( $row['updated_at'] );
        }
        if ( $updated_at === 0 && $created_at > 0 ) {
            $updated_at = $created_at;
        }

        return array(
            'kind'          => 'stock',
            'id'            => (string) ( $row['task_id'] ?? '' ),
            'history_id'    => isset( $row['db_id'] ) ? (int) $row['db_id'] : 0,
            'title'         => $title,
            'site'          => isset( $order['site'] ) ? (string) $order['site'] : '',
            'provider_label' => $provider_label,
            'remote_id'     => isset( $row['stock_id'] ) ? (string) $row['stock_id'] : ( isset( $order['stock_id'] ) ? (string) $order['stock_id'] : '' ),
            'stock_url'     => $stock_url,
            'thumbnail'     => $thumbnail,
            'status'        => nehtw_gateway_history_format_status( $status ),
            'points'        => isset( $order['cost_points'] ) ? floatval( $order['cost_points'] ) : 0.0,
            'created_at'    => $created_at,
            'updated_at'    => $updated_at,
            'task_id'       => isset( $row['task_id'] ) ? (string) $row['task_id'] : '',
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
     * For stock orders, deduplicates by user_id + site + stock_id (shows only most recent per unique file).
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
            // Deduplicate stock orders: get only the most recent per user+site+stock_id
            $selects[] = $wpdb->prepare(
                "SELECT 'stock' AS kind, s1.id AS db_id, s1.task_id, s1.site, s1.provider_label, s1.file_name, s1.stock_id, s1.source_url, s1.preview_thumb, s1.status, s1.cost_points, s1.created_at, s1.updated_at, s1.raw_response, s1.download_link, NULL AS prompt, NULL AS files 
                FROM {$table_stock} s1
                INNER JOIN (
                    SELECT user_id, site, stock_id, MAX(updated_at) AS max_updated
                    FROM {$table_stock}
                    WHERE user_id = %d AND stock_id IS NOT NULL
                    GROUP BY user_id, site, stock_id
                ) s2 ON s1.user_id = s2.user_id AND s1.site = s2.site AND s1.stock_id = s2.stock_id AND s1.updated_at = s2.max_updated
                WHERE s1.user_id = %d
                UNION ALL
                SELECT 'stock' AS kind, id AS db_id, task_id, site, provider_label, file_name, stock_id, source_url, preview_thumb, status, cost_points, created_at, updated_at, raw_response, download_link, NULL AS prompt, NULL AS files 
                FROM {$table_stock} 
                WHERE user_id = %d AND stock_id IS NULL",
                $user_id,
                $user_id,
                $user_id
            );
        }

        if ( ( 'all' === $type || 'ai' === $type ) && $table_ai ) {
            $selects[] = $wpdb->prepare(
                "SELECT 'ai' AS kind, id AS db_id, job_id, NULL AS site, NULL AS provider_label, NULL AS file_name, NULL AS stock_id, NULL AS source_url, NULL AS preview_thumb, status, cost_points, created_at, updated_at, NULL AS raw_response, NULL AS download_link, prompt, files FROM {$table_ai} WHERE user_id = %d",
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
        $order_clause = $wpdb->prepare( 'ORDER BY updated_at DESC, created_at DESC, db_id DESC LIMIT %d OFFSET %d', $per_page, $offset );
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

        // Count deduplicated stock orders
        $count_stock = 0;
        $count_ai    = 0;

        if ( $table_stock && ( 'all' === $type || 'stock' === $type ) ) {
            // Count unique stock orders (deduplicated)
            $count_stock = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(DISTINCT CONCAT(user_id, '-', site, '-', COALESCE(stock_id, ''))) 
                    FROM {$table_stock} 
                    WHERE user_id = %d",
                    $user_id
                )
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
