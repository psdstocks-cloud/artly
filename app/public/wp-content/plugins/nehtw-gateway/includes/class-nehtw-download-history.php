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

if ( ! function_exists( 'nehtw_gateway_history_build_provider_fallback_url' ) ) {
    /**
     * Build a provider-specific fallback thumbnail URL based on site and stock ID.
     *
     * @param string $site    Provider site key (e.g. 'shutterstock', 'adobestock').
     * @param string $stock_id Stock ID from the provider.
     *
     * @return string Fallback URL or empty string if no pattern matches.
     */
    function nehtw_gateway_history_build_provider_fallback_url( $site, $stock_id ) {
        if ( empty( $site ) || empty( $stock_id ) ) {
            return '';
        }

        $site = strtolower( sanitize_key( $site ) );
        $stock_id = sanitize_text_field( $stock_id );

        // Provider-specific fallback URL patterns.
        $fallback_patterns = array(
            'shutterstock' => 'https://image.shutterstock.com/image-photo/id-' . $stock_id . '-260nw.jpg',
            'adobestock'   => 'https://as1.ftcdn.net/v2/jpg/' . $stock_id . '/500_F_' . $stock_id . '.jpg',
            'pngtree'      => 'https://pngtree.com/freepng/' . $stock_id . '.jpg',
            'storyblocks'  => 'https://media.storyblocks.com/stock-images/' . $stock_id . '.jpg',
            'getty'        => 'https://media.gettyimages.com/id/' . $stock_id . '/photo',
            'istock'       => 'https://media.istockphoto.com/id/' . $stock_id . '/photo',
            'depositphotos' => 'https://st.depositphotos.com/' . $stock_id . '/stock-photo',
            'pexels'       => 'https://images.pexels.com/photos/' . $stock_id,
            'pixabay'      => 'https://pixabay.com/get/' . $stock_id,
            'unsplash'     => 'https://images.unsplash.com/' . $stock_id,
        );

        if ( isset( $fallback_patterns[ $site ] ) ) {
            return $fallback_patterns[ $site ];
        }

        return '';
    }
}

if ( ! function_exists( 'nehtw_gateway_history_check_image_accessibility' ) ) {
    /**
     * Check if an image URL is accessible and valid.
     *
     * Uses cached transient results to avoid repeated HTTP requests.
     * Performs lightweight HEAD request to check accessibility.
     *
     * @param string $url Image URL to check.
     * @param bool   $force_check If true, bypass cache and force check.
     *
     * @return bool True if accessible, false otherwise.
     */
    function nehtw_gateway_history_check_image_accessibility( $url, $force_check = false ) {
        if ( empty( $url ) ) {
            return false;
        }

        // Validate URL format first.
        $url = nehtw_gateway_history_validate_image_url( $url );
        if ( '' === $url ) {
            return false;
        }

        // Check cache first (unless force_check is true).
        if ( ! $force_check ) {
            $cache_key = 'artly_thumb_check_' . md5( $url );
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return '1' === $cached;
            }
        }

        // Use a HEAD request to check if the image exists (lightweight check).
        $response = wp_remote_head( $url, array(
            'timeout'     => 3,
            'redirection' => 2,
        ) );

        $is_accessible = false;

        if ( is_wp_error( $response ) ) {
            $is_accessible = false;
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code >= 200 && $code < 300 ) {
                $content_type = wp_remote_retrieve_header( $response, 'content-type' );
                // Verify it's an image.
                if ( $content_type && strpos( strtolower( $content_type ), 'image/' ) === 0 ) {
                    $is_accessible = true;
                }
            }
        }

        // Cache result for 24 hours.
        if ( ! $force_check ) {
            $cache_key = 'artly_thumb_check_' . md5( $url );
            set_transient( $cache_key, $is_accessible ? '1' : '0', DAY_IN_SECONDS );
        }

        return $is_accessible;
    }
}

if ( ! function_exists( 'nehtw_gateway_history_get_cached_placeholder' ) ) {
    /**
     * Get or create a cached placeholder image for broken thumbnails.
     *
     * @return string URL to cached placeholder or empty string on failure.
     */
    function nehtw_gateway_history_get_cached_placeholder() {
        $upload_dir = wp_upload_dir();
        if ( $upload_dir['error'] ) {
            return '';
        }

        $cache_dir = $upload_dir['basedir'] . '/artly-cache';
        $cache_url = $upload_dir['baseurl'] . '/artly-cache';
        $placeholder_file = $cache_dir . '/placeholder.svg';

        // Check if placeholder already exists and is fresh (within 24 hours).
        if ( file_exists( $placeholder_file ) ) {
            $file_age = time() - filemtime( $placeholder_file );
            if ( $file_age < DAY_IN_SECONDS ) {
                return $cache_url . '/placeholder.svg';
            }
        }

        // Create cache directory if it doesn't exist.
        if ( ! file_exists( $cache_dir ) ) {
            wp_mkdir_p( $cache_dir );
        }

        // Generate placeholder SVG with Artly theme colors (dark theme primary, respects CSS overrides).
        $svg_content = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="300" height="200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 200">
  <rect width="300" height="200" fill="#0f172a" stroke="#06f5e8" stroke-width="2" stroke-dasharray="5,5"/>
  <circle cx="150" cy="80" r="25" fill="none" stroke="#06f5e8" stroke-width="2"/>
  <path d="M 120 120 L 150 100 L 180 120 L 180 150 L 120 150 Z" fill="none" stroke="#06f5e8" stroke-width="2"/>
  <text x="150" y="170" text-anchor="middle" fill="#06f5e8" font-family="Arial, sans-serif" font-size="12">Image Preview</text>
</svg>';

        // Write placeholder using WP_Filesystem if available, otherwise use file_put_contents.
        global $wp_filesystem;
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( $wp_filesystem ) {
            $wp_filesystem->put_contents( $placeholder_file, $svg_content, FS_CHMOD_FILE );
        } else {
            file_put_contents( $placeholder_file, $svg_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
        }

        if ( file_exists( $placeholder_file ) ) {
            return $cache_url . '/placeholder.svg';
        }

        return '';
    }
}

if ( ! function_exists( 'nehtw_gateway_history_proxy_image' ) ) {
    /**
     * Proxy and cache an image URL, returning cached version if original is broken.
     *
     * @param string $url Original image URL.
     *
     * @return string URL to accessible image (original, cached, or placeholder).
     */
    function nehtw_gateway_history_proxy_image( $url ) {
        if ( empty( $url ) ) {
            return nehtw_gateway_history_get_cached_placeholder();
        }

        // Validate URL format.
        $url = nehtw_gateway_history_validate_image_url( $url );
        if ( '' === $url ) {
            return nehtw_gateway_history_get_cached_placeholder();
        }

        // Check if image is accessible.
        $is_accessible = nehtw_gateway_history_check_image_accessibility( $url );
        if ( $is_accessible ) {
            return $url;
        }

        // Image is broken, return placeholder.
        return nehtw_gateway_history_get_cached_placeholder();
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

        // Try existing thumbnail sources first.
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

        // Get site and stock_id for fallback logic.
        $site = isset( $order['site'] ) ? (string) $order['site'] : ( isset( $row['site'] ) ? (string) $row['site'] : '' );
        $stock_id = isset( $row['stock_id'] ) ? (string) $row['stock_id'] : ( isset( $order['stock_id'] ) ? (string) $order['stock_id'] : '' );

        // If we have a thumbnail, validate it and try fallback if broken.
        if ( '' !== $thumbnail ) {
            // Skip accessibility check for our own cached placeholder (always accessible).
            $upload_dir = wp_upload_dir();
            $is_placeholder = false;
            if ( ! $upload_dir['error'] && strpos( $thumbnail, $upload_dir['baseurl'] . '/artly-cache/placeholder.svg' ) !== false ) {
                $is_placeholder = true;
            }

            $is_accessible = true;
            if ( ! $is_placeholder ) {
                // Check accessibility (function handles caching internally).
                $is_accessible = nehtw_gateway_history_check_image_accessibility( $thumbnail );
            }

            if ( ! $is_accessible ) {
                // Original thumbnail is broken, try provider fallback.
                if ( $site && $stock_id ) {
                    $fallback_url = nehtw_gateway_history_build_provider_fallback_url( $site, $stock_id );
                    if ( '' !== $fallback_url ) {
                        // Check fallback accessibility (function handles caching internally).
                        $fallback_accessible = nehtw_gateway_history_check_image_accessibility( $fallback_url );

                        if ( $fallback_accessible ) {
                            $thumbnail = $fallback_url;

                            // Log fallback usage if WP_DEBUG is enabled.
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                $user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : get_current_user_id();
                                error_log( '[artly-thumb-fallback] User ' . $user_id . ' - replaced broken thumb for ' . $site . ' ID ' . $stock_id . ' with fallback URL' );
                            }
                        } else {
                            // Fallback also broken, use placeholder.
                            $thumbnail = nehtw_gateway_history_get_cached_placeholder();

                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                $user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : get_current_user_id();
                                error_log( '[artly-thumb-fallback] User ' . $user_id . ' - replaced broken thumb for ' . $site . ' ID ' . $stock_id . ' with placeholder (fallback also broken)' );
                            }
                        }
                    } else {
                        // No fallback pattern available, use placeholder.
                        $thumbnail = nehtw_gateway_history_get_cached_placeholder();

                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            $user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : get_current_user_id();
                            error_log( '[artly-thumb-fallback] User ' . $user_id . ' - replaced broken thumb for ' . $site . ' ID ' . $stock_id . ' with placeholder (no fallback pattern)' );
                        }
                    }
                } else {
                    // No site/stock_id available, use placeholder.
                    $thumbnail = nehtw_gateway_history_get_cached_placeholder();
                }
            }
            // If thumbnail is accessible, keep it as-is (no need to proxy on every request).
        } else {
            // No thumbnail found, try provider fallback before using placeholder.
            if ( $site && $stock_id ) {
                $fallback_url = nehtw_gateway_history_build_provider_fallback_url( $site, $stock_id );
                if ( '' !== $fallback_url ) {
                    // Check fallback accessibility (function handles caching internally).
                    $fallback_accessible = nehtw_gateway_history_check_image_accessibility( $fallback_url );

                    if ( $fallback_accessible ) {
                        $thumbnail = $fallback_url;

                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            $user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : get_current_user_id();
                            error_log( '[artly-thumb-fallback] User ' . $user_id . ' - using provider fallback thumb for ' . $site . ' ID ' . $stock_id );
                        }
                    } else {
                        // Fallback not accessible, use placeholder.
                        $thumbnail = nehtw_gateway_history_get_cached_placeholder();

                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            $user_id = isset( $row['user_id'] ) ? (int) $row['user_id'] : get_current_user_id();
                            error_log( '[artly-thumb-fallback] User ' . $user_id . ' - using placeholder for ' . $site . ' ID ' . $stock_id . ' (fallback not accessible)' );
                        }
                    }
                } else {
                    // No fallback pattern, use placeholder.
                    $thumbnail = nehtw_gateway_history_get_cached_placeholder();
                }
            } else {
                // No site/stock_id, use placeholder.
                $thumbnail = nehtw_gateway_history_get_cached_placeholder();
            }
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

if ( ! function_exists( 'nehtw_gateway_history_refresh_stock_statuses' ) ) {
    /**
     * Refresh stock order statuses for a set of history rows.
     *
     * For non-final statuses, check Nehtw API once (with basic caching) and
     * update the local order + history row with the latest status.
     *
     * @param array $rows    Raw DB rows from stock history.
     * @param int   $user_id Current user ID.
     *
     * @return array Updated rows.
     */
    function nehtw_gateway_history_refresh_stock_statuses( $rows, $user_id ) {
        if ( empty( $rows ) || ! function_exists( 'nehtw_gateway_api_order_status' ) ) {
            return $rows;
        }

        // Define non-final statuses that should be refreshed.
        $non_final_statuses = array( 'queued', 'pending', 'processing', 'requesting' );

        // Limit how many orders we refresh per page load to avoid API spam.
        $max_checks = 20;
        $checks_done = 0;

        foreach ( $rows as $index => $row ) {
            // Safety checks.
            if ( $checks_done >= $max_checks ) {
                break;
            }

            if ( empty( $row['kind'] ) || 'stock' !== $row['kind'] ) {
                continue;
            }

            // Current status as stored in history.
            $status = isset( $row['status'] ) ? strtolower( trim( (string) $row['status'] ) ) : '';

            // Skip if status is empty, final, or not in our list of non-final statuses.
            if ( '' === $status || ! in_array( $status, $non_final_statuses, true ) ) {
                // Already final or unknown status, skip.
                continue;
            }

            // Task ID should be present for stock orders.
            $task_id = isset( $row['task_id'] ) ? $row['task_id'] : '';

            if ( ! $task_id ) {
                continue;
            }

            // Basic per-task caching to avoid calling API too frequently.
            $cache_key = 'nehtw_status_sync_' . md5( $user_id . '|' . $task_id );
            $recent    = get_transient( $cache_key );

            if ( $recent ) {
                // We refreshed recently, skip for now.
                continue;
            }

            // Mark as checked for the next 30 seconds.
            set_transient( $cache_key, 1, 30 );

            // Call Nehtw API for this order status.
            $api_response = nehtw_gateway_api_order_status( $task_id, 'any' );

            if ( is_wp_error( $api_response ) ) {
                // Do not fail the whole page on API errors; just skip.
                continue;
            }

            // Normalize the API response.
            if ( function_exists( 'nehtw_gateway_normalize_api_payload' ) ) {
                $api_data = nehtw_gateway_normalize_api_payload( $api_response );
            } else {
                $api_data = is_array( $api_response ) ? $api_response : array();
            }

            $remote_status = isset( $api_data['status'] ) ? $api_data['status'] : '';

            // Normalize the status.
            if ( function_exists( 'nehtw_gateway_normalize_remote_status' ) ) {
                $new_status = nehtw_gateway_normalize_remote_status( $remote_status );
            } else {
                $new_status = strtolower( (string) $remote_status );
            }

            // Skip if no valid new status or status hasn't changed.
            if ( '' === $new_status || $new_status === $status ) {
                // No change or empty status.
                continue;
            }

            // Update local stock order record, if the helper class exists.
            if ( class_exists( 'Nehtw_Gateway_Stock_Orders' ) ) {
                // Try to fetch the full order row by task_id to make sure it belongs to this user.
                $order = Nehtw_Gateway_Stock_Orders::get_by_task_id( $task_id );

                if ( $order && (int) $order['user_id'] === (int) $user_id ) {
                    // Merge raw data with last status check info.
                    $raw_data = isset( $order['raw_response'] ) ? maybe_unserialize( $order['raw_response'] ) : array();

                    if ( ! is_array( $raw_data ) ) {
                        $raw_data = array();
                    }

                    $status_payload = array(
                        'checked_at' => current_time( 'mysql' ),
                        'response'   => $api_data,
                    );

                    // Merge raw response with status data.
                    if ( method_exists( 'Nehtw_Gateway_Stock_Orders', 'merge_raw_response_with_status' ) ) {
                        $raw_merged = Nehtw_Gateway_Stock_Orders::merge_raw_response_with_status(
                            $raw_data,
                            $status_payload
                        );
                    } else {
                        $raw_merged = $raw_data;
                        if ( ! empty( $status_payload ) ) {
                            $raw_merged['last_status'] = $status_payload;
                        }
                    }

                    // Update the order status in the database.
                    Nehtw_Gateway_Stock_Orders::update_status(
                        $task_id,
                        $new_status,
                        array(
                            'raw_response' => maybe_serialize( $raw_merged ),
                        )
                    );
                }
            }

            // Reflect the new status back into the history row so the UI is correct in this request.
            $rows[ $index ]['status'] = $new_status;
            $checks_done++;
        }

        return $rows;
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
                    SELECT user_id, site, stock_id, MAX(id) AS max_id
                    FROM {$table_stock}
                    WHERE user_id = %d AND stock_id IS NOT NULL
                    GROUP BY user_id, site, stock_id
                ) s2 ON s1.user_id = s2.user_id AND s1.site = s2.site AND s1.stock_id = s2.stock_id AND s1.id = s2.max_id
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

        // Refresh stock order statuses for non-final statuses before formatting.
        if ( ! empty( $rows ) && function_exists( 'nehtw_gateway_history_refresh_stock_statuses' ) ) {
            $rows = nehtw_gateway_history_refresh_stock_statuses( $rows, $user_id );
        }

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