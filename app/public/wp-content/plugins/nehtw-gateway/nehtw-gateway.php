<?php
/**
 * Plugin Name: Nehtw Gateway
 * Description: Core integration with the Nehtw API for credits, stock downloads, and AI image generation.
 * Version:     0.1.0
 * Author:      Your Name
 * Text Domain: nehtw-gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NEHTW_GATEWAY_VERSION', '0.1.0' );
define( 'NEHTW_GATEWAY_PLUGIN_FILE', __FILE__ );
define( 'NEHTW_GATEWAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NEHTW_GATEWAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NEHTW_GATEWAY_API_BASE', 'https://nehtw.com' );
define( 'NEHTW_GATEWAY_API_KEY', 'A8K9bV5s2OX12E8cmS4I96mtmSNzv7' );

require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-stock-orders.php';

// WooCommerce integration for wallet top-ups (only load if WooCommerce is active)
if ( class_exists( 'WooCommerce' ) ) {
    require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-artly-woocommerce-points.php';
}

function nehtw_gateway_activate() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate   = $wpdb->get_charset_collate();
    $table_wallet      = $wpdb->prefix . 'nehtw_wallet_transactions';
    $table_stock       = $wpdb->prefix . 'nehtw_stock_orders';
    $table_ai          = $wpdb->prefix . 'nehtw_ai_jobs';
    $table_stock_sites = $wpdb->prefix . 'nehtw_stock_sites';

    $sql_wallet = "CREATE TABLE {$table_wallet} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        type VARCHAR(50) NOT NULL,
        points DECIMAL(10,2) NOT NULL DEFAULT 0,
        currency_amount DECIMAL(10,2) NULL DEFAULT NULL,
        currency_code VARCHAR(10) NULL DEFAULT NULL,
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY type (type),
        KEY created_at (created_at)
    ) {$charset_collate};";

    $sql_stock = "CREATE TABLE {$table_stock} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        site VARCHAR(50) NOT NULL,
        stock_id VARCHAR(191) NULL,
        source_url TEXT NULL,
        task_id VARCHAR(191) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        cost_points DECIMAL(10,2) NOT NULL DEFAULT 0,
        nehtw_cost DECIMAL(10,2) NULL DEFAULT NULL,
        download_link TEXT NULL,
        file_name VARCHAR(255) NULL,
        link_type VARCHAR(50) NULL,
        raw_response LONGTEXT NULL,
        created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY task_id (task_id),
        KEY site (site),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charset_collate};";

    $sql_ai = "CREATE TABLE {$table_ai} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        job_id VARCHAR(191) NOT NULL,
        parent_job_id VARCHAR(191) NULL,
        prompt TEXT NULL,
        type VARCHAR(50) NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        cost_points DECIMAL(10,2) NOT NULL DEFAULT 0,
        nehtw_cost DECIMAL(10,2) NULL DEFAULT NULL,
        percentage_complete INT(11) NULL DEFAULT 0,
        files LONGTEXT NULL,
        error_message TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        updated_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY job_id (job_id),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charset_collate};";

    $sql_stock_sites = "CREATE TABLE {$table_stock_sites} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        provider_key VARCHAR(100) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        nehtw_price DECIMAL(10,2) NULL DEFAULT NULL,
        your_price_points DECIMAL(10,2) NULL DEFAULT NULL,
        last_synced_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (id),
        UNIQUE KEY provider_key (provider_key),
        KEY active (active)
    ) {$charset_collate};";

    dbDelta( $sql_wallet );
    dbDelta( $sql_stock );
    dbDelta( $sql_ai );
    dbDelta( $sql_stock_sites );
}
register_activation_hook( NEHTW_GATEWAY_PLUGIN_FILE, 'nehtw_gateway_activate' );

function nehtw_gateway_get_table_name( $alias ) {
    global $wpdb;
    $map = array(
        'wallet_transactions' => $wpdb->prefix . 'nehtw_wallet_transactions',
        'stock_orders'        => $wpdb->prefix . 'nehtw_stock_orders',
        'ai_jobs'             => $wpdb->prefix . 'nehtw_ai_jobs',
        'stock_sites'         => $wpdb->prefix . 'nehtw_stock_sites',
    );
    return isset( $map[ $alias ] ) ? $map[ $alias ] : '';
}

function nehtw_gateway_add_transaction( $user_id, $type, $points, $args = array() ) {
    global $wpdb;
    $table = nehtw_gateway_get_table_name( 'wallet_transactions' );
    if ( ! $table ) return false;

    $user_id = intval( $user_id );
    if ( $user_id <= 0 ) return false;

    $type   = sanitize_key( $type );
    $points = floatval( $points );

    $currency_amount = null;
    $currency_code   = null;
    $meta_json       = null;

    if ( isset( $args['currency_amount'] ) ) {
        $currency_amount = floatval( $args['currency_amount'] );
    }
    if ( isset( $args['currency_code'] ) && '' !== $args['currency_code'] ) {
        $currency_code = strtoupper( sanitize_text_field( $args['currency_code'] ) );
    }
    if ( isset( $args['meta'] ) ) {
        $meta_json = wp_json_encode( $args['meta'] );
    }

    $created_at = current_time( 'mysql' );
    $inserted = $wpdb->insert(
        $table,
        array(
            'user_id'         => $user_id,
            'type'            => $type,
            'points'          => $points,
            'currency_amount' => $currency_amount,
            'currency_code'   => $currency_code,
            'meta'            => $meta_json,
            'created_at'      => $created_at,
        ),
        array( '%d', '%s', '%f', '%f', '%s', '%s', '%s' )
    );

    if ( ! $inserted ) return false;
    return $wpdb->insert_id;
}

function nehtw_gateway_get_balance( $user_id ) {
    global $wpdb;
    $table   = nehtw_gateway_get_table_name( 'wallet_transactions' );
    $user_id = intval( $user_id );
    if ( ! $table || $user_id <= 0 ) return 0.0;

    $sql = $wpdb->prepare(
        "SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d",
        $user_id
    );
    $sum = $wpdb->get_var( $sql );
    return floatval( $sum );
}

function nehtw_gateway_get_transactions( $user_id, $limit = 20, $offset = 0 ) {
    global $wpdb;
    $table   = nehtw_gateway_get_table_name( 'wallet_transactions' );
    $user_id = intval( $user_id );
    $limit   = max( 1, intval( $limit ) );
    $offset  = max( 0, intval( $offset ) );

    if ( ! $table || $user_id <= 0 ) return array();

    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
        $user_id, $limit, $offset
    );
    $rows = $wpdb->get_results( $sql, ARRAY_A );
    return is_array( $rows ) ? $rows : array();
}

function nehtw_gateway_create_stock_order( $user_id, $site, $stock_id, $source_url, $task_id, $cost_points, $nehtw_cost = null, $status = 'pending', $extra = array() ) {
    global $wpdb;
    $table = nehtw_gateway_get_table_name( 'stock_orders' );
    if ( ! $table ) return false;

    $user_id     = intval( $user_id );
    $site        = sanitize_key( $site );
    $stock_id    = ( null !== $stock_id ) ? sanitize_text_field( $stock_id ) : null;
    $source_url  = ( null !== $source_url ) ? esc_url_raw( $source_url ) : null;
    $task_id     = sanitize_text_field( $task_id );
    $status      = sanitize_key( $status );
    $cost_points = floatval( $cost_points );
    $nehtw_cost  = ( null !== $nehtw_cost ) ? floatval( $nehtw_cost ) : null;

    if ( $user_id <= 0 || '' === $site || '' === $task_id ) return false;

    $file_name     = isset( $extra['file_name'] ) ? sanitize_text_field( $extra['file_name'] ) : null;
    $link_type     = isset( $extra['link_type'] ) ? sanitize_text_field( $extra['link_type'] ) : null;
    $download_link = isset( $extra['download_link'] ) ? esc_url_raw( $extra['download_link'] ) : null;
    $raw_response  = isset( $extra['raw_response'] ) ? maybe_serialize( $extra['raw_response'] ) : null;

    $now = current_time( 'mysql' );
    $inserted = $wpdb->insert(
        $table,
        array(
            'user_id'       => $user_id,
            'site'          => $site,
            'stock_id'      => $stock_id,
            'source_url'    => $source_url,
            'task_id'       => $task_id,
            'status'        => $status,
            'cost_points'   => $cost_points,
            'nehtw_cost'    => $nehtw_cost,
            'download_link' => $download_link,
            'file_name'     => $file_name,
            'link_type'     => $link_type,
            'raw_response'  => $raw_response,
            'created_at'    => $now,
            'updated_at'    => $now,
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
    );

    if ( ! $inserted ) return false;
    return $wpdb->insert_id;
}

function nehtw_gateway_update_stock_order_status( $task_id, $status, $fields = array() ) {
    global $wpdb;
    $table   = nehtw_gateway_get_table_name( 'stock_orders' );
    $task_id = sanitize_text_field( $task_id );
    $status  = sanitize_key( $status );

    if ( ! $table || '' === $task_id ) return false;

    $data   = array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) );
    $format = array( '%s', '%s' );

    if ( isset( $fields['download_link'] ) ) {
        $data['download_link'] = esc_url_raw( $fields['download_link'] );
        $format[] = '%s';
    }
    if ( isset( $fields['file_name'] ) ) {
        $data['file_name'] = sanitize_text_field( $fields['file_name'] );
        $format[] = '%s';
    }
    if ( isset( $fields['link_type'] ) ) {
        $data['link_type'] = sanitize_text_field( $fields['link_type'] );
        $format[] = '%s';
    }
    if ( isset( $fields['nehtw_cost'] ) ) {
        $data['nehtw_cost'] = floatval( $fields['nehtw_cost'] );
        $format[] = '%f';
    }
    if ( isset( $fields['raw_response'] ) ) {
        $data['raw_response'] = maybe_serialize( $fields['raw_response'] );
        $format[] = '%s';
    }

    $updated = $wpdb->update( $table, $data, array( 'task_id' => $task_id ), $format, array( '%s' ) );
    return ( false !== $updated );
}

function nehtw_gateway_get_orders_for_user( $user_id, $limit = 20, $offset = 0 ) {
    global $wpdb;
    $table   = nehtw_gateway_get_table_name( 'stock_orders' );
    $user_id = intval( $user_id );
    $limit   = max( 1, intval( $limit ) );
    $offset  = max( 0, intval( $offset ) );

    if ( ! $table || $user_id <= 0 ) return array();

    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
        $user_id, $limit, $offset
    );
    $rows = $wpdb->get_results( $sql, ARRAY_A );
    return is_array( $rows ) ? $rows : array();
}

function nehtw_gateway_get_order_by_task_id( $task_id ) {
    global $wpdb;
    $table   = nehtw_gateway_get_table_name( 'stock_orders' );
    $task_id = sanitize_text_field( $task_id );

    if ( ! $table || '' === $task_id ) return null;

    $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE task_id = %s LIMIT 1", $task_id );
    $row = $wpdb->get_row( $sql, ARRAY_A );
    return $row ?: null;
}

function nehtw_gateway_get_api_key() {
    $key = defined( 'NEHTW_GATEWAY_API_KEY' ) ? NEHTW_GATEWAY_API_KEY : '';
    return trim( (string) $key );
}

function nehtw_gateway_build_api_headers() {
    $headers = array( 'Accept' => 'application/json' );
    $api_key = nehtw_gateway_get_api_key();
    if ( '' !== $api_key ) {
        $headers['X-Api-Key'] = $api_key;
    }
    return $headers;
}

function nehtw_gateway_api_get( $path, $query_args = array() ) {
    $base = rtrim( NEHTW_GATEWAY_API_BASE, '/' );
    $path = '/' . ltrim( $path, '/' );
    $url  = $base . $path;

    if ( ! empty( $query_args ) ) {
        $url = add_query_arg( $query_args, $url );
    }

    $response = wp_remote_get( $url, array(
        'headers' => nehtw_gateway_build_api_headers(),
        'timeout' => 20,
    ) );

    if ( is_wp_error( $response ) ) return $response;

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( null === $data ) {
        return new WP_Error( 'nehtw_bad_json', sprintf( 'Nehtw API returned invalid JSON (HTTP %d).', $code ) );
    }
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'nehtw_http_error', sprintf( 'Nehtw API error (HTTP %d).', $code ), $data );
    }
    return $data;
}

function nehtw_gateway_api_post_json( $path, $body = array(), $query_args = array() ) {
    $base = rtrim( NEHTW_GATEWAY_API_BASE, '/' );
    $path = '/' . ltrim( $path, '/' );
    $url  = $base . $path;

    if ( ! empty( $query_args ) ) {
        $url = add_query_arg( $query_args, $url );
    }

    $headers = nehtw_gateway_build_api_headers();
    $headers['Content-Type'] = 'application/json';

    $response = wp_remote_post( $url, array(
        'headers' => $headers,
        'timeout' => 30,
        'body'    => wp_json_encode( $body ),
    ) );

    if ( is_wp_error( $response ) ) return $response;

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( null === $data ) {
        return new WP_Error( 'nehtw_bad_json', sprintf( 'Nehtw API returned invalid JSON (HTTP %d).', $code ) );
    }
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'nehtw_http_error', sprintf( 'Nehtw API error (HTTP %d).', $code ), $data );
    }
    return $data;
}

function nehtw_gateway_api_get_me() {
    return nehtw_gateway_api_get( '/api/me' );
}

function nehtw_gateway_api_get_stocksites() {
    return nehtw_gateway_api_get( '/api/stocksites' );
}

function nehtw_gateway_api_get_stockinfo( $site = null, $stock_id = null, $url = null ) {
    $path_parts = array( '/api/stockinfo' );
    if ( null !== $site ) {
        $path_parts[] = rawurlencode( $site );
        if ( null !== $stock_id ) {
            $path_parts[] = rawurlencode( $stock_id );
        }
    }
    $path  = implode( '/', $path_parts );
    $query = array();
    if ( null !== $url && '' !== $url ) {
        $query['url'] = rawurlencode( $url );
    }
    return nehtw_gateway_api_get( $path, $query );
}

function nehtw_gateway_api_stockorder( $site, $stock_id, $url = null ) {
    $path = sprintf( '/api/stockorder/%s/%s', rawurlencode( $site ), rawurlencode( $stock_id ) );
    $query = array();
    if ( null !== $url && '' !== $url ) {
        $query['url'] = rawurlencode( $url );
    }
    return nehtw_gateway_api_get( $path, $query );
}

function nehtw_gateway_api_order_status( $task_id, $responsetype = 'any' ) {
    $path = sprintf( '/api/order/%s/status', rawurlencode( $task_id ) );
    return nehtw_gateway_api_get( $path, array( 'responsetype' => $responsetype ) );
}

function nehtw_gateway_api_order_download( $task_id, $responsetype = 'any' ) {
    $path = sprintf( '/api/v2/order/%s/download', rawurlencode( $task_id ) );
    return nehtw_gateway_api_get( $path, array( 'responsetype' => $responsetype ) );
}

function nehtw_gateway_register_rest_routes() {
    register_rest_route( 'nehtw/v1', '/stock-order', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'nehtw_gateway_rest_stock_order',
        'permission_callback' => function () { return is_user_logged_in(); },
    ) );

    register_rest_route( 'nehtw/v1', '/orders', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'nehtw_gateway_rest_get_orders',
        'permission_callback' => function () { return is_user_logged_in(); },
        'args'                => array(
            'per_page' => array( 'type' => 'integer', 'default' => 10, 'sanitize_callback' => 'absint' ),
            'page'     => array( 'type' => 'integer', 'default' => 1, 'sanitize_callback' => 'absint' ),
        ),
    ) );

    register_rest_route( 'nehtw/v1', '/orders/(?P<task_id>[^/]+)/redownload', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'nehtw_gateway_rest_redownload_order',
        'permission_callback' => function () { return is_user_logged_in(); },
        'args'                => array(
            'task_id' => array(
                'type'              => 'string',
                'required'          => true,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ) );
}
add_action( 'rest_api_init', 'nehtw_gateway_register_rest_routes' );

function nehtw_gateway_rest_stock_order( WP_REST_Request $request ) {
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_Error( 'nehtw_not_logged_in', __( 'You must be logged in to place a stock order.', 'nehtw-gateway' ), array( 'status' => 401 ) );
    }

    $site        = sanitize_key( (string) $request->get_param( 'site' ) );
    $stock_id    = sanitize_text_field( (string) $request->get_param( 'stock_id' ) );
    $source_url  = $request->get_param( 'source_url' );
    $cost_points = (float) $request->get_param( 'cost_points' );

    if ( ! empty( $source_url ) ) {
        $source_url = esc_url_raw( $source_url );
    } else {
        $source_url = null;
    }

    if ( '' === $site || '' === $stock_id ) {
        return new WP_Error( 'nehtw_missing_params', __( 'Both "site" and "stock_id" are required.', 'nehtw-gateway' ), array( 'status' => 400 ) );
    }

    $existing_order = Nehtw_Gateway_Stock_Orders::find_existing_user_order( $user_id, $site, $stock_id );
    if ( $existing_order && ! empty( $existing_order['download_link'] ) ) {
        $balance = nehtw_gateway_get_balance( $user_id );

        return array(
            'success'        => true,
            'task_id'        => isset( $existing_order['task_id'] ) ? $existing_order['task_id'] : '',
            'order_id'       => isset( $existing_order['id'] ) ? (int) $existing_order['id'] : 0,
            'new_balance'    => $balance,
            'already_owned'  => true,
            'download_link'  => $existing_order['download_link'],
            'status'         => isset( $existing_order['status'] ) ? $existing_order['status'] : '',
            'site'           => isset( $existing_order['site'] ) ? $existing_order['site'] : '',
            'stock_id'       => isset( $existing_order['stock_id'] ) ? $existing_order['stock_id'] : '',
        );
    }

    $balance = nehtw_gateway_get_balance( $user_id );
    if ( $cost_points > 0 && $balance < $cost_points ) {
        return new WP_Error(
            'nehtw_not_enough_points',
            sprintf( __( 'Not enough points. You have %1$s, but this order costs %2$s.', 'nehtw-gateway' ), $balance, $cost_points ),
            array( 'status' => 400, 'balance' => $balance, 'required' => $cost_points )
        );
    }

    $resp = nehtw_gateway_api_stockorder( $site, $stock_id, $source_url );

    if ( is_wp_error( $resp ) ) {
        $resp->add_data( array( 'status' => 502 ) );
        return $resp;
    }

    if ( isset( $resp['error'] ) && $resp['error'] ) {
        $msg = isset( $resp['message'] ) ? (string) $resp['message'] : 'Unknown error from Nehtw.';
        return new WP_Error( 'nehtw_remote_error', $msg, array( 'status' => 400, 'nehtw_response' => $resp ) );
    }

    $task_id = isset( $resp['task_id'] ) ? (string) $resp['task_id'] : '';

    if ( '' === $task_id ) {
        return new WP_Error( 'nehtw_no_task_id', __( 'Nehtw response did not contain a task_id.', 'nehtw-gateway' ), array( 'status' => 500, 'nehtw_response' => $resp ) );
    }

    if ( $cost_points > 0 ) {
        nehtw_gateway_add_transaction(
            $user_id,
            'download_stock',
            -1 * $cost_points,
            array( 'meta' => array( 'task_id' => $task_id, 'site' => $site, 'stock_id' => $stock_id, 'source' => 'rest_api' ) )
        );
    }

    $order_id = nehtw_gateway_create_stock_order(
        $user_id, $site, $stock_id, $source_url, $task_id, $cost_points, null, 'pending',
        array( 'raw_response' => $resp )
    );

    $new_balance = nehtw_gateway_get_balance( $user_id );

    return array(
        'success'     => true,
        'task_id'     => $task_id,
        'order_id'    => $order_id,
        'new_balance' => $new_balance,
        'remote'      => $resp,
    );
}

function nehtw_gateway_format_order_for_rest( $row ) {
    if ( ! is_array( $row ) ) {
        return array();
    }

    $raw_response = array();
    if ( isset( $row['raw_response'] ) ) {
        $maybe_raw = maybe_unserialize( $row['raw_response'] );

        if ( is_array( $maybe_raw ) ) {
            $raw_response = $maybe_raw;
        } elseif ( is_object( $maybe_raw ) ) {
            $object_json = wp_json_encode( $maybe_raw );
            if ( $object_json ) {
                $decoded = json_decode( $object_json, true );
                if ( is_array( $decoded ) ) {
                    $raw_response = $decoded;
                }
            }
        } elseif ( is_string( $maybe_raw ) ) {
            $decoded = json_decode( $maybe_raw, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                $raw_response = $decoded;
            }
        }
    }

    $find_preview = function( $data ) use ( &$find_preview ) {
        if ( ! is_array( $data ) ) {
            return '';
        }

        $keys = array( 'preview_thumb', 'preview', 'thumbnail', 'thumb_url', 'preview_url' );
        foreach ( $keys as $key ) {
            if ( isset( $data[ $key ] ) && '' !== $data[ $key ] && null !== $data[ $key ] ) {
                $sanitized = esc_url_raw( (string) $data[ $key ] );
                if ( '' !== $sanitized ) {
                    return $sanitized;
                }
            }
        }

        foreach ( $data as $value ) {
            if ( is_array( $value ) ) {
                $found = $find_preview( $value );
                if ( '' !== $found ) {
                    return $found;
                }
            }
        }

        return '';
    };

    $preview_thumb = $find_preview( $raw_response );

    $download_link = '';
    if ( isset( $row['download_link'] ) && '' !== $row['download_link'] ) {
        $maybe_download_link = esc_url_raw( (string) $row['download_link'] );
        if ( '' !== $maybe_download_link ) {
            $download_link = $maybe_download_link;
        }
    }

    $source_url = '';
    if ( isset( $row['source_url'] ) && '' !== $row['source_url'] ) {
        $maybe_source_url = esc_url_raw( (string) $row['source_url'] );
        if ( '' !== $maybe_source_url ) {
            $source_url = $maybe_source_url;
        }
    }

    $status = isset( $row['status'] ) ? (string) $row['status'] : '';

    $nehtw_cost = null;
    if ( isset( $row['nehtw_cost'] ) && '' !== $row['nehtw_cost'] && null !== $row['nehtw_cost'] ) {
        $nehtw_cost = floatval( $row['nehtw_cost'] );
    }

    $file_name = isset( $row['file_name'] ) ? sanitize_text_field( $row['file_name'] ) : '';
    $link_type = isset( $row['link_type'] ) ? sanitize_text_field( $row['link_type'] ) : '';

    return array(
        'id'                  => isset( $row['id'] ) ? (int) $row['id'] : 0,
        'task_id'             => isset( $row['task_id'] ) ? (string) $row['task_id'] : '',
        'site'                => isset( $row['site'] ) ? (string) $row['site'] : '',
        'stock_id'            => array_key_exists( 'stock_id', $row ) && null !== $row['stock_id'] ? (string) $row['stock_id'] : '',
        'source_url'          => $source_url,
        'status'              => $status,
        'cost_points'         => isset( $row['cost_points'] ) ? floatval( $row['cost_points'] ) : 0.0,
        'nehtw_cost'          => $nehtw_cost,
        'download_link'       => $download_link,
        'file_name'           => $file_name,
        'link_type'           => $link_type,
        'created_at'          => isset( $row['created_at'] ) ? (string) $row['created_at'] : '',
        'updated_at'          => isset( $row['updated_at'] ) ? (string) $row['updated_at'] : '',
        'preview_thumb'       => $preview_thumb,
        'can_redownload_free' => ( '' !== $download_link ) && ( 'error' !== strtolower( $status ) ),
    );
}

function nehtw_gateway_rest_get_orders( $request ) {
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_Error( 'nehtw_not_logged_in', __( 'You must be logged in to see your orders.', 'nehtw-gateway' ), array( 'status' => 401 ) );
    }

    $per_page = (int) $request->get_param( 'per_page' );
    if ( $per_page <= 0 ) {
        $per_page = 10;
    }

    $page = (int) $request->get_param( 'page' );
    if ( $page <= 0 ) {
        $page = 1;
    }

    $offset = ( $page - 1 ) * $per_page;
    $orders = Nehtw_Gateway_Stock_Orders::get_user_orders( $user_id, $per_page, $offset );
    if ( ! is_array( $orders ) ) {
        $orders = array();
    }

    $formatted_orders = array_map( 'nehtw_gateway_format_order_for_rest', $orders );

    return rest_ensure_response( array(
        'data'     => $formatted_orders,
        'page'     => $page,
        'per_page' => $per_page,
    ) );
}

function nehtw_gateway_rest_redownload_order( WP_REST_Request $request ) {
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_Error( 'nehtw_not_logged_in', __( 'You must be logged in to access this download.', 'nehtw-gateway' ), array( 'status' => 401 ) );
    }

    $task_id = sanitize_text_field( (string) $request->get_param( 'task_id' ) );

    $order = nehtw_gateway_get_order_by_task_id( $task_id );

    if ( ! $order ) {
        return new WP_Error( 'nehtw_order_not_found', __( 'Order not found.', 'nehtw-gateway' ), array( 'status' => 404 ) );
    }

    if ( (int) $order['user_id'] !== (int) $user_id ) {
        return new WP_Error( 'nehtw_forbidden', __( 'You are not allowed to access this order.', 'nehtw-gateway' ), array( 'status' => 403 ) );
    }

    $api = nehtw_gateway_api_order_download( $task_id, 'any' );

    if ( is_wp_error( $api ) ) {
        $api->add_data( array( 'status' => 502 ) );
        return $api;
    }

    if ( isset( $api['error'] ) && $api['error'] ) {
        $message = isset( $api['message'] ) ? (string) $api['message'] : __( 'Unknown error from Nehtw.', 'nehtw-gateway' );
        return new WP_Error( 'nehtw_remote_error', $message, array( 'status' => 502, 'nehtw_response' => $api ) );
    }

    $download_link = '';
    if ( isset( $api['download_link'] ) && '' !== $api['download_link'] ) {
        $download_link = esc_url_raw( (string) $api['download_link'] );
    }

    if ( '' === $download_link && isset( $api['url'] ) && '' !== $api['url'] ) {
        $download_link = esc_url_raw( (string) $api['url'] );
    }

    if ( '' === $download_link ) {
        return new WP_Error( 'nehtw_no_download_link', __( 'Unable to retrieve a download link from Nehtw.', 'nehtw-gateway' ), array( 'status' => 502 ) );
    }

    $fields = array(
        'download_link' => $download_link,
        'raw_response'  => $api,
    );

    if ( isset( $api['file_name'] ) && '' !== $api['file_name'] ) {
        $fields['file_name'] = $api['file_name'];
    } elseif ( isset( $api['filename'] ) && '' !== $api['filename'] ) {
        $fields['file_name'] = $api['filename'];
    }

    if ( isset( $api['link_type'] ) && '' !== $api['link_type'] ) {
        $fields['link_type'] = $api['link_type'];
    }

    $current_status = isset( $order['status'] ) && '' !== $order['status'] ? $order['status'] : 'completed';

    $updated = nehtw_gateway_update_stock_order_status( $task_id, $current_status, $fields );

    if ( ! $updated ) {
        return new WP_Error( 'nehtw_failed_update', __( 'Could not update the order with the refreshed download link.', 'nehtw-gateway' ), array( 'status' => 500 ) );
    }

    // nehtw_gateway_add_transaction(
    //     $user_id,
    //     'redownload_stock',
    //     0,
    //     array( 'meta' => array( 'task_id' => $task_id ) )
    // );

    $updated_order = nehtw_gateway_get_order_by_task_id( $task_id );

    if ( ! $updated_order ) {
        return new WP_Error( 'nehtw_order_not_found', __( 'Order not found after update.', 'nehtw-gateway' ), array( 'status' => 500 ) );
    }

    $formatted_order = nehtw_gateway_format_order_for_rest( $updated_order );

    return rest_ensure_response( array(
        'success'       => true,
        'order'         => $formatted_order,
        'download_link' => $download_link,
    ) );
}

function nehtw_gateway_register_admin_menu() {
    add_menu_page(
        __( 'Nehtw Gateway', 'nehtw-gateway' ),
        __( 'Nehtw Gateway', 'nehtw-gateway' ),
        'manage_options',
        'nehtw-gateway',
        'nehtw_gateway_render_admin_page',
        'dashicons-images-alt2',
        58
    );
}
add_action( 'admin_menu', 'nehtw_gateway_register_admin_menu' );

function nehtw_gateway_enqueue_admin_assets( $hook_suffix ) {
    if ( 'toplevel_page_nehtw-gateway' !== $hook_suffix ) return;

    wp_enqueue_script( 'wp-element' );
    wp_enqueue_script( 'wp-api-fetch' );

    wp_enqueue_script(
        'nehtw-gateway-react-app',
        NEHTW_GATEWAY_PLUGIN_URL . 'assets/js/nehtw-react-app.js',
        array( 'wp-element', 'wp-api-fetch' ),
        NEHTW_GATEWAY_VERSION,
        true
    );

    wp_localize_script( 'nehtw-gateway-react-app', 'nehtwGatewaySettings', array(
        'defaultCostPoints' => 5,
    ) );
}
add_action( 'admin_enqueue_scripts', 'nehtw_gateway_enqueue_admin_assets' );

function nehtw_gateway_enqueue_frontend_assets() {
    // Only on My Downloads page (make sure slug is correct)
    if ( ! is_page( 'my-downloads' ) ) {
        return;
    }

    // Styles
    wp_enqueue_style(
        'nehtw-gateway-dashboard',
        NEHTW_GATEWAY_PLUGIN_URL . 'assets/css/nehtw-dashboard.css',
        array(),
        NEHTW_GATEWAY_VERSION
    );

    // WordPress React & apiFetch
    wp_enqueue_script( 'wp-element' );
    wp_enqueue_script( 'wp-api-fetch' );

    // Front-end React app
    wp_enqueue_script(
        'nehtw-gateway-dashboard',
        NEHTW_GATEWAY_PLUGIN_URL . 'assets/js/nehtw-dashboard.js',
        array( 'wp-element', 'wp-api-fetch' ),
        NEHTW_GATEWAY_VERSION,
        true
    );

    $current_user = wp_get_current_user();
    $user_id = get_current_user_id();
    
    // Get wallet transactions for the user
    $transactions = array();
    if ( $user_id > 0 ) {
        $txns = nehtw_gateway_get_transactions( $user_id, 20, 0 );
        foreach ( $txns as $txn ) {
            $meta = ! empty( $txn['meta'] ) ? json_decode( $txn['meta'], true ) : array();
            $transactions[] = array(
                'id'         => (int) $txn['id'],
                'type'       => $txn['type'],
                'points'     => (float) $txn['points'],
                'created_at' => $txn['created_at'],
                'meta'       => $meta,
            );
        }
    }

    wp_localize_script(
        'nehtw-gateway-dashboard',
        'nehtwDashboardSettings',
        array(
            'restUrl'           => esc_url_raw( rest_url( 'nehtw/v1/' ) ),
            'nonce'             => wp_create_nonce( 'wp_rest' ),
            'defaultCostPoints' => 5,
            'user'              => array(
                'id'          => (int) $user_id,
                'displayName' => $current_user ? $current_user->display_name : '',
            ),
            'pricingUrl'        => site_url( '/pricing/' ),
            'transactions'      => $transactions,
            'homeUrl'           => home_url(),
        )
    );
}
add_action( 'wp_enqueue_scripts', 'nehtw_gateway_enqueue_frontend_assets' );

function nehtw_gateway_my_downloads_shortcode() {
    if ( ! is_user_logged_in() ) {
        ob_start();
        ?>
        <div class="nehtw-dashboard-guest">
            <p><?php esc_html_e( 'You need to log in to see your downloads.', 'nehtw-gateway' ); ?></p>
            <a class="button" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">
                <?php esc_html_e( 'Log in', 'nehtw-gateway' ); ?>
            </a>
        </div>
        <?php
        return ob_get_clean();
    }

    return '<div id="nehtw-gateway-dashboard-root"></div>';
}
add_shortcode( 'nehtw_gateway_my_downloads', 'nehtw_gateway_my_downloads_shortcode' );

function nehtw_gateway_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
    }

    $current_user_id = get_current_user_id();
    $wallet_message = $api_error = $api_result = $stock_order_message = $stock_order_error = $stock_order_result = $download_check_error = $download_check_result = '';

    if ( isset( $_POST['nehtw_wallet_action'] ) && 'add_test_points' === $_POST['nehtw_wallet_action'] ) {
        check_admin_referer( 'nehtw_wallet_add_points' );
        $amount = isset( $_POST['nehtw_wallet_amount'] ) ? floatval( $_POST['nehtw_wallet_amount'] ) : 0;
        if ( 0.0 !== $amount && $current_user_id ) {
            nehtw_gateway_add_transaction(
                $current_user_id,
                'admin_adjust',
                $amount,
                array( 'meta' => array( 'reason' => 'Admin test credit from Nehtw Gateway dashboard' ) )
            );
            $wallet_message = sprintf( __( 'Added %s points to your wallet.', 'nehtw-gateway' ), esc_html( $amount ) );
        } else {
            $wallet_message = __( 'Please enter a non-zero amount.', 'nehtw-gateway' );
        }
    }

    if ( isset( $_POST['nehtw_action'] ) && 'test_api_me' === $_POST['nehtw_action'] ) {
        check_admin_referer( 'nehtw_api_test_me' );
        $response = nehtw_gateway_api_get_me();
        if ( is_wp_error( $response ) ) {
            $api_error = sprintf( 'API error: %s', esc_html( $response->get_error_message() ) );
        } else {
            $api_result = $response;
        }
    }

    $balance      = nehtw_gateway_get_balance( $current_user_id );
    $transactions = nehtw_gateway_get_transactions( $current_user_id, 10 );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Nehtw Gateway – Wallet & API Debug', 'nehtw-gateway' ); ?></h1>

        <?php if ( ! empty( $wallet_message ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( $wallet_message ); ?></p>
            </div>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Your Current Balance', 'nehtw-gateway' ); ?></h2>
        <p><?php printf( esc_html__( 'You currently have %s points.', 'nehtw-gateway' ), '<strong>' . esc_html( $balance ) . '</strong>' ); ?></p>

        <hr />

        <h2><?php esc_html_e( 'React Test – Paste URL (Prototype)', 'nehtw-gateway' ); ?></h2>
        <div id="nehtw-gateway-react-app"></div>

    </div>
    <?php
}

function nehtw_gateway_init() {
    // Future: Additional initialization
}
add_action( 'plugins_loaded', 'nehtw_gateway_init' );
/**
 * Shortcode: [nehtw_gateway_my_downloads]
 * Renders the React dashboard container.
 */
