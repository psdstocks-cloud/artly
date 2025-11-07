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
define( 'NEHTW_GATEWAY_OPTION_API_KEY', 'nehtw_gateway_api_key' );

require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-stock-orders.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-download-history.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-subscriptions.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-wallet-topups.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-email-templates.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-stock.php';

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
    $table_subscriptions = $wpdb->prefix . 'nehtw_subscriptions';
    
    // Check if we need to add new columns to stock_orders table
    $stock_columns = $wpdb->get_col( "DESC {$table_stock}", 0 );
    $needs_preview_thumb = ! in_array( 'preview_thumb', $stock_columns, true );
    $needs_provider_label = ! in_array( 'provider_label', $stock_columns, true );

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
        provider_label VARCHAR(100) NULL,
        stock_id VARCHAR(191) NULL,
        source_url TEXT NULL,
        preview_thumb TEXT NULL,
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
        KEY created_at (created_at),
        KEY user_site_stock (user_id, site, stock_id)
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

    $sql_subscriptions = "CREATE TABLE {$table_subscriptions} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        plan_key VARCHAR(64) NOT NULL,
        points_per_interval FLOAT NOT NULL DEFAULT 0,
        `interval` VARCHAR(32) NOT NULL DEFAULT 'month',
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        next_renewal_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        meta LONGTEXT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY status (status),
        KEY plan_key (plan_key),
        KEY next_renewal_at (next_renewal_at)
    ) {$charset_collate};";

    dbDelta( $sql_wallet );
    dbDelta( $sql_stock );
    dbDelta( $sql_ai );
    dbDelta( $sql_stock_sites );
    dbDelta( $sql_subscriptions );
    
    // Add new columns if they don't exist
    if ( $needs_preview_thumb ) {
        $wpdb->query( "ALTER TABLE {$table_stock} ADD COLUMN preview_thumb TEXT NULL AFTER source_url" );
    }
    if ( $needs_provider_label ) {
        $wpdb->query( "ALTER TABLE {$table_stock} ADD COLUMN provider_label VARCHAR(100) NULL AFTER site" );
    }
}

// Migration function to add new columns on plugin update
function nehtw_gateway_maybe_migrate_stock_orders() {
    global $wpdb;
    $table_stock = $wpdb->prefix . 'nehtw_stock_orders';
    
    $stock_columns = $wpdb->get_col( "DESC {$table_stock}", 0 );
    $needs_preview_thumb = ! in_array( 'preview_thumb', $stock_columns, true );
    $needs_provider_label = ! in_array( 'provider_label', $stock_columns, true );
    
    if ( $needs_preview_thumb ) {
        $wpdb->query( "ALTER TABLE {$table_stock} ADD COLUMN preview_thumb TEXT NULL AFTER source_url" );
    }
    if ( $needs_provider_label ) {
        $wpdb->query( "ALTER TABLE {$table_stock} ADD COLUMN provider_label VARCHAR(100) NULL AFTER site" );
    }
}
add_action( 'admin_init', 'nehtw_gateway_maybe_migrate_stock_orders' );

register_activation_hook( NEHTW_GATEWAY_PLUGIN_FILE, 'nehtw_gateway_activate' );

function nehtw_gateway_get_table_name( $alias ) {
    global $wpdb;
    $map = array(
        'wallet_transactions' => $wpdb->prefix . 'nehtw_wallet_transactions',
        'stock_orders'        => $wpdb->prefix . 'nehtw_stock_orders',
        'ai_jobs'             => $wpdb->prefix . 'nehtw_ai_jobs',
        'stock_sites'         => $wpdb->prefix . 'nehtw_stock_sites',
        'subscriptions'        => $wpdb->prefix . 'nehtw_subscriptions',
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

/**
 * Get the current wallet points balance for a specific user.
 *
 * @param int $user_id
 * @return float
 */
function nehtw_gateway_get_user_points_balance( $user_id ) {
    global $wpdb;

    $user_id = intval( $user_id );
    if ( $user_id <= 0 ) {
        return 0;
    }

    $table = nehtw_gateway_get_table_name( 'wallet_transactions' );
    if ( ! $table ) {
        return 0;
    }

    $sql = $wpdb->prepare(
        "SELECT COALESCE(SUM(points), 0) FROM {$table} WHERE user_id = %d",
        $user_id
    );

    $total = $wpdb->get_var( $sql );

    return floatval( $total );
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

/**
 * Get the wallet transactions table name.
 *
 * This reuses the existing helper function.
 */
function nehtw_gateway_get_wallet_transactions_table() {
    return nehtw_gateway_get_table_name( 'wallet_transactions' );
}

/**
 * Fetch paginated wallet transactions for a user.
 *
 * @param int    $user_id
 * @param int    $page     1-based page
 * @param int    $per_page items per page
 * @param string $type     'all'|'stock'|'ai'|'admin'|'other'
 *
 * @return array {
 *   'items'       => array<array>,
 *   'total'       => int,
 *   'total_pages' => int,
 * }
 */
function nehtw_gateway_get_user_transactions( $user_id, $page = 1, $per_page = 20, $type = 'all' ) {
    global $wpdb;

    $user_id  = intval( $user_id );
    $page     = max( 1, intval( $page ) );
    $per_page = max( 1, intval( $per_page ) );

    if ( $user_id <= 0 ) {
        return array(
            'items'       => array(),
            'total'       => 0,
            'total_pages' => 1,
        );
    }

    $table = nehtw_gateway_get_wallet_transactions_table();

    if ( ! $table ) {
        return array(
            'items'       => array(),
            'total'       => 0,
            'total_pages' => 1,
        );
    }

    $where_clauses = array( 'user_id = %d' );
    $where_params  = array( $user_id );

    // Optional type filter
    if ( 'all' !== $type ) {
        switch ( $type ) {
            case 'stock':
                $where_clauses[] = "type LIKE %s";
                $where_params[]  = 'stock_%';
                break;
            case 'ai':
                $where_clauses[] = "type LIKE %s";
                $where_params[]  = 'ai_%';
                break;
            case 'admin':
                $where_clauses[] = "type LIKE %s";
                $where_params[]  = 'admin_%';
                break;
            case 'other':
                $where_clauses[] = "type NOT LIKE %s";
                $where_clauses[] = "type NOT LIKE %s";
                $where_clauses[] = "type NOT LIKE %s";
                $where_params[]  = 'stock_%';
                $where_params[]  = 'ai_%';
                $where_params[]  = 'admin_%';
                break;
        }
    }

    $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

    // Count total
    $sql_count = "SELECT COUNT(*) FROM {$table} {$where_sql}";
    $total     = (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $where_params ) );

    $total_pages = max( 1, (int) ceil( $total / $per_page ) );
    $offset      = ( $page - 1 ) * $per_page;

    // Fetch items ordered by newest first
    $sql_items = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d";
    $query_params = array_merge( $where_params, array( $per_page, $offset ) );

    $rows = $wpdb->get_results( $wpdb->prepare( $sql_items, $query_params ), ARRAY_A );

    // Normalize rows: add direction (credit/debit), parsed meta, etc.
    $items = array();
    foreach ( $rows as $row ) {
        $points = isset( $row['points'] ) ? (float) $row['points'] : 0.0;
        $meta   = array();

        if ( ! empty( $row['meta'] ) ) {
            $decoded = json_decode( $row['meta'], true );
            if ( is_array( $decoded ) ) {
                $meta = $decoded;
            }
        }

        // Convert DATETIME to Unix timestamp
        $created_at_timestamp = 0;
        if ( ! empty( $row['created_at'] ) && '0000-00-00 00:00:00' !== $row['created_at'] ) {
            $created_at_timestamp = strtotime( $row['created_at'] );
        }

        $items[] = array(
            'id'         => isset( $row['id'] ) ? (int) $row['id'] : 0,
            'type'       => isset( $row['type'] ) ? (string) $row['type'] : '',
            'points'     => $points,
            'created_at' => $created_at_timestamp,
            'meta'       => $meta,
        );
    }

    return array(
        'items'       => $items,
        'total'       => $total,
        'total_pages' => $total_pages,
    );
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
    $preview_thumb = isset( $extra['preview_thumb'] ) ? esc_url_raw( $extra['preview_thumb'] ) : null;
    $provider_label = isset( $extra['provider_label'] ) ? sanitize_text_field( $extra['provider_label'] ) : null;
    
    // Get provider label from config if not provided
    if ( empty( $provider_label ) && function_exists( 'nehtw_gateway_get_stock_sites_config' ) ) {
        $sites_config = nehtw_gateway_get_stock_sites_config();
        if ( isset( $sites_config[ $site ]['label'] ) ) {
            $provider_label = sanitize_text_field( $sites_config[ $site ]['label'] );
        }
    }

    $now = current_time( 'mysql' );
    
    // Check if a record already exists for this user+site+stock_id (deduplication)
    $existing = null;
    if ( $stock_id ) {
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, updated_at FROM {$table} WHERE user_id = %d AND site = %s AND stock_id = %s ORDER BY updated_at DESC LIMIT 1",
                $user_id,
                $site,
                $stock_id
            ),
            ARRAY_A
        );
    }
    
    if ( $existing ) {
        // Update existing record instead of creating duplicate
        $update_data = array(
            'task_id'     => $task_id,
            'status'     => $status,
            'cost_points' => $cost_points,
            'updated_at'  => $now,
        );
        $update_format = array( '%s', '%s', '%f', '%s' );
        
        if ( null !== $nehtw_cost ) {
            $update_data['nehtw_cost'] = $nehtw_cost;
            $update_format[] = '%f';
        }
        if ( null !== $download_link ) {
            $update_data['download_link'] = $download_link;
            $update_format[] = '%s';
        }
        if ( null !== $file_name ) {
            $update_data['file_name'] = $file_name;
            $update_format[] = '%s';
        }
        if ( null !== $link_type ) {
            $update_data['link_type'] = $link_type;
            $update_format[] = '%s';
        }
        if ( null !== $raw_response ) {
            $update_data['raw_response'] = $raw_response;
            $update_format[] = '%s';
        }
        if ( null !== $preview_thumb ) {
            $update_data['preview_thumb'] = $preview_thumb;
            $update_format[] = '%s';
        }
        if ( null !== $provider_label ) {
            $update_data['provider_label'] = $provider_label;
            $update_format[] = '%s';
        }
        if ( null !== $source_url ) {
            $update_data['source_url'] = $source_url;
            $update_format[] = '%s';
        }
        
        $updated = $wpdb->update(
            $table,
            $update_data,
            array( 'id' => $existing['id'] ),
            $update_format,
            array( '%d' )
        );
        
        return $updated !== false ? (int) $existing['id'] : false;
    }
    
    // Insert new record
    $inserted = $wpdb->insert(
        $table,
        array(
            'user_id'       => $user_id,
            'site'          => $site,
            'provider_label' => $provider_label,
            'stock_id'      => $stock_id,
            'source_url'    => $source_url,
            'preview_thumb' => $preview_thumb,
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
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
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

function nehtw_gateway_get_ai_job_by_job_id( $job_id ) {
    global $wpdb;

    $table  = nehtw_gateway_get_table_name( 'ai_jobs' );
    $job_id = sanitize_text_field( $job_id );

    if ( ! $table || '' === $job_id ) {
        return null;
    }

    $sql = $wpdb->prepare( "SELECT * FROM {$table} WHERE job_id = %s LIMIT 1", $job_id );
    $row = $wpdb->get_row( $sql, ARRAY_A );

    return $row ?: null;
}

function nehtw_gateway_update_ai_job( $job_id, $fields = array() ) {
    global $wpdb;

    $table  = nehtw_gateway_get_table_name( 'ai_jobs' );
    $job_id = sanitize_text_field( $job_id );

    if ( ! $table || '' === $job_id || empty( $fields ) ) {
        return false;
    }

    $data   = array( 'updated_at' => current_time( 'mysql' ) );
    $format = array( '%s' );

    if ( isset( $fields['status'] ) ) {
        $data['status'] = sanitize_text_field( $fields['status'] );
        $format[]       = '%s';
    }

    if ( isset( $fields['cost_points'] ) ) {
        $data['cost_points'] = floatval( $fields['cost_points'] );
        $format[]            = '%f';
    }

    if ( isset( $fields['percentage_complete'] ) ) {
        $data['percentage_complete'] = intval( $fields['percentage_complete'] );
        $format[]                    = '%d';
    }

    if ( isset( $fields['files'] ) ) {
        $data['files'] = maybe_serialize( $fields['files'] );
        $format[]      = '%s';
    }

    if ( isset( $fields['error_message'] ) ) {
        $data['error_message'] = sanitize_text_field( $fields['error_message'] );
        $format[]              = '%s';
    }

    if ( isset( $fields['prompt'] ) ) {
        $data['prompt'] = wp_strip_all_tags( $fields['prompt'] );
        $format[]       = '%s';
    }

    if ( isset( $fields['created_at'] ) ) {
        $data['created_at'] = sanitize_text_field( $fields['created_at'] );
        $format[]           = '%s';
    }

    $updated = $wpdb->update( $table, $data, array( 'job_id' => $job_id ), $format, array( '%s' ) );

    return ( false !== $updated );
}

function nehtw_gateway_api_ai_public( $job_id ) {
    $path = sprintf( '/api/aig/public/%s', rawurlencode( $job_id ) );
    return nehtw_gateway_api_get( $path );
}

function nehtw_gateway_get_api_key() {
    $key = get_option( NEHTW_GATEWAY_OPTION_API_KEY, '' );

    if ( ! is_string( $key ) ) {
        $key = '';
    }

    return trim( $key );
}

function nehtw_gateway_require_api_key() {
    $key = nehtw_gateway_get_api_key();

    if ( '' === $key ) {
        return new WP_Error(
            'nehtw_no_api_key',
            __( 'Artly download service is not configured. Please contact support.', 'nehtw-gateway' )
        );
    }

    return true;
}

function nehtw_gateway_build_api_headers() {
    $headers = array( 'Accept' => 'application/json' );
    $api_key = nehtw_gateway_get_api_key();
    if ( '' !== $api_key ) {
        $headers['X-Api-Key'] = $api_key;
    }
    return $headers;
}

function nehtw_gateway_admin_notice_missing_key() {
    if ( ! is_admin() ) {
        return;
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( '' !== nehtw_gateway_get_api_key() ) {
        return;
    }

    echo '<div class="notice notice-warning"><p>';
    echo esc_html__( 'Nehtw API key is not configured. Artly stock downloads will not work until you add your key.', 'nehtw-gateway' );
    echo '</p></div>';
}
add_action( 'admin_notices', 'nehtw_gateway_admin_notice_missing_key' );

function nehtw_gateway_api_get( $path, $query_args = array() ) {
    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        return $key_check;
    }

    $api_key = nehtw_gateway_get_api_key();

    $base = rtrim( NEHTW_GATEWAY_API_BASE, '/' );
    $path = '/' . ltrim( $path, '/' );
    $url  = $base . $path;

    if ( ! is_array( $query_args ) ) {
        $query_args = array();
    }

    $query_args['apikey'] = $api_key;

    $url = add_query_arg( $query_args, $url );

    $response = wp_remote_get( $url, array(
        'headers' => nehtw_gateway_build_api_headers(),
        'timeout' => 20,
    ) );

    if ( is_wp_error( $response ) ) return $response;

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );

    if ( $code < 200 || $code >= 300 ) {
        $decoded = json_decode( $body, true );
        $message = '';

        if ( is_array( $decoded ) && ! empty( $decoded['message'] ) ) {
            $message = (string) $decoded['message'];
        } elseif ( '' !== trim( $body ) ) {
            $message = $body;
        } else {
            $message = sprintf( 'Nehtw API error (HTTP %d).', $code );
        }

        return new WP_Error(
            'nehtw_http_error',
            $message,
            array(
                'status'        => (int) $code,
                'response_body' => $body,
                'response_json' => $decoded,
                'endpoint'      => $path,
            )
        );
    }

    $data = json_decode( $body, true );

    if ( null === $data ) {
        return new WP_Error( 'nehtw_bad_json', sprintf( 'Nehtw API returned invalid JSON (HTTP %d).', $code ) );
    }

    return $data;
}

function nehtw_gateway_api_post_json( $path, $body = array(), $query_args = array() ) {
    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        return $key_check;
    }

    $api_key = nehtw_gateway_get_api_key();

    $base = rtrim( NEHTW_GATEWAY_API_BASE, '/' );
    $path = '/' . ltrim( $path, '/' );
    $url  = $base . $path;

    if ( ! is_array( $query_args ) ) {
        $query_args = array();
    }

    $query_args['apikey'] = $api_key;

    $url = add_query_arg( $query_args, $url );

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

function nehtw_gateway_api_stock_preview( $site, $stock_id, $url = null ) {
    $site     = (string) $site;
    $stock_id = (string) $stock_id;

    if ( '' === trim( $site ) || '' === trim( $stock_id ) ) {
        return new WP_Error( 'nehtw_preview_missing_params', __( 'Missing preview parameters.', 'nehtw-gateway' ) );
    }

    $path  = sprintf( '/api/v2/stock/%s/%s/preview', rawurlencode( $site ), rawurlencode( $stock_id ) );
    $query = array();

    if ( null !== $url && '' !== $url ) {
        $query['url'] = rawurlencode( $url );
    }

    $response = nehtw_gateway_api_get( $path, $query );

    if ( is_wp_error( $response ) ) {
        $fallback = nehtw_gateway_api_get_stockinfo( $site, $stock_id, $url );
        if ( ! is_wp_error( $fallback ) ) {
            return $fallback;
        }

        return $response;
    }

    return $response;
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

    register_rest_route( 'nehtw/v1', '/download-history', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'nehtw_rest_get_download_history',
        'permission_callback' => function () { return is_user_logged_in(); },
        'args'                => array(
            'page'     => array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 1,
            ),
            'per_page' => array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 20,
            ),
            'type'     => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'all',
            ),
        ),
    ) );

    register_rest_route( 'nehtw/v1', '/download-link', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'nehtw_rest_get_download_link',
        'permission_callback' => function () { return is_user_logged_in(); },
    ) );
    
    // Artly-branded re-download endpoint using history_id
    register_rest_route( 'artly/v1', '/download-redownload', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'nehtw_gateway_rest_download_redownload',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ) );

    register_rest_route( 'nehtw/v1', '/wallet-transactions', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'nehtw_rest_get_wallet_transactions',
        'permission_callback' => function () { return is_user_logged_in(); },
        'args'                => array(
            'page'     => array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 1,
            ),
            'per_page' => array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 20,
            ),
            'type'     => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'all',
            ),
        ),
    ) );

    register_rest_route( 'nehtw/v1', '/wallet-transactions/export', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'nehtw_rest_export_wallet_transactions_csv',
        'permission_callback' => function () { return is_user_logged_in(); },
        'args'                => array(
            'type' => array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => 'all',
            ),
        ),
    ) );

    // Artly-branded stock ordering endpoint (batch mode)
    register_rest_route( 'artly/v1', '/stock-order', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'nehtw_gateway_rest_stock_order_batch',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ) );

    // Artly-branded stock order preview endpoint (single link)
    register_rest_route(
        'artly/v1',
        '/stock-order-preview',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'nehtw_gateway_rest_stock_order_preview',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        )
    );

    register_rest_route(
        'artly/v1',
        '/wallet-info',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'artly_get_wallet_info',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        )
    );

    // Artly-branded stock order status polling endpoint
    register_rest_route( 'artly/v1', '/stock-order-status', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'nehtw_gateway_rest_stock_order_status',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ) );

    register_rest_route(
        'artly/v1',
        '/stock-orders/(?P<task_id>[^/]+)/status',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'artly_stock_order_rest_check_status',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => array(
                'task_id' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        )
    );

    register_rest_route(
        'artly/v1',
        '/stock-orders/(?P<task_id>[^/]+)/download',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => 'artly_stock_order_rest_generate_download_link',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => array(
                'task_id' => array(
                    'type'              => 'string',
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        )
    );
}
add_action( 'rest_api_init', 'nehtw_gateway_register_rest_routes' );

function artly_get_wallet_info( WP_REST_Request $request ) {
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_Error(
            'artly_wallet_not_logged_in',
            __( 'You must be logged in to view wallet information.', 'nehtw-gateway' ),
            array( 'status' => 401 )
        );
    }

    $balance = function_exists( 'nehtw_gateway_get_balance' )
        ? nehtw_gateway_get_balance( $user_id )
        : 0.0;

    $next_billing = get_user_meta( $user_id, '_artly_next_billing_date', true );

    if ( empty( $next_billing ) && function_exists( 'wcs_get_users_subscriptions' ) ) {
        $subscriptions = wcs_get_users_subscriptions( $user_id );

        if ( ! empty( $subscriptions ) && is_array( $subscriptions ) ) {
            foreach ( $subscriptions as $subscription ) {
                if ( ! is_object( $subscription ) ) {
                    continue;
                }

                if ( method_exists( $subscription, 'has_status' ) && ! $subscription->has_status( array( 'active', 'pending-cancel' ) ) ) {
                    continue;
                }

                $timestamp = false;
                if ( method_exists( $subscription, 'get_time' ) ) {
                    $timestamp = $subscription->get_time( 'next_payment' );
                } elseif ( method_exists( $subscription, 'get_date' ) ) {
                    $date = $subscription->get_date( 'next_payment' );
                    if ( $date ) {
                        $timestamp = strtotime( $date );
                    }
                }

                if ( $timestamp ) {
                    $next_billing = gmdate( 'Y-m-d H:i:s', $timestamp );
                    break;
                }
            }
        }
    }

    if ( empty( $next_billing ) ) {
        $future = strtotime( '+30 days' );
        if ( $future ) {
            $next_billing = gmdate( 'Y-m-d H:i:s', $future );
        }
    }

    $formatted_next = 'N/A';
    if ( ! empty( $next_billing ) ) {
        $timestamp = is_numeric( $next_billing ) ? (int) $next_billing : strtotime( $next_billing );
        if ( $timestamp ) {
            $formatted_next = date_i18n( 'M j, Y', $timestamp );
        }
    }

    return new WP_REST_Response(
        array(
            'balance'      => (float) $balance,
            'next_billing' => $formatted_next,
        ),
        200
    );
}

function nehtw_gateway_rest_stock_order( WP_REST_Request $request ) {
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_Error( 'nehtw_not_logged_in', __( 'You must be logged in to place a stock order.', 'nehtw-gateway' ), array( 'status' => 401 ) );
    }

    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        $key_check->add_data( array( 'status' => 400 ) );
        return $key_check;
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

/**
 * Handle batch stock ordering from Stock Ordering page (artly/v1/stock-order).
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function nehtw_gateway_rest_stock_order_batch( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response( array( 'message' => 'Unauthorized' ), 401 );
    }

    $user_id = get_current_user_id();

    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        $key_check->add_data( array( 'status' => 400 ) );
        return $key_check;
    }

    $payload_links = $request->get_param( 'links' );

    if ( ! is_array( $payload_links ) || empty( $payload_links ) ) {
        return new WP_REST_Response( array( 'message' => 'No links provided.' ), 400 );
    }

    $sites_config = function_exists( 'nehtw_gateway_get_stock_sites_config' )
        ? nehtw_gateway_get_stock_sites_config()
        : array();

    // Get current balance (using existing helper)
    $balance = function_exists( 'nehtw_gateway_get_balance' )
        ? nehtw_gateway_get_balance( $user_id )
        : 0.0;

    $results = array();

    foreach ( $payload_links as $entry ) {
        $url      = isset( $entry['url'] ) ? trim( (string) $entry['url'] ) : '';
        $selected = ! empty( $entry['selected'] );

        $result = array(
            'url'     => $url,
            'status'  => '',
            'message' => '',
        );

        if ( '' === $url ) {
            $result['status']  = 'error';
            $result['message'] = __( 'Empty link.', 'nehtw-gateway' );
            $results[] = $result;
            continue;
        }

        if ( ! $selected ) {
            $result['status']  = 'skipped';
            $result['message'] = __( 'Skipped by user.', 'nehtw-gateway' );
            $results[] = $result;
            continue;
        }

        $parsed = function_exists( 'nehtw_gateway_parse_stock_url' )
            ? nehtw_gateway_parse_stock_url( $url )
            : null;

        if ( ! $parsed ) {
            $result['status']  = 'error';
            $result['message'] = __( 'Unsupported or invalid link.', 'nehtw-gateway' );
            $results[] = $result;
            continue;
        }

        $site      = $parsed['site'];
        $remote_id = $parsed['remote_id'];

        if ( empty( $sites_config[ $site ] ) ) {
            $result['status']  = 'error';
            $result['message'] = __( 'This website is not supported.', 'nehtw-gateway' );
            $results[] = $result;
            continue;
        }

        $cost_points = (float) $sites_config[ $site ]['points'];

        // Check for existing completed order (no double charge)
        $existing = function_exists( 'nehtw_gateway_get_existing_stock_order' )
            ? nehtw_gateway_get_existing_stock_order( $user_id, $site, $remote_id )
            : null;

        if ( $existing ) {
            $formatted_existing = is_array( $existing ) ? Nehtw_Gateway_Stock_Orders::format_order_for_api( $existing ) : array();
            $existing_link      = isset( $formatted_existing['download_link'] ) ? $formatted_existing['download_link'] : '';

            $result['status']   = 'already_downloaded';
            $result['message']  = __( 'You already downloaded this asset. Reusing link without charging points.', 'nehtw-gateway' );
            $result['order_id'] = isset( $existing['id'] ) ? (int) $existing['id'] : 0;
            $result['task_id']  = isset( $existing['task_id'] ) ? (string) $existing['task_id'] : '';

            if ( ! empty( $existing_link ) ) {
                $result['download_link'] = $existing_link;
                $result['download_url']  = $existing_link;
            }

            $results[] = $result;
            continue;
        }

        // Check balance
        if ( $balance < $cost_points ) {
            $result['status']  = 'insufficient_points';
            $result['message'] = __( 'Not enough points in your wallet.', 'nehtw-gateway' );
            $results[] = $result;
            continue;
        }

        // Deduct points from wallet
        if ( function_exists( 'nehtw_gateway_add_transaction' ) ) {
            nehtw_gateway_add_transaction(
                $user_id,
                'stock_order',
                -1 * $cost_points,
                array(
                    'meta' => array(
                        'source'       => 'stock_order_page',
                        'site'         => $site,
                        'remote_id'    => $remote_id,
                        'original_url' => $url,
                    ),
                )
            );
        }

        // Update local variable balance
        $balance -= $cost_points;

        // Place stock order using existing API helper
        $api_resp = function_exists( 'nehtw_gateway_api_stockorder' )
            ? nehtw_gateway_api_stockorder( $site, $remote_id, $url )
            : null;

        if ( is_wp_error( $api_resp ) || ( isset( $api_resp['error'] ) && $api_resp['error'] ) ) {
            $result['status']  = 'error';
            $result['message'] = isset( $api_resp['message'] ) ? $api_resp['message'] : __( 'Failed to place order.', 'nehtw-gateway' );
            $results[] = $result;
            // Refund points if order failed
            if ( function_exists( 'nehtw_gateway_add_transaction' ) ) {
                nehtw_gateway_add_transaction(
                    $user_id,
                    'stock_order_refund',
                    $cost_points,
                    array(
                        'meta' => array(
                            'source'       => 'stock_order_refund',
                            'site'         => $site,
                            'remote_id'    => $remote_id,
                            'original_url' => $url,
                        ),
                    )
                );
                $balance += $cost_points;
            }
            continue;
        }

        $task_id = isset( $api_resp['task_id'] ) ? (string) $api_resp['task_id'] : '';

        if ( '' === $task_id ) {
            $result['status']  = 'error';
            $result['message'] = __( 'Order placed but no task ID received.', 'nehtw-gateway' );
            $results[] = $result;
            continue;
        }

        // Get preview thumbnail if available
        $preview_thumb = null;
        if ( function_exists( 'nehtw_gateway_api_stock_preview' ) ) {
            $preview_data = nehtw_gateway_api_stock_preview( $site, $remote_id, $url );
            if ( ! is_wp_error( $preview_data ) && isset( $preview_data['image'] ) && ! empty( $preview_data['image'] ) ) {
                $preview_thumb = esc_url_raw( $preview_data['image'] );
            }
        }
        
        // Get provider label
        $provider_label = isset( $sites_config[ $site ]['label'] ) ? sanitize_text_field( $sites_config[ $site ]['label'] ) : null;

        // Create order record in database
        $order_id = function_exists( 'nehtw_gateway_create_stock_order' )
            ? nehtw_gateway_create_stock_order(
                $user_id,
                $site,
                $remote_id,
                $url,
                $task_id,
                $cost_points,
                null,
                'pending',
                array(
                    'raw_response' => $api_resp,
                    'preview_thumb' => $preview_thumb,
                    'provider_label' => $provider_label,
                )
            )
            : 0;

        // Normalize status: 'pending' from DB becomes 'queued' for frontend
        $result['status']   = 'queued';
        $result['message']  = __( 'Order queued. You\'ll see it soon in your history.', 'nehtw-gateway' );
        $result['order_id'] = $order_id;
        $result['task_id']  = $task_id;
        $results[] = $result;
    }

    // Get final balance after all transactions
    $final_balance = function_exists( 'nehtw_gateway_get_balance' )
        ? nehtw_gateway_get_balance( $user_id )
        : 0.0;

    return new WP_REST_Response(
        array(
            'orders'      => $results,
            'links'       => $results,
            'new_balance' => $final_balance,
            'balance'     => $final_balance,
        ),
        200
    );
}

/**
 * Handle stock order status polling from Stock Ordering page (artly/v1/stock-order-status).
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function nehtw_gateway_rest_stock_order_status( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response( array( 'message' => 'Unauthorized' ), 401 );
    }

    $user_id   = get_current_user_id();

    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        $key_check->add_data( array( 'status' => 400 ) );
        return $key_check;
    }

    $order_ids = $request->get_param( 'order_ids' );

    if ( ! is_array( $order_ids ) || empty( $order_ids ) ) {
        return new WP_REST_Response(
            array(
                'message' => 'No order IDs provided.',
                'orders'  => array(),
            ),
            200
        );
    }

    $order_ids = array_map( 'intval', $order_ids );
    $order_ids = array_filter( $order_ids );

    if ( empty( $order_ids ) ) {
        return new WP_REST_Response(
            array(
                'message' => 'No valid order IDs.',
                'orders'  => array(),
            ),
            200
        );
    }

    global $wpdb;

    $table = nehtw_gateway_get_table_name( 'stock_orders' );

    if ( ! $table ) {
        return new WP_REST_Response(
            array(
                'message' => 'Stock orders table not found.',
                'orders'  => array(),
            ),
            200
        );
    }

    // Fetch rows belonging to this user only.
    $placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );
    $sql          = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE user_id = %d AND id IN ($placeholders)",
        array_merge( array( $user_id ), $order_ids )
    );

    $rows = $wpdb->get_results( $sql, ARRAY_A );

    if ( ! $rows ) {
        return new WP_REST_Response(
            array(
                'message' => 'No orders found.',
                'orders'  => array(),
            ),
            200
        );
    }

    // Optional: sync queued/processing orders with Nehtw via internal helper.
    $orders = array();

    foreach ( $rows as $row ) {
        $row_id = (int) $row['id'];

        // If status is non-final, attempt sync via internal helper
        // that talks to Nehtw API and updates local DB.
        $status = isset( $row['status'] ) ? strtolower( (string) $row['status'] ) : '';
        if ( in_array( $status, array( 'pending', 'queued', 'processing' ), true ) && function_exists( 'nehtw_gateway_sync_stock_order_status' ) ) {
            nehtw_gateway_sync_stock_order_status( $row_id );
        }

        // Re-fetch the row after potential sync.
        $sql_one = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d LIMIT 1",
            $row_id,
            $user_id
        );
        $row2 = $wpdb->get_row( $sql_one, ARRAY_A );

        if ( ! $row2 ) {
            continue;
        }

        // Normalize status for frontend
        $status_normalized = strtolower( (string) $row2['status'] );
        if ( $status_normalized === 'pending' ) {
            $status_normalized = 'queued';
        } elseif ( $status_normalized === 'completed' || $status_normalized === 'complete' || $status_normalized === 'ready' ) {
            $status_normalized = 'completed';
        }

        $orders[] = array(
            'id'           => (int) $row2['id'],
            'status'       => $status_normalized,
            'site'         => isset( $row2['site'] ) ? $row2['site'] : '',
            'remote_id'    => isset( $row2['stock_id'] ) ? $row2['stock_id'] : '',
            'download_url' => ! empty( $row2['download_link'] ) ? esc_url_raw( $row2['download_link'] ) : '',
            'updated_at'   => isset( $row2['updated_at'] ) ? $row2['updated_at'] : '',
        );
    }

    return new WP_REST_Response(
        array(
            'orders' => $orders,
        ),
        200
    );
}

/**
 * Convert a mixed API payload into a normalized array.
 *
 * @param mixed $payload Raw payload from the remote service.
 *
 * @return array
 */
function nehtw_gateway_normalize_api_payload( $payload ) {
    if ( is_wp_error( $payload ) ) {
        return array();
    }

    if ( is_array( $payload ) ) {
        return $payload;
    }

    if ( is_object( $payload ) ) {
        $encoded = wp_json_encode( $payload );
        if ( $encoded ) {
            $decoded = json_decode( $encoded, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                return $decoded;
            }
        }

        return array();
    }

    if ( is_string( $payload ) ) {
        $decoded = json_decode( $payload, true );
        if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
            return $decoded;
        }
    }

    return array();
}

/**
 * Map remote status strings to UI-friendly statuses.
 *
 * @param string $status Remote status string.
 *
 * @return string
 */
function nehtw_gateway_normalize_remote_status( $status ) {
    $status = strtolower( (string) $status );

    if ( '' === $status ) {
        return 'processing';
    }

    if ( in_array( $status, array( 'pending', 'queued', 'queue', 'waiting' ), true ) ) {
        return 'queued';
    }

    if ( in_array( $status, array( 'processing', 'in_progress', 'working' ), true ) ) {
        return 'processing';
    }

    if ( in_array( $status, array( 'ready' ), true ) ) {
        return 'ready';
    }

    if ( in_array( $status, array( 'completed', 'complete', 'done', 'success', 'succeeded' ), true ) ) {
        return 'completed';
    }

    if ( in_array( $status, array( 'failed', 'error', 'cancelled', 'canceled', 'refused', 'expired' ), true ) ) {
        return 'failed';
    }

    return $status;
}

/**
 * REST callback: check the status of a specific stock order by task ID.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function artly_stock_order_rest_check_status( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Please sign in to continue.', 'nehtw-gateway' ) ),
            401
        );
    }

    $user_id = get_current_user_id();
    $task_id = trim( (string) $request->get_param( 'task_id' ) );

    if ( '' === $task_id ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'status'  => 'invalid',
                'message' => __( 'We could not find that download reference.', 'nehtw-gateway' ),
            ),
            400
        );
    }

    $order = Nehtw_Gateway_Stock_Orders::get_by_task_id( $task_id );

    if ( ! $order || (int) $order['user_id'] !== (int) $user_id ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'status'  => 'not_found',
                'message' => __( 'We could not find that download request.', 'nehtw-gateway' ),
            ),
            404
        );
    }

    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        $key_check->add_data( array( 'status' => 500 ) );
        return $key_check;
    }

    $api_response = nehtw_gateway_api_order_status( $task_id, 'any' );

    if ( is_wp_error( $api_response ) ) {
        return new WP_Error(
            'artly_stock_status_failed',
            __( 'We couldn\'t update this download right now. Please try again in a moment.', 'nehtw-gateway' ),
            array( 'status' => 502 )
        );
    }

    $api_data        = nehtw_gateway_normalize_api_payload( $api_response );
    $remote_status   = isset( $api_data['status'] ) ? $api_data['status'] : '';
    $normalized      = nehtw_gateway_normalize_remote_status( $remote_status );
    $message         = isset( $api_data['message'] ) ? wp_strip_all_tags( (string) $api_data['message'] ) : '';
    $status_payload  = array(
        'checked_at' => current_time( 'mysql' ),
        'response'   => $api_data,
    );
    $raw_merged      = Nehtw_Gateway_Stock_Orders::merge_raw_response_with_status(
        isset( $order['raw_response'] ) ? $order['raw_response'] : array(),
        $status_payload
    );

    Nehtw_Gateway_Stock_Orders::update_status(
        $task_id,
        $normalized,
        array(
            'raw_response' => $raw_merged,
        )
    );

    if ( 'failed' === $normalized && '' === $message ) {
        $message = __( 'The download failed. Please try again with a fresh link.', 'nehtw-gateway' );
    } elseif ( 'ready' === $normalized && '' === $message ) {
        $message = __( 'Preparing download link…', 'nehtw-gateway' );
    } elseif ( 'queued' === $normalized && '' === $message ) {
        $message = __( 'Queued…', 'nehtw-gateway' );
    } elseif ( 'processing' === $normalized && '' === $message ) {
        $message = __( 'Processing…', 'nehtw-gateway' );
    }

    if ( isset( $api_data['success'] ) && false === $api_data['success'] && 'failed' !== $normalized ) {
        $normalized = 'failed';
        if ( '' === $message ) {
            $message = __( 'The download failed. Please try again with a fresh link.', 'nehtw-gateway' );
        }
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'status'  => $normalized,
            'message' => $message,
            'task_id' => $task_id,
        ),
        200
    );
}

/**
 * REST callback: generate or retrieve a download link for a stock order.
 *
 * @param WP_REST_Request $request Request instance.
 *
 * @return WP_REST_Response|WP_Error
 */
function artly_stock_order_rest_generate_download_link( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Please sign in to continue.', 'nehtw-gateway' ) ),
            401
        );
    }

    $user_id = get_current_user_id();
    $task_id = trim( (string) $request->get_param( 'task_id' ) );

    if ( '' === $task_id ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'We could not find that download reference.', 'nehtw-gateway' ),
            ),
            400
        );
    }

    $order = Nehtw_Gateway_Stock_Orders::get_by_task_id( $task_id );

    if ( ! $order || (int) $order['user_id'] !== (int) $user_id ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'We could not find that download request.', 'nehtw-gateway' ),
            ),
            404
        );
    }

    $raw_data = Nehtw_Gateway_Stock_Orders::get_order_raw_data( $order );

    if ( Nehtw_Gateway_Stock_Orders::order_download_is_valid( $order, $raw_data ) ) {
        $formatted = Nehtw_Gateway_Stock_Orders::format_order_for_api( $order );
        $link      = isset( $formatted['download_link'] ) ? $formatted['download_link'] : '';

        if ( $link ) {
            return new WP_REST_Response(
                array(
                    'success'      => true,
                    'download_url' => $link,
                    'file_name'    => isset( $formatted['file_name'] ) ? $formatted['file_name'] : null,
                    'link_type'    => isset( $formatted['link_type'] ) ? $formatted['link_type'] : null,
                    'cached'       => true,
                    'status'       => isset( $formatted['status'] ) ? $formatted['status'] : 'completed',
                ),
                200
            );
        }
    }

    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        $key_check->add_data( array( 'status' => 500 ) );
        return $key_check;
    }

    $api_response = nehtw_gateway_api_order_download( $task_id, 'any' );

    if ( is_wp_error( $api_response ) ) {
        return new WP_Error(
            'artly_stock_download_failed',
            __( 'We could not prepare your download right now. Please try again shortly.', 'nehtw-gateway' ),
            array( 'status' => 502 )
        );
    }

    $api_data = nehtw_gateway_normalize_api_payload( $api_response );

    if ( empty( $api_data ) || ( isset( $api_data['error'] ) && $api_data['error'] ) ) {
        $message = isset( $api_data['message'] ) ? wp_strip_all_tags( (string) $api_data['message'] ) : '';
        if ( '' === $message ) {
            $message = __( 'We could not prepare your download right now. Please try again shortly.', 'nehtw-gateway' );
        }

        return new WP_Error( 'artly_stock_download_failed', $message, array( 'status' => 502 ) );
    }

    $download_url = '';
    foreach ( array( 'downloadLink', 'download_link', 'url', 'link' ) as $key ) {
        if ( ! empty( $api_data[ $key ] ) ) {
            $candidate = esc_url_raw( $api_data[ $key ] );
            if ( $candidate ) {
                $download_url = $candidate;
                break;
            }
        }
    }

    if ( '' === $download_url ) {
        return new WP_Error(
            'artly_stock_download_missing_link',
            __( 'We could not find a download link for this asset yet. Please try again from your downloads page.', 'nehtw-gateway' ),
            array( 'status' => 502 )
        );
    }

    $file_name = null;
    if ( ! empty( $api_data['fileName'] ) ) {
        $file_name = sanitize_text_field( $api_data['fileName'] );
    } elseif ( ! empty( $api_data['file_name'] ) ) {
        $file_name = sanitize_text_field( $api_data['file_name'] );
    }

    $link_type = null;
    if ( ! empty( $api_data['linkType'] ) ) {
        $link_type = sanitize_text_field( $api_data['linkType'] );
    } elseif ( ! empty( $api_data['link_type'] ) ) {
        $link_type = sanitize_text_field( $api_data['link_type'] );
    }

    $raw_merged = Nehtw_Gateway_Stock_Orders::merge_raw_response_with_download(
        isset( $order['raw_response'] ) ? $order['raw_response'] : array(),
        array(
            'received_at' => current_time( 'mysql' ),
            'response'    => $api_data,
        )
    );

    Nehtw_Gateway_Stock_Orders::update_status(
        $task_id,
        'completed',
        array(
            'download_link' => $download_url,
            'file_name'     => $file_name,
            'link_type'     => $link_type,
            'raw_response'  => $raw_merged,
        )
    );

    $refreshed  = Nehtw_Gateway_Stock_Orders::get_by_task_id( $task_id );
    $formatted  = $refreshed ? Nehtw_Gateway_Stock_Orders::format_order_for_api( $refreshed ) : array();
    $final_link = isset( $formatted['download_link'] ) && $formatted['download_link'] ? $formatted['download_link'] : $download_url;
    $final_name = isset( $formatted['file_name'] ) ? $formatted['file_name'] : $file_name;
    $final_type = isset( $formatted['link_type'] ) ? $formatted['link_type'] : $link_type;
    $final_status = isset( $formatted['status'] ) ? $formatted['status'] : 'completed';

    return new WP_REST_Response(
        array(
            'success'      => true,
            'download_url' => $final_link,
            'file_name'    => $final_name,
            'link_type'    => $final_type,
            'cached'       => false,
            'status'       => $final_status,
            'message'      => __( 'Ready to download', 'nehtw-gateway' ),
        ),
        200
    );
}

/**
 * Preview a stock order from a single URL, without placing the order.
 *
 * POST /wp-json/artly/v1/stock-order-preview
 * Body: { "url": "https://..." }
 *
 * Returns JSON with site, stock_id, cost_points, balance, preview_thumb, labels, etc.
 * Does NOT create orders or deduct points.
 */
function nehtw_gateway_rest_stock_order_preview( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Unauthorized', 'nehtw-gateway' ) ),
            401
        );
    }

    $user_id = get_current_user_id();
    $url     = trim( (string) $request->get_param( 'url' ) );

    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        $key_check->add_data( array( 'status' => 400 ) );
        return $key_check;
    }

    if ( '' === $url ) {
        return new WP_REST_Response(
            array( 'message' => __( 'No URL provided.', 'nehtw-gateway' ) ),
            400
        );
    }

    $parsed = function_exists( 'nehtw_gateway_parse_stock_url' )
        ? nehtw_gateway_parse_stock_url( $url )
        : null;

    if ( ! $parsed ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Unsupported or invalid link.', 'nehtw-gateway' ) ),
            400
        );
    }

    $site      = isset( $parsed['site'] ) ? sanitize_key( (string) $parsed['site'] ) : '';
    $remote_id = isset( $parsed['remote_id'] ) ? sanitize_text_field( (string) $parsed['remote_id'] ) : '';

    if ( '' === $site || '' === $remote_id ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Unsupported or invalid link.', 'nehtw-gateway' ) ),
            400
        );
    }

    $sites_config = function_exists( 'nehtw_gateway_get_stock_sites_config' )
        ? nehtw_gateway_get_stock_sites_config()
        : array();

    if ( empty( $sites_config[ $site ] ) ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Unsupported website.', 'nehtw-gateway' ) ),
            400
        );
    }

    $site_config  = $sites_config[ $site ];
    $cost_points  = isset( $site_config['points'] ) ? (float) $site_config['points'] : 0.0;
    $site_label   = isset( $site_config['label'] ) ? sanitize_text_field( $site_config['label'] ) : $site;
    $balance_raw  = function_exists( 'nehtw_gateway_get_balance' )
        ? nehtw_gateway_get_balance( $user_id )
        : 0.0;
    $balance      = (float) $balance_raw;
    $enough_points = ( $cost_points <= 0 ) || ( $balance >= $cost_points );

    $preview_raw = null;

    if ( function_exists( 'nehtw_gateway_api_stock_preview' ) ) {
        $preview_raw = nehtw_gateway_api_stock_preview( $site, $remote_id, $url );
    }

    if ( is_wp_error( $preview_raw ) ) {
        $message      = $preview_raw->get_error_message();
        $safe_message = __( 'Failed to fetch preview data.', 'nehtw-gateway' );

        return new WP_REST_Response(
            array(
                'message'      => $safe_message,
                'error_detail' => $message,
            ),
            502
        );
    }

    if ( is_string( $preview_raw ) ) {
        $decoded = json_decode( $preview_raw, true );
        if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
            $preview_raw = $decoded;
        }
    } elseif ( is_object( $preview_raw ) ) {
        $object_json = wp_json_encode( $preview_raw );
        if ( $object_json ) {
            $decoded = json_decode( $object_json, true );
            if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
                $preview_raw = $decoded;
            }
        }
    }

    if ( ! is_array( $preview_raw ) ) {
        $preview_raw = array();
    }

    if ( isset( $preview_raw['error'] ) && $preview_raw['error'] ) {
        $message      = isset( $preview_raw['message'] ) ? (string) $preview_raw['message'] : '';
        $safe_message = __( 'Failed to fetch preview data.', 'nehtw-gateway' );

        return new WP_REST_Response(
            array(
                'message'      => $safe_message,
                'error_detail' => $message,
            ),
            502
        );
    }

    $preview_data = isset( $preview_raw['data'] ) && is_array( $preview_raw['data'] )
        ? $preview_raw['data']
        : $preview_raw;

    $find_preview = function( $data ) use ( &$find_preview ) {
        if ( ! is_array( $data ) ) {
            return '';
        }

        $keys = array( 'preview_thumb', 'preview', 'thumbnail', 'thumb_url', 'preview_url', 'thumb', 'image' );
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

    $preview_thumb = $find_preview( $preview_data );

    return new WP_REST_Response(
        array(
            'success'        => true,
            'site'           => $site,
            'site_label'     => $site_label,
            'stock_id'       => $remote_id,
            'url'            => $url,
            'cost_points'    => $cost_points,
            'balance'        => $balance,
            'enough_points'  => $enough_points,
            'preview_thumb'  => $preview_thumb,
        ),
        200
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

    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        $key_check->add_data( array( 'status' => 400 ) );
        return $key_check;
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

function nehtw_rest_get_download_history( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Unauthorized', 'nehtw-gateway' ) ),
            401
        );
    }

    $user_id  = get_current_user_id();
    $page     = max( 1, (int) $request->get_param( 'page' ) );
    $per_page = max( 1, (int) $request->get_param( 'per_page' ) );
    $per_page = min( 100, $per_page );
    $type     = nehtw_gateway_history_sanitize_type( $request->get_param( 'type' ) );

    $history = array(
        'items'       => array(),
        'total'       => 0,
        'total_pages' => 0,
    );

    if ( function_exists( 'nehtw_get_user_download_history' ) ) {
        $history = nehtw_get_user_download_history( $user_id, $page, $per_page, $type );
    }

    $items = array();
    if ( ! empty( $history['items'] ) && is_array( $history['items'] ) ) {
        foreach ( $history['items'] as $item ) {
            $formatted = array(
                'kind'       => isset( $item['kind'] ) ? sanitize_key( $item['kind'] ) : '',
                'id'         => isset( $item['id'] ) ? sanitize_text_field( $item['id'] ) : '',
                'title'      => isset( $item['title'] ) ? wp_strip_all_tags( $item['title'] ) : '',
                'site'       => isset( $item['site'] ) ? sanitize_text_field( $item['site'] ) : '',
                'thumbnail'  => isset( $item['thumbnail'] ) && $item['thumbnail'] ? esc_url_raw( $item['thumbnail'] ) : '',
                'status'     => isset( $item['status'] ) ? wp_strip_all_tags( $item['status'] ) : '',
                'points'     => isset( $item['points'] ) ? floatval( $item['points'] ) : 0.0,
                'created_at' => isset( $item['created_at'] ) ? (int) $item['created_at'] : 0,
                'task_id'    => isset( $item['task_id'] ) ? sanitize_text_field( $item['task_id'] ) : '',
                'job_id'     => isset( $item['job_id'] ) ? sanitize_text_field( $item['job_id'] ) : '',
            );
            
            // Add stock-specific fields
            if ( 'stock' === $formatted['kind'] ) {
                $formatted['history_id'] = isset( $item['history_id'] ) ? intval( $item['history_id'] ) : 0;
                $formatted['provider_label'] = isset( $item['provider_label'] ) ? wp_strip_all_tags( $item['provider_label'] ) : '';
                $formatted['remote_id'] = isset( $item['remote_id'] ) ? sanitize_text_field( $item['remote_id'] ) : '';
                $formatted['stock_url'] = isset( $item['stock_url'] ) && $item['stock_url'] ? esc_url_raw( $item['stock_url'] ) : '';
                $formatted['updated_at'] = isset( $item['updated_at'] ) ? intval( $item['updated_at'] ) : $formatted['created_at'];
            }
            
            $items[] = $formatted;
        }
    }

    return new WP_REST_Response(
        array(
            'items'       => $items,
            'total'       => isset( $history['total'] ) ? (int) $history['total'] : 0,
            'total_pages' => isset( $history['total_pages'] ) ? (int) $history['total_pages'] : 0,
            'page'        => $page,
            'per_page'    => $per_page,
        ),
        200
    );
}

/**
 * Re-download endpoint for stock files using history_id.
 * Does NOT charge points again - just generates a fresh download link.
 *
 * POST /wp-json/artly/v1/download-redownload
 * Body: { "history_id": 123 }
 */
function nehtw_gateway_rest_download_redownload( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Unauthorized', 'nehtw-gateway' ) ),
            401
        );
    }

    $user_id = get_current_user_id();

    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        $key_check->add_data( array( 'status' => 400 ) );
        return $key_check;
    }

    $history_id = (int) $request->get_param( 'history_id' );
    if ( $history_id <= 0 ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Invalid history ID.', 'nehtw-gateway' ) ),
            400
        );
    }

    global $wpdb;
    $table = nehtw_gateway_get_table_name( 'stock_orders' );
    if ( ! $table ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Database error.', 'nehtw-gateway' ) ),
            500
        );
    }

    // Get order by history_id and verify it belongs to current user
    $order = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d LIMIT 1",
            $history_id,
            $user_id
        ),
        ARRAY_A
    );

    if ( ! $order ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Download not found.', 'nehtw-gateway' ) ),
            404
        );
    }

    $task_id = isset( $order['task_id'] ) ? sanitize_text_field( $order['task_id'] ) : '';
    if ( '' === $task_id ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Invalid order.', 'nehtw-gateway' ) ),
            400
        );
    }

    if ( class_exists( 'Nehtw_Gateway_Stock_Orders' ) ) {
        $raw_data = Nehtw_Gateway_Stock_Orders::get_order_raw_data( $order );

        if ( Nehtw_Gateway_Stock_Orders::order_download_is_valid( $order, $raw_data ) ) {
            $formatted    = Nehtw_Gateway_Stock_Orders::format_order_for_api( $order );
            $download_url = '';

            if ( ! empty( $formatted['download_link'] ) && is_string( $formatted['download_link'] ) ) {
                $download_url = esc_url_raw( $formatted['download_link'] );
            }

            if ( '' !== $download_url ) {
                return new WP_REST_Response(
                    array(
                        'success'      => true,
                        'download_url' => $download_url,
                        'cached'       => true,
                    ),
                    200
                );
            }
        }
    }

    // Call Nehtw API to get fresh download link (does not charge again)
    $api = nehtw_gateway_api_order_download( $task_id, 'any' );

    if ( is_wp_error( $api ) ) {
        $error_data = $api->get_error_data();
        $status     = 0;

        if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
            $status = (int) $error_data['status'];
        }

        $error_message = $api->get_error_message();

        if ( 409 === $status ) {
            error_log( 'Nehtw re-download 409 for task_id ' . $task_id . ': ' . $error_message );
            return new WP_REST_Response(
                array( 'message' => __( 'Download not ready.', 'nehtw-gateway' ) ),
                409
            );
        }

        if ( $status >= 500 || 0 === $status ) {
            $api_retry = nehtw_gateway_api_order_download( $task_id, 'any' );

            if ( ! is_wp_error( $api_retry ) ) {
                $api = $api_retry;
            } else {
                $error_message = $api_retry->get_error_message();
                $error_data    = $api_retry->get_error_data();
                if ( is_array( $error_data ) && isset( $error_data['status'] ) ) {
                    $status = (int) $error_data['status'];
                }
                $api = $api_retry;
            }
        }

        if ( is_wp_error( $api ) ) {
            error_log( 'Nehtw re-download error for task_id ' . $task_id . ' (status ' . $status . '): ' . $error_message );

            return new WP_REST_Response(
                array( 'message' => __( 'Failed to generate download link.', 'nehtw-gateway' ) ),
                502
            );
        }
    }

    if ( isset( $api['error'] ) && $api['error'] ) {
        $message = isset( $api['message'] ) ? (string) $api['message'] : '';
        error_log( 'Nehtw re-download error response for task_id ' . $task_id . ': ' . $message );
        return new WP_REST_Response(
            array( 'message' => __( 'We couldn\'t generate a download link right now. Please try again later.', 'nehtw-gateway' ) ),
            502
        );
    }

    $download_url = '';
    $candidates   = array( 'downloadLink', 'download_link', 'url', 'link' );
    foreach ( $candidates as $candidate ) {
        if ( isset( $api[ $candidate ] ) && '' !== $api[ $candidate ] ) {
            $maybe = esc_url_raw( (string) $api[ $candidate ] );
            if ( '' !== $maybe ) {
                $download_url = $maybe;
                break;
            }
        }
    }

    if ( '' === $download_url && isset( $api['data'] ) ) {
        foreach ( $candidates as $candidate ) {
            if ( isset( $api['data'][ $candidate ] ) && '' !== $api['data'][ $candidate ] ) {
                $maybe = esc_url_raw( (string) $api['data'][ $candidate ] );
                if ( '' !== $maybe ) {
                    $download_url = $maybe;
                    break;
                }
            }
        }
    }

    if ( '' === $download_url ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Download link not available at the moment. Please try again later.', 'nehtw-gateway' ) ),
            502
        );
    }

    // Update the order record with the new download link
    $update_fields = array(
        'download_link' => $download_url,
        'raw_response'  => class_exists( 'Nehtw_Gateway_Stock_Orders' ) ? Nehtw_Gateway_Stock_Orders::merge_raw_response_with_download( isset( $order['raw_response'] ) ? $order['raw_response'] : array(), $api ) : $api,
    );

    if ( isset( $api['file_name'] ) && '' !== $api['file_name'] ) {
        $update_fields['file_name'] = $api['file_name'];
    } elseif ( isset( $api['filename'] ) && '' !== $api['filename'] ) {
        $update_fields['file_name'] = $api['filename'];
    }

    if ( isset( $api['link_type'] ) && '' !== $api['link_type'] ) {
        $update_fields['link_type'] = $api['link_type'];
    }

    $current_status = isset( $order['status'] ) && '' !== $order['status'] ? $order['status'] : 'completed';
    nehtw_gateway_update_stock_order_status( $task_id, $current_status, $update_fields );

    return new WP_REST_Response(
        array(
            'success'      => true,
            'download_url' => $download_url,
        ),
        200
    );
}

function nehtw_rest_get_download_link( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Unauthorized', 'nehtw-gateway' ) ),
            401
        );
    }

    $user_id = get_current_user_id();

    $params = $request->get_json_params();
    if ( ! is_array( $params ) ) {
        $params = array();
    }

    $kind = $request->get_param( 'kind' );
    if ( null === $kind && isset( $params['kind'] ) ) {
        $kind = $params['kind'];
    }

    $id = $request->get_param( 'id' );
    if ( null === $id && isset( $params['id'] ) ) {
        $id = $params['id'];
    }

    $kind = sanitize_key( (string) $kind );
    $id   = sanitize_text_field( (string) $id );

    if ( '' === $kind || '' === $id ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Missing parameters.', 'nehtw-gateway' ) ),
            400
        );
    }

    $download_url = '';

    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        $key_check->add_data( array( 'status' => 400 ) );
        return $key_check;
    }

    if ( 'stock' === $kind ) {
        $order = nehtw_gateway_get_order_by_task_id( $id );

        if ( ! $order ) {
            return new WP_REST_Response(
                array( 'message' => __( 'Download not found.', 'nehtw-gateway' ) ),
                404
            );
        }

        if ( (int) $order['user_id'] !== (int) $user_id ) {
            return new WP_REST_Response(
                array( 'message' => __( 'Forbidden', 'nehtw-gateway' ) ),
                403
            );
        }

        $raw_data = class_exists( 'Nehtw_Gateway_Stock_Orders' ) ? Nehtw_Gateway_Stock_Orders::get_order_raw_data( $order ) : array();
        if ( class_exists( 'Nehtw_Gateway_Stock_Orders' ) && Nehtw_Gateway_Stock_Orders::order_download_is_valid( $order, $raw_data ) ) {
            $formatted    = Nehtw_Gateway_Stock_Orders::format_order_for_api( $order );
            $download_url = isset( $formatted['download_link'] ) ? esc_url_raw( $formatted['download_link'] ) : '';
        }

        if ( '' === $download_url && ! empty( $raw_data ) && class_exists( 'Nehtw_Gateway_Stock_Orders' ) ) {
            $download_url = Nehtw_Gateway_Stock_Orders::extract_download_link_from_array( $raw_data );
        }

        if ( '' === $download_url ) {
            $api = nehtw_gateway_api_order_download( $id, 'any' );

            if ( is_wp_error( $api ) ) {
                $api->add_data( array( 'status' => 502 ) );
                return $api;
            }

            if ( isset( $api['error'] ) && $api['error'] ) {
                $message = isset( $api['message'] ) ? (string) $api['message'] : __( 'Unable to refresh the download link.', 'nehtw-gateway' );
                return new WP_REST_Response( array( 'message' => $message ), 502 );
            }

            $candidates = array( 'download_link', 'url', 'link' );
            foreach ( $candidates as $candidate ) {
                if ( isset( $api[ $candidate ] ) && '' !== $api[ $candidate ] ) {
                    $maybe = esc_url_raw( (string) $api[ $candidate ] );
                    if ( '' !== $maybe ) {
                        $download_url = $maybe;
                        break;
                    }
                }
            }

            if ( '' === $download_url && isset( $api['data'] ) ) {
                foreach ( $candidates as $candidate ) {
                    if ( isset( $api['data'][ $candidate ] ) && '' !== $api['data'][ $candidate ] ) {
                        $maybe = esc_url_raw( (string) $api['data'][ $candidate ] );
                        if ( '' !== $maybe ) {
                            $download_url = $maybe;
                            break;
                        }
                    }
                }
            }

            if ( '' === $download_url ) {
                return new WP_REST_Response(
                    array( 'message' => __( 'Download not ready.', 'nehtw-gateway' ) ),
                    409
                );
            }

            $update_fields = array(
                'download_link' => $download_url,
                'raw_response'  => class_exists( 'Nehtw_Gateway_Stock_Orders' ) ? Nehtw_Gateway_Stock_Orders::merge_raw_response_with_download( isset( $order['raw_response'] ) ? $order['raw_response'] : array(), $api ) : $api,
            );

            if ( isset( $api['file_name'] ) && '' !== $api['file_name'] ) {
                $update_fields['file_name'] = $api['file_name'];
            } elseif ( isset( $api['filename'] ) && '' !== $api['filename'] ) {
                $update_fields['file_name'] = $api['filename'];
            }

            if ( isset( $api['link_type'] ) && '' !== $api['link_type'] ) {
                $update_fields['link_type'] = $api['link_type'];
            }

            nehtw_gateway_update_stock_order_status( $id, isset( $order['status'] ) ? $order['status'] : 'completed', $update_fields );
        }
    } elseif ( 'ai' === $kind ) {
        $job = nehtw_gateway_get_ai_job_by_job_id( $id );

        if ( ! $job ) {
            return new WP_REST_Response(
                array( 'message' => __( 'Download not found.', 'nehtw-gateway' ) ),
                404
            );
        }

        if ( (int) $job['user_id'] !== (int) $user_id ) {
            return new WP_REST_Response(
                array( 'message' => __( 'Forbidden', 'nehtw-gateway' ) ),
                403
            );
        }

        $download_url = nehtw_gateway_extract_ai_download_url( isset( $job['files'] ) ? $job['files'] : array() );

        if ( '' === $download_url ) {
            $api = nehtw_gateway_api_ai_public( $id );

            if ( is_wp_error( $api ) ) {
                $api->add_data( array( 'status' => 502 ) );
                return $api;
            }

            if ( isset( $api['files'] ) ) {
                $download_url = nehtw_gateway_extract_ai_download_url( $api['files'] );
            }

            if ( '' === $download_url && isset( $api['data']['files'] ) ) {
                $download_url = nehtw_gateway_extract_ai_download_url( $api['data']['files'] );
            }

            $update_fields = array();

            if ( isset( $api['status'] ) ) {
                $update_fields['status'] = $api['status'];
            }

            if ( isset( $api['prompt'] ) ) {
                $update_fields['prompt'] = $api['prompt'];
            }

            if ( isset( $api['files'] ) ) {
                $update_fields['files'] = $api['files'];
            }

            if ( isset( $api['percentage_complete'] ) ) {
                $update_fields['percentage_complete'] = $api['percentage_complete'];
            } elseif ( isset( $api['percentage'] ) ) {
                $update_fields['percentage_complete'] = $api['percentage'];
            }

            if ( isset( $api['error'] ) && $api['error'] ) {
                $update_fields['error_message'] = is_string( $api['error'] ) ? $api['error'] : __( 'AI generation reported an error.', 'nehtw-gateway' );
            }

            if ( ! empty( $update_fields ) ) {
                nehtw_gateway_update_ai_job( $id, $update_fields );
            }
        }

        if ( '' === $download_url ) {
            return new WP_REST_Response(
                array( 'message' => __( 'Download not ready.', 'nehtw-gateway' ) ),
                409
            );
        }
    } else {
        return new WP_REST_Response(
            array( 'message' => __( 'Unsupported download type.', 'nehtw-gateway' ) ),
            400
        );
    }

    return new WP_REST_Response(
        array( 'url' => esc_url_raw( $download_url ) ),
        200
    );
}

function nehtw_rest_get_wallet_transactions( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Unauthorized', 'nehtw-gateway' ) ),
            401
        );
    }

    $user_id  = get_current_user_id();
    $page     = (int) $request->get_param( 'page' ) ?: 1;
    $per_page = (int) $request->get_param( 'per_page' ) ?: 20;
    $type     = $request->get_param( 'type' ) ?: 'all';

    if ( ! function_exists( 'nehtw_gateway_get_user_transactions' ) ) {
        return new WP_REST_Response(
            array( 'items' => array(), 'total' => 0, 'total_pages' => 1 ),
            200
        );
    }

    $data = nehtw_gateway_get_user_transactions( $user_id, $page, $per_page, $type );

    return new WP_REST_Response(
        array(
            'items'       => $data['items'],
            'total'       => $data['total'],
            'total_pages' => $data['total_pages'],
        ),
        200
    );
}

function nehtw_rest_export_wallet_transactions_csv( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Unauthorized', 'nehtw-gateway' ) ),
            401
        );
    }

    $user_id = get_current_user_id();
    $type    = $request->get_param( 'type' ) ?: 'all';

    if ( ! function_exists( 'nehtw_gateway_get_user_transactions' ) ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Transactions helper not found', 'nehtw-gateway' ) ),
            500
        );
    }

    // Export *all* user transactions of this type, no pagination.
    $data  = nehtw_gateway_get_user_transactions( $user_id, 1, PHP_INT_MAX, $type );
    $items = $data['items'];

    $csv_lines   = array();
    $csv_lines[] = array( 'ID', 'Type', 'Direction', 'Points', 'Description', 'Created At' );

    foreach ( $items as $item ) {
        $points    = isset( $item['points'] ) ? (float) $item['points'] : 0.0;
        $direction = $points >= 0 ? 'credit' : 'debit';
        $meta      = isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : array();
        $note      = isset( $meta['note'] ) ? $meta['note'] : '';
        $source    = isset( $meta['source'] ) ? $meta['source'] : '';

        $desc_parts = array();
        if ( $note ) {
            $desc_parts[] = $note;
        }
        if ( $source ) {
            $desc_parts[] = 'source: ' . $source;
        }
        $description = implode( ' | ', $desc_parts );

        $created_at = isset( $item['created_at'] ) ? (int) $item['created_at'] : 0;
        $date_str   = $created_at
            ? date_i18n( 'Y-m-d H:i:s', $created_at )
            : '';

        $csv_lines[] = array(
            $item['id'] ?? '',
            $item['type'] ?? '',
            $direction,
            $points,
            $description,
            $date_str,
        );
    }

    // Build CSV string
    $fh = fopen( 'php://temp', 'w+' );
    foreach ( $csv_lines as $row ) {
        fputcsv( $fh, $row );
    }
    rewind( $fh );
    $csv = stream_get_contents( $fh );
    fclose( $fh );

    $filename = 'artly-wallet-transactions-' . date_i18n( 'Y-m-d' ) . '.csv';

    return new WP_REST_Response(
        $csv,
        200,
        array(
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        )
    );
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

/**
 * Register the User Points submenu under Nehtw Gateway.
 */
function nehtw_gateway_register_user_points_submenu() {
    add_submenu_page(
        'nehtw-gateway',
        __( 'User Points', 'nehtw-gateway' ),
        __( 'User Points', 'nehtw-gateway' ),
        'manage_options',
        'nehtw-gateway-user-points',
        'nehtw_gateway_render_user_points_page'
    );
}
add_action( 'admin_menu', 'nehtw_gateway_register_user_points_submenu' );

/**
 * Register the Subscription Plans submenu under Nehtw Gateway.
 */
function nehtw_gateway_register_subscription_plans_submenu() {
    add_submenu_page(
        'nehtw-gateway',
        __( 'Subscription Plans', 'nehtw-gateway' ),
        __( 'Subscription Plans', 'nehtw-gateway' ),
        'manage_options',
        'nehtw-gateway-subscriptions',
        'nehtw_gateway_render_subscription_plans_page'
    );
}
add_action( 'admin_menu', 'nehtw_gateway_register_subscription_plans_submenu' );

/**
 * Register the User Subscriptions submenu under Nehtw Gateway.
 */
function nehtw_gateway_register_user_subscriptions_submenu() {
    add_submenu_page(
        'nehtw-gateway',
        __( 'User Subscriptions', 'nehtw-gateway' ),
        __( 'User Subscriptions', 'nehtw-gateway' ),
        'manage_options',
        'nehtw-gateway-user-subscriptions',
        'nehtw_gateway_render_user_subscriptions_page'
    );
}
add_action( 'admin_menu', 'nehtw_gateway_register_user_subscriptions_submenu' );

/**
 * Register the Wallet Top-ups submenu under Nehtw Gateway.
 */
function nehtw_gateway_register_wallet_topups_submenu() {
    add_submenu_page(
        'nehtw-gateway',
        __( 'Wallet Top-ups', 'nehtw-gateway' ),
        __( 'Wallet Top-ups', 'nehtw-gateway' ),
        'manage_options',
        'nehtw-gateway-wallet-topups',
        'nehtw_gateway_render_wallet_topups_page'
    );
}
add_action( 'admin_menu', 'nehtw_gateway_register_wallet_topups_submenu' );

/**
 * Register the Email Templates submenu under Nehtw Gateway.
 */
function nehtw_gateway_register_email_templates_submenu() {
    add_submenu_page(
        'nehtw-gateway',
        __( 'Email Templates', 'nehtw-gateway' ),
        __( 'Email Templates', 'nehtw-gateway' ),
        'manage_options',
        'nehtw-gateway-email-templates',
        'nehtw_gateway_render_email_templates_page'
    );
}
add_action( 'admin_menu', 'nehtw_gateway_register_email_templates_submenu' );

/**
 * Render the User Points admin page.
 */
function nehtw_gateway_render_user_points_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
    }

    $success_message = '';
    $error_message   = '';

    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['nehtw_gateway_action'] ) ) {
        $action = sanitize_key( wp_unslash( $_POST['nehtw_gateway_action'] ) );

        if ( 'adjust_points' === $action ) {
            check_admin_referer( 'nehtw_gateway_adjust_points', 'nehtw_gateway_nonce' );

            $user_id = isset( $_POST['user_id'] ) ? intval( wp_unslash( $_POST['user_id'] ) ) : 0;
            $amount  = isset( $_POST['amount'] ) ? floatval( wp_unslash( $_POST['amount'] ) ) : 0;
            $note    = isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '';

            if ( $user_id <= 0 || 0 == $amount ) {
                $error_message = __( 'Please provide a valid user and amount.', 'nehtw-gateway' );
            } else {
                $meta = array(
                    'source' => 'admin_manual_adjustment',
                    'note'   => $note,
                );

                $inserted = nehtw_gateway_add_transaction(
                    $user_id,
                    'admin_manual_adjust',
                    $amount,
                    array(
                        'meta' => $meta,
                    )
                );

                if ( $inserted ) {
                    $success_message = __( 'Points updated for user.', 'nehtw-gateway' );
                } else {
                    $error_message = __( 'Unable to update points for user.', 'nehtw-gateway' );
                }
            }
        }
    }

    $paged    = isset( $_GET['paged'] ) ? max( 1, intval( wp_unslash( $_GET['paged'] ) ) ) : 1;
    $per_page = 20;

    $users = get_users(
        array(
            'number' => $per_page,
            'paged'  => $paged,
        )
    );

    $total_users_data = count_users();
    $total_users      = isset( $total_users_data['total_users'] ) ? intval( $total_users_data['total_users'] ) : 0;
    $total_pages      = $total_users > 0 ? (int) ceil( $total_users / $per_page ) : 1;
    $base_url = menu_page_url( 'nehtw-gateway-user-points', false );
    if ( ! $base_url ) {
        $base_url = add_query_arg(
            array( 'page' => 'nehtw-gateway-user-points' ),
            admin_url( 'admin.php' )
        );
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'User Points', 'nehtw-gateway' ); ?></h1>

        <?php if ( $success_message ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $success_message ); ?></p></div>
        <?php endif; ?>

        <?php if ( $error_message ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error_message ); ?></p></div>
        <?php endif; ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'nehtw-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'nehtw-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Current Points', 'nehtw-gateway' ); ?></th>
                    <th><?php esc_html_e( 'Adjust Points', 'nehtw-gateway' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $users ) ) : ?>
                    <?php foreach ( $users as $user ) : ?>
                        <?php $balance = nehtw_gateway_get_user_points_balance( $user->ID ); ?>
                        <tr>
                            <td><?php echo esc_html( $user->display_name ); ?></td>
                            <td><?php echo esc_html( $user->user_email ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $balance, 2 ) ); ?></td>
                            <td>
                                <form method="post" style="display:flex; gap:6px; align-items:center;">
                                    <?php wp_nonce_field( 'nehtw_gateway_adjust_points', 'nehtw_gateway_nonce' ); ?>
                                    <input type="hidden" name="nehtw_gateway_action" value="adjust_points" />
                                    <input type="hidden" name="user_id" value="<?php echo esc_attr( $user->ID ); ?>" />
                                    <input type="number" step="0.01" name="amount" placeholder="<?php esc_attr_e( 'Amount', 'nehtw-gateway' ); ?>" />
                                    <input type="text" name="note" placeholder="<?php esc_attr_e( 'Note (optional)', 'nehtw-gateway' ); ?>" />
                                    <button type="submit" class="button button-small">
                                        <?php esc_html_e( 'Add / Subtract', 'nehtw-gateway' ); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4"><?php esc_html_e( 'No users found.', 'nehtw-gateway' ); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ( $total_pages > 1 ) : ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php if ( $paged > 1 ) : ?>
                        <?php $prev_url = esc_url( add_query_arg( 'paged', $paged - 1, $base_url ) ); ?>
                        <a class="button" href="<?php echo $prev_url; ?>">&laquo; <?php esc_html_e( 'Previous', 'nehtw-gateway' ); ?></a>
                    <?php endif; ?>

                    <span class="pagination-links">
                        <?php printf( esc_html__( 'Page %1$d of %2$d', 'nehtw-gateway' ), intval( $paged ), intval( $total_pages ) ); ?>
                    </span>

                    <?php if ( $paged < $total_pages ) : ?>
                        <?php $next_url = esc_url( add_query_arg( 'paged', $paged + 1, $base_url ) ); ?>
                        <a class="button" href="<?php echo $next_url; ?>"><?php esc_html_e( 'Next', 'nehtw-gateway' ); ?> &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render the Subscription Plans admin page.
 */
function nehtw_gateway_render_subscription_plans_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
    }

    $success_message = '';
    $error_message   = '';

    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['nehtw_gateway_action'] ) ) {
        $action = sanitize_key( wp_unslash( $_POST['nehtw_gateway_action'] ) );

        if ( 'save_plan' === $action ) {
            check_admin_referer( 'nehtw_gateway_save_plan', 'nehtw_gateway_nonce' );

            $key        = isset( $_POST['plan_key'] ) ? sanitize_key( wp_unslash( $_POST['plan_key'] ) ) : '';
            $name       = isset( $_POST['plan_name'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_name'] ) ) : '';
            $points     = isset( $_POST['plan_points'] ) ? floatval( wp_unslash( $_POST['plan_points'] ) ) : 0;
            $price      = isset( $_POST['plan_price'] ) ? sanitize_text_field( wp_unslash( $_POST['plan_price'] ) ) : '';
            $desc       = isset( $_POST['plan_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['plan_description'] ) ) : '';
            $highlight  = isset( $_POST['plan_highlight'] ) ? true : false;
            $product_id = isset( $_POST['plan_product_id'] ) ? intval( wp_unslash( $_POST['plan_product_id'] ) ) : 0;

            if ( empty( $key ) || empty( $name ) || $points <= 0 ) {
                $error_message = __( 'Please provide key, name, and points.', 'nehtw-gateway' );
            } else {
                $plans = nehtw_gateway_get_subscription_plans();
                $plan  = array(
                    'key'         => $key,
                    'name'        => $name,
                    'points'      => $points,
                    'price_label' => $price,
                    'description' => $desc,
                    'highlight'   => $highlight,
                    'product_id'  => $product_id > 0 ? $product_id : null,
                );

                // Update or add
                $found = false;
                foreach ( $plans as &$existing_plan ) {
                    if ( isset( $existing_plan['key'] ) && $existing_plan['key'] === $key ) {
                        $existing_plan = $plan;
                        $found         = true;
                        break;
                    }
                }

                if ( ! $found ) {
                    $plans[] = $plan;
                }

                nehtw_gateway_save_subscription_plans( $plans );
                $success_message = __( 'Plan saved successfully.', 'nehtw-gateway' );
            }
        } elseif ( 'delete_plan' === $action ) {
            check_admin_referer( 'nehtw_gateway_delete_plan', 'nehtw_gateway_nonce' );

            $key = isset( $_POST['plan_key'] ) ? sanitize_key( wp_unslash( $_POST['plan_key'] ) ) : '';

            if ( ! empty( $key ) ) {
                $plans = nehtw_gateway_get_subscription_plans();
                $plans = array_filter(
                    $plans,
                    function( $plan ) use ( $key ) {
                        return ! isset( $plan['key'] ) || $plan['key'] !== $key;
                    }
                );
                nehtw_gateway_save_subscription_plans( array_values( $plans ) );
                $success_message = __( 'Plan deleted successfully.', 'nehtw-gateway' );
            }
        }
    }

    $plans = nehtw_gateway_get_subscription_plans();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Subscription Plans', 'nehtw-gateway' ); ?></h1>

        <?php if ( $success_message ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $success_message ); ?></p></div>
        <?php endif; ?>

        <?php if ( $error_message ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error_message ); ?></p></div>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Add / Edit Plan', 'nehtw-gateway' ); ?></h2>
        <form method="post" style="max-width: 600px;">
            <?php wp_nonce_field( 'nehtw_gateway_save_plan', 'nehtw_gateway_nonce' ); ?>
            <input type="hidden" name="nehtw_gateway_action" value="save_plan" />
            <?php
            // Get plan to edit if editing (check for plan_key in URL or form)
            $editing_plan = null;
            $edit_key     = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
            if ( $edit_key ) {
                foreach ( $plans as $p ) {
                    if ( isset( $p['key'] ) && $p['key'] === $edit_key ) {
                        $editing_plan = $p;
                        break;
                    }
                }
            }
            ?>
            <table class="form-table">
                <tr>
                    <th><label for="plan_key"><?php esc_html_e( 'Plan Key', 'nehtw-gateway' ); ?></label></th>
                    <td><input type="text" id="plan_key" name="plan_key" required class="regular-text" placeholder="starter_100" value="<?php echo $editing_plan ? esc_attr( $editing_plan['key'] ) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="plan_name"><?php esc_html_e( 'Plan Name', 'nehtw-gateway' ); ?></label></th>
                    <td><input type="text" id="plan_name" name="plan_name" required class="regular-text" placeholder="Starter 100" value="<?php echo $editing_plan ? esc_attr( $editing_plan['name'] ) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="plan_points"><?php esc_html_e( 'Points per Month', 'nehtw-gateway' ); ?></label></th>
                    <td><input type="number" id="plan_points" name="plan_points" required step="0.01" min="0" class="regular-text" placeholder="100" value="<?php echo $editing_plan ? esc_attr( $editing_plan['points'] ) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="plan_price"><?php esc_html_e( 'Price Label', 'nehtw-gateway' ); ?></label></th>
                    <td><input type="text" id="plan_price" name="plan_price" class="regular-text" placeholder="EGP 99 / month" value="<?php echo $editing_plan ? esc_attr( $editing_plan['price_label'] ) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="plan_description"><?php esc_html_e( 'Description', 'nehtw-gateway' ); ?></label></th>
                    <td><textarea id="plan_description" name="plan_description" class="large-text" rows="3"><?php echo $editing_plan ? esc_textarea( $editing_plan['description'] ) : ''; ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="plan_highlight"><?php esc_html_e( 'Highlight', 'nehtw-gateway' ); ?></label></th>
                    <td><input type="checkbox" id="plan_highlight" name="plan_highlight" <?php echo $editing_plan && ! empty( $editing_plan['highlight'] ) ? 'checked' : ''; ?> /></td>
                </tr>
                <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                <tr>
                    <th><label for="plan_product_id"><?php esc_html_e( 'WooCommerce Product', 'nehtw-gateway' ); ?></label></th>
                    <td>
                        <select id="plan_product_id" name="plan_product_id" class="regular-text">
                            <option value="0"><?php esc_html_e( '— None —', 'nehtw-gateway' ); ?></option>
                            <?php
                            $args = array(
                                'post_type'      => 'product',
                                'post_status'    => 'publish',
                                'posts_per_page' => -1,
                                'orderby'        => 'title',
                                'order'          => 'ASC',
                            );
                            $products = get_posts( $args );
                            $editing_product_id = $editing_plan && isset( $editing_plan['product_id'] ) ? (int) $editing_plan['product_id'] : 0;
                            foreach ( $products as $product ) :
                                $selected = $editing_product_id === $product->ID ? 'selected' : '';
                                ?>
                                <option value="<?php echo esc_attr( $product->ID ); ?>" <?php echo $selected; ?>>
                                    <?php echo esc_html( $product->post_title ); ?> (ID: <?php echo esc_html( $product->ID ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select the WooCommerce product that customers will purchase to subscribe to this plan.', 'nehtw-gateway' ); ?></p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Plan', 'nehtw-gateway' ); ?></button>
            </p>
        </form>

        <h2><?php esc_html_e( 'Existing Plans', 'nehtw-gateway' ); ?></h2>
        <?php if ( ! empty( $plans ) ) : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Key', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Points', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Price', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'WooCommerce Product', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Highlight', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nehtw-gateway' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $plans as $plan ) : ?>
                        <tr>
                            <td><?php echo esc_html( isset( $plan['key'] ) ? $plan['key'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $plan['name'] ) ? $plan['name'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $plan['points'] ) ? number_format_i18n( $plan['points'], 0 ) : '0' ); ?></td>
                            <td><?php echo esc_html( isset( $plan['price_label'] ) ? $plan['price_label'] : '' ); ?></td>
                            <td>
                                <?php
                                $product_id = isset( $plan['product_id'] ) ? (int) $plan['product_id'] : 0;
                                if ( $product_id > 0 ) {
                                    $product = get_post( $product_id );
                                    if ( $product ) {
                                        echo esc_html( $product->post_title );
                                        echo ' (ID: ' . esc_html( $product_id ) . ')';
                                    } else {
                                        echo esc_html__( 'Product not found', 'nehtw-gateway' );
                                    }
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo ! empty( $plan['highlight'] ) ? esc_html__( 'Yes', 'nehtw-gateway' ) : esc_html__( 'No', 'nehtw-gateway' ); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'nehtw_gateway_delete_plan', 'nehtw_gateway_nonce' ); ?>
                                    <input type="hidden" name="nehtw_gateway_action" value="delete_plan" />
                                    <input type="hidden" name="plan_key" value="<?php echo esc_attr( isset( $plan['key'] ) ? $plan['key'] : '' ); ?>" />
                                    <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'nehtw-gateway' ); ?>');"><?php esc_html_e( 'Delete', 'nehtw-gateway' ); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e( 'No plans defined yet.', 'nehtw-gateway' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render the User Subscriptions admin page.
 */
function nehtw_gateway_render_user_subscriptions_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
    }

    $success_message = '';
    $error_message   = '';
    $selected_user_id = 0;
    $selected_user   = null;

    // Handle user search
    if ( isset( $_GET['user_search'] ) ) {
        $search = sanitize_text_field( wp_unslash( $_GET['user_search'] ) );
        if ( is_numeric( $search ) ) {
            $selected_user = get_user_by( 'id', intval( $search ) );
        } else {
            $selected_user = get_user_by( 'email', $search );
            if ( ! $selected_user ) {
                $selected_user = get_user_by( 'login', $search );
            }
        }
        if ( $selected_user ) {
            $selected_user_id = $selected_user->ID;
        }
    }

    // Handle form submission
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['nehtw_gateway_action'] ) ) {
        $action = sanitize_key( wp_unslash( $_POST['nehtw_gateway_action'] ) );

        if ( 'save_subscription' === $action ) {
            check_admin_referer( 'nehtw_gateway_save_subscription', 'nehtw_gateway_nonce' );

            $user_id             = isset( $_POST['user_id'] ) ? intval( wp_unslash( $_POST['user_id'] ) ) : 0;
            $plan_key            = isset( $_POST['plan_key'] ) ? sanitize_key( wp_unslash( $_POST['plan_key'] ) ) : '';
            $points              = isset( $_POST['points_per_interval'] ) ? floatval( wp_unslash( $_POST['points_per_interval'] ) ) : 0;
            $status              = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'active';
            $next_renewal        = isset( $_POST['next_renewal_at'] ) ? sanitize_text_field( wp_unslash( $_POST['next_renewal_at'] ) ) : '';
            $subscription_id     = isset( $_POST['subscription_id'] ) ? intval( wp_unslash( $_POST['subscription_id'] ) ) : 0;

            if ( $user_id <= 0 || empty( $plan_key ) || $points <= 0 ) {
                $error_message = __( 'Please provide valid user, plan, and points.', 'nehtw-gateway' );
            } else {
                if ( empty( $next_renewal ) ) {
                    $next_renewal = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) );
                }

                $data = array(
                    'id'                 => $subscription_id,
                    'user_id'            => $user_id,
                    'plan_key'           => $plan_key,
                    'points_per_interval' => $points,
                    'interval'           => 'month',
                    'status'             => $status,
                    'next_renewal_at'    => $next_renewal,
                );

                $result = nehtw_gateway_save_subscription( $data );

                if ( $result ) {
                    $success_message = __( 'Subscription saved successfully.', 'nehtw-gateway' );
                    $selected_user_id = $user_id;
                    $selected_user   = get_user_by( 'id', $user_id );
                } else {
                    $error_message = __( 'Failed to save subscription.', 'nehtw-gateway' );
                }
            }
        }
    }

    $plans = nehtw_gateway_get_subscription_plans();
    $user_subscriptions = array();

    if ( $selected_user_id > 0 ) {
        $user_subscriptions = nehtw_gateway_get_user_subscriptions( $selected_user_id );
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'User Subscriptions', 'nehtw-gateway' ); ?></h1>

        <?php if ( $success_message ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $success_message ); ?></p></div>
        <?php endif; ?>

        <?php if ( $error_message ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error_message ); ?></p></div>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Search User', 'nehtw-gateway' ); ?></h2>
        <form method="get" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="nehtw-gateway-user-subscriptions" />
            <input type="text" name="user_search" placeholder="<?php esc_attr_e( 'User ID, email, or username', 'nehtw-gateway' ); ?>" value="<?php echo isset( $_GET['user_search'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['user_search'] ) ) ) : ''; ?>" />
            <button type="submit" class="button"><?php esc_html_e( 'Search', 'nehtw-gateway' ); ?></button>
        </form>

        <?php if ( $selected_user ) : ?>
            <h2><?php printf( esc_html__( 'Subscriptions for: %s (ID: %d)', 'nehtw-gateway' ), esc_html( $selected_user->display_name ), esc_html( $selected_user->ID ) ); ?></h2>

            <h3><?php esc_html_e( 'Add / Edit Subscription', 'nehtw-gateway' ); ?></h3>
            <form method="post" style="max-width: 600px;">
                <?php wp_nonce_field( 'nehtw_gateway_save_subscription', 'nehtw_gateway_nonce' ); ?>
                <input type="hidden" name="nehtw_gateway_action" value="save_subscription" />
                <input type="hidden" name="user_id" value="<?php echo esc_attr( $selected_user_id ); ?>" />
                <table class="form-table">
                    <tr>
                        <th><label for="subscription_id"><?php esc_html_e( 'Edit Subscription ID', 'nehtw-gateway' ); ?></label></th>
                        <td><input type="number" id="subscription_id" name="subscription_id" min="0" class="regular-text" placeholder="0 = New" /></td>
                    </tr>
                    <tr>
                        <th><label for="plan_key"><?php esc_html_e( 'Plan', 'nehtw-gateway' ); ?></label></th>
                        <td>
                            <select id="plan_key" name="plan_key" required class="regular-text">
                                <option value=""><?php esc_html_e( 'Select a plan', 'nehtw-gateway' ); ?></option>
                                <?php foreach ( $plans as $plan ) : ?>
                                    <option value="<?php echo esc_attr( isset( $plan['key'] ) ? $plan['key'] : '' ); ?>" data-points="<?php echo esc_attr( isset( $plan['points'] ) ? $plan['points'] : 0 ); ?>">
                                        <?php echo esc_html( isset( $plan['name'] ) ? $plan['name'] : '' ); ?> (<?php echo esc_html( isset( $plan['points'] ) ? number_format_i18n( $plan['points'], 0 ) : '0' ); ?> points)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="points_per_interval"><?php esc_html_e( 'Points per Interval', 'nehtw-gateway' ); ?></label></th>
                        <td><input type="number" id="points_per_interval" name="points_per_interval" required step="0.01" min="0" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label for="status"><?php esc_html_e( 'Status', 'nehtw-gateway' ); ?></label></th>
                        <td>
                            <select id="status" name="status" class="regular-text">
                                <option value="active"><?php esc_html_e( 'Active', 'nehtw-gateway' ); ?></option>
                                <option value="paused"><?php esc_html_e( 'Paused', 'nehtw-gateway' ); ?></option>
                                <option value="cancelled"><?php esc_html_e( 'Cancelled', 'nehtw-gateway' ); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="next_renewal_at"><?php esc_html_e( 'Next Renewal', 'nehtw-gateway' ); ?></label></th>
                        <td><input type="datetime-local" id="next_renewal_at" name="next_renewal_at" class="regular-text" /></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Subscription', 'nehtw-gateway' ); ?></button>
                </p>
            </form>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var planSelect = document.getElementById('plan_key');
                    var pointsInput = document.getElementById('points_per_interval');
                    if (planSelect && pointsInput) {
                        planSelect.addEventListener('change', function() {
                            var selected = planSelect.options[planSelect.selectedIndex];
                            if (selected && selected.dataset.points) {
                                pointsInput.value = selected.dataset.points;
                            }
                        });
                    }
                });
            </script>

            <h3><?php esc_html_e( 'Existing Subscriptions', 'nehtw-gateway' ); ?></h3>
            <?php if ( ! empty( $user_subscriptions ) ) : ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Plan', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Points', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Next Renewal', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Created', 'nehtw-gateway' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $user_subscriptions as $sub ) : ?>
                            <tr>
                                <td><?php echo esc_html( isset( $sub['id'] ) ? $sub['id'] : '' ); ?></td>
                                <td><?php echo esc_html( isset( $sub['plan_key'] ) ? $sub['plan_key'] : '' ); ?></td>
                                <td><?php echo esc_html( isset( $sub['points_per_interval'] ) ? number_format_i18n( $sub['points_per_interval'], 2 ) : '0' ); ?></td>
                                <td><?php echo esc_html( isset( $sub['status'] ) ? $sub['status'] : '' ); ?></td>
                                <td><?php echo esc_html( isset( $sub['next_renewal_at'] ) ? $sub['next_renewal_at'] : '' ); ?></td>
                                <td><?php echo esc_html( isset( $sub['created_at'] ) ? $sub['created_at'] : '' ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No subscriptions found for this user.', 'nehtw-gateway' ); ?></p>
            <?php endif; ?>
        <?php else : ?>
            <p><?php esc_html_e( 'Search for a user above to manage their subscriptions.', 'nehtw-gateway' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render the Wallet Top-ups admin page.
 */
function nehtw_gateway_render_wallet_topups_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
    }

    $success_message = '';
    $error_message   = '';

    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['nehtw_gateway_action'] ) ) {
        $action = sanitize_key( wp_unslash( $_POST['nehtw_gateway_action'] ) );

        if ( 'save_pack' === $action ) {
            check_admin_referer( 'nehtw_gateway_save_pack', 'nehtw_gateway_nonce' );

            $key        = isset( $_POST['pack_key'] ) ? sanitize_key( wp_unslash( $_POST['pack_key'] ) ) : '';
            $name       = isset( $_POST['pack_name'] ) ? sanitize_text_field( wp_unslash( $_POST['pack_name'] ) ) : '';
            $points     = isset( $_POST['pack_points'] ) ? floatval( wp_unslash( $_POST['pack_points'] ) ) : 0;
            $price      = isset( $_POST['pack_price'] ) ? sanitize_text_field( wp_unslash( $_POST['pack_price'] ) ) : '';
            $desc       = isset( $_POST['pack_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pack_description'] ) ) : '';
            $highlight  = isset( $_POST['pack_highlight'] ) ? true : false;
            $product_id = isset( $_POST['pack_product_id'] ) ? intval( wp_unslash( $_POST['pack_product_id'] ) ) : 0;

            if ( empty( $key ) || empty( $name ) || $points <= 0 ) {
                $error_message = __( 'Please provide key, name, and points.', 'nehtw-gateway' );
            } else {
                $packs = nehtw_gateway_get_wallet_topup_packs();
                $pack  = array(
                    'key'         => $key,
                    'name'        => $name,
                    'points'      => $points,
                    'price_label' => $price,
                    'description' => $desc,
                    'highlight'   => $highlight,
                    'product_id'  => $product_id > 0 ? $product_id : null,
                );

                // Update or add
                $found = false;
                foreach ( $packs as &$existing_pack ) {
                    if ( isset( $existing_pack['key'] ) && $existing_pack['key'] === $key ) {
                        $existing_pack = $pack;
                        $found         = true;
                        break;
                    }
                }

                if ( ! $found ) {
                    $packs[] = $pack;
                }

                nehtw_gateway_save_wallet_topup_packs( $packs );
                $success_message = __( 'Pack saved successfully.', 'nehtw-gateway' );
            }
        } elseif ( 'delete_pack' === $action ) {
            check_admin_referer( 'nehtw_gateway_delete_pack', 'nehtw_gateway_nonce' );

            $key = isset( $_POST['pack_key'] ) ? sanitize_key( wp_unslash( $_POST['pack_key'] ) ) : '';

            if ( ! empty( $key ) ) {
                $packs = nehtw_gateway_get_wallet_topup_packs();
                $packs = array_filter(
                    $packs,
                    function( $pack ) use ( $key ) {
                        return ! isset( $pack['key'] ) || $pack['key'] !== $key;
                    }
                );
                nehtw_gateway_save_wallet_topup_packs( array_values( $packs ) );
                $success_message = __( 'Pack deleted successfully.', 'nehtw-gateway' );
            }
        }
    }

    $packs = nehtw_gateway_get_wallet_topup_packs();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Wallet Top-ups', 'nehtw-gateway' ); ?></h1>

        <?php if ( $success_message ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $success_message ); ?></p></div>
        <?php endif; ?>

        <?php if ( $error_message ) : ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error_message ); ?></p></div>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Add / Edit Pack', 'nehtw-gateway' ); ?></h2>
        <form method="post" style="max-width: 600px;">
            <?php wp_nonce_field( 'nehtw_gateway_save_pack', 'nehtw_gateway_nonce' ); ?>
            <input type="hidden" name="nehtw_gateway_action" value="save_pack" />
            <?php
            // Get pack to edit if editing
            $editing_pack = null;
            $edit_key     = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
            if ( $edit_key ) {
                foreach ( $packs as $p ) {
                    if ( isset( $p['key'] ) && $p['key'] === $edit_key ) {
                        $editing_pack = $p;
                        break;
                    }
                }
            }
            ?>
            <table class="form-table">
                <tr>
                    <th><label for="pack_key"><?php esc_html_e( 'Pack Key', 'nehtw-gateway' ); ?></label></th>
                    <td><input type="text" id="pack_key" name="pack_key" required class="regular-text" placeholder="wallet_100" value="<?php echo $editing_pack ? esc_attr( $editing_pack['key'] ) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="pack_name"><?php esc_html_e( 'Pack Name', 'nehtw-gateway' ); ?></label></th>
                    <td><input type="text" id="pack_name" name="pack_name" required class="regular-text" placeholder="Starter" value="<?php echo $editing_pack ? esc_attr( $editing_pack['name'] ) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="pack_points"><?php esc_html_e( 'Points', 'nehtw-gateway' ); ?></label></th>
                    <td><input type="number" id="pack_points" name="pack_points" required step="0.01" min="0" class="regular-text" placeholder="100" value="<?php echo $editing_pack ? esc_attr( $editing_pack['points'] ) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="pack_price"><?php esc_html_e( 'Price Label', 'nehtw-gateway' ); ?></label></th>
                    <td><input type="text" id="pack_price" name="pack_price" class="regular-text" placeholder="EGP 99" value="<?php echo $editing_pack ? esc_attr( $editing_pack['price_label'] ) : ''; ?>" /></td>
                </tr>
                <tr>
                    <th><label for="pack_description"><?php esc_html_e( 'Description', 'nehtw-gateway' ); ?></label></th>
                    <td><textarea id="pack_description" name="pack_description" class="large-text" rows="2"><?php echo $editing_pack ? esc_textarea( $editing_pack['description'] ) : ''; ?></textarea></td>
                </tr>
                <tr>
                    <th><label for="pack_highlight"><?php esc_html_e( 'Highlight (Best Value)', 'nehtw-gateway' ); ?></label></th>
                    <td><input type="checkbox" id="pack_highlight" name="pack_highlight" <?php echo $editing_pack && ! empty( $editing_pack['highlight'] ) ? 'checked' : ''; ?> /></td>
                </tr>
                <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                <tr>
                    <th><label for="pack_product_id"><?php esc_html_e( 'WooCommerce Product', 'nehtw-gateway' ); ?></label></th>
                    <td>
                        <select id="pack_product_id" name="pack_product_id" class="regular-text">
                            <option value="0"><?php esc_html_e( '— None —', 'nehtw-gateway' ); ?></option>
                            <?php
                            $args = array(
                                'post_type'      => 'product',
                                'post_status'    => 'publish',
                                'posts_per_page' => -1,
                                'orderby'        => 'title',
                                'order'          => 'ASC',
                            );
                            $products = get_posts( $args );
                            $editing_product_id = $editing_pack && isset( $editing_pack['product_id'] ) ? (int) $editing_pack['product_id'] : 0;
                            foreach ( $products as $product ) :
                                $selected = $editing_product_id === $product->ID ? 'selected' : '';
                                ?>
                                <option value="<?php echo esc_attr( $product->ID ); ?>" <?php echo $selected; ?>>
                                    <?php echo esc_html( $product->post_title ); ?> (ID: <?php echo esc_html( $product->ID ); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select the WooCommerce product for this top-up pack.', 'nehtw-gateway' ); ?></p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Save Pack', 'nehtw-gateway' ); ?></button>
            </p>
        </form>

        <h2><?php esc_html_e( 'Existing Packs', 'nehtw-gateway' ); ?></h2>
        <?php if ( ! empty( $packs ) ) : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Key', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Points', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Price', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'WooCommerce Product', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Highlight', 'nehtw-gateway' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nehtw-gateway' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $packs as $pack ) : ?>
                        <tr>
                            <td><?php echo esc_html( isset( $pack['key'] ) ? $pack['key'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $pack['name'] ) ? $pack['name'] : '' ); ?></td>
                            <td><?php echo esc_html( isset( $pack['points'] ) ? number_format_i18n( $pack['points'], 0 ) : '0' ); ?></td>
                            <td><?php echo esc_html( isset( $pack['price_label'] ) ? $pack['price_label'] : '' ); ?></td>
                            <td>
                                <?php
                                $product_id = isset( $pack['product_id'] ) ? (int) $pack['product_id'] : 0;
                                if ( $product_id > 0 ) {
                                    $product = get_post( $product_id );
                                    if ( $product ) {
                                        echo esc_html( $product->post_title );
                                        echo ' (ID: ' . esc_html( $product_id ) . ')';
                                    } else {
                                        echo esc_html__( 'Product not found', 'nehtw-gateway' );
                                    }
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td><?php echo ! empty( $pack['highlight'] ) ? esc_html__( 'Yes', 'nehtw-gateway' ) : esc_html__( 'No', 'nehtw-gateway' ); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <?php wp_nonce_field( 'nehtw_gateway_delete_pack', 'nehtw_gateway_nonce' ); ?>
                                    <input type="hidden" name="nehtw_gateway_action" value="delete_pack" />
                                    <input type="hidden" name="pack_key" value="<?php echo esc_attr( isset( $pack['key'] ) ? $pack['key'] : '' ); ?>" />
                                    <button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Are you sure?', 'nehtw-gateway' ); ?>');"><?php esc_html_e( 'Delete', 'nehtw-gateway' ); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e( 'No packs defined yet.', 'nehtw-gateway' ); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

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

    $api_settings_notice       = '';
    $api_settings_notice_class = 'notice-success';
    $has_api_key               = '' !== nehtw_gateway_get_api_key();

    if ( isset( $_POST['nehtw_gateway_action'] ) && 'save_api_key' === $_POST['nehtw_gateway_action'] ) {
        check_admin_referer( 'nehtw_gateway_save_api_key', 'nehtw_gateway_api_nonce' );

        $raw_key   = isset( $_POST['nehtw_gateway_api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['nehtw_gateway_api_key'] ) ) : '';
        $clean_key = trim( $raw_key );

        if ( '' === $clean_key ) {
            delete_option( NEHTW_GATEWAY_OPTION_API_KEY );
            $api_settings_notice       = __( 'Nehtw API key removed. Remote requests will be disabled until you add a key.', 'nehtw-gateway' );
            $api_settings_notice_class = 'notice-warning';
        } else {
            update_option( NEHTW_GATEWAY_OPTION_API_KEY, $clean_key );
            $api_settings_notice       = __( 'Nehtw API key updated successfully.', 'nehtw-gateway' );
            $api_settings_notice_class = 'notice-success';
        }

        $has_api_key = '' !== nehtw_gateway_get_api_key();
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

        <?php if ( ! empty( $api_settings_notice ) ) : ?>
            <div class="notice <?php echo esc_attr( $api_settings_notice_class ); ?> is-dismissible">
                <p><?php echo esc_html( $api_settings_notice ); ?></p>
            </div>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Nehtw API Settings', 'nehtw-gateway' ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'nehtw_gateway_save_api_key', 'nehtw_gateway_api_nonce' ); ?>
            <input type="hidden" name="nehtw_gateway_action" value="save_api_key" />
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nehtw_gateway_api_key"><?php esc_html_e( 'API Key', 'nehtw-gateway' ); ?></label></th>
                    <td>
                        <input type="password" class="regular-text" id="nehtw_gateway_api_key" name="nehtw_gateway_api_key" autocomplete="new-password" placeholder="<?php esc_attr_e( 'Enter your Nehtw API key', 'nehtw-gateway' ); ?>" />
                        <?php if ( $has_api_key ) : ?>
                            <p class="description"><?php esc_html_e( 'A key is currently saved. Saving a new value will replace it. Leave the field blank to remove the existing key.', 'nehtw-gateway' ); ?></p>
                        <?php else : ?>
                            <p class="description"><?php esc_html_e( 'Paste the Nehtw API key provided to you. It will be stored securely and not displayed in the admin again.', 'nehtw-gateway' ); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save API Key', 'nehtw-gateway' ) ); ?>
        </form>

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

/**
 * Schedule subscriptions cron event.
 */
function nehtw_gateway_schedule_subscriptions_cron() {
    if ( ! wp_next_scheduled( 'nehtw_gateway_run_subscriptions_cron' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'nehtw_gateway_run_subscriptions_cron' );
    }
}
add_action( 'wp', 'nehtw_gateway_schedule_subscriptions_cron' );

/**
 * Handle subscription renewals and reminders via cron.
 */
add_action( 'nehtw_gateway_run_subscriptions_cron', 'nehtw_gateway_handle_subscriptions_cron' );
function nehtw_gateway_handle_subscriptions_cron() {
    global $wpdb;

    $table = nehtw_gateway_get_subscriptions_table();
    if ( ! $table ) {
        return;
    }

    $now = gmdate( 'Y-m-d H:i:s' );

    // 1) Handle reminders (3 days & 1 day before) - using templated email system
    if ( function_exists( 'nehtw_gateway_process_subscription_reminders' ) ) {
        nehtw_gateway_process_subscription_reminders();
    }

    // 2) Handle due renewals
    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE status = %s AND next_renewal_at <= %s",
        'active',
        $now
    );

    $subs = $wpdb->get_results( $sql, ARRAY_A );

    if ( empty( $subs ) ) {
        return;
    }

    foreach ( $subs as $sub ) {
        $user_id = (int) $sub['user_id'];
        $points  = isset( $sub['points_per_interval'] ) ? (float) $sub['points_per_interval'] : 0.0;

        if ( $user_id <= 0 || $points <= 0 ) {
            continue;
        }

        // Add points to local wallet using existing helper
        if ( function_exists( 'nehtw_gateway_add_transaction' ) ) {
            nehtw_gateway_add_transaction(
                $user_id,
                'subscription_renewal',
                $points,
                array(
                    'meta' => array(
                        'source'  => 'subscription_auto_topup',
                        'plan_key' => $sub['plan_key'],
                        'note'    => sprintf( 'Auto top-up from plan %s', $sub['plan_key'] ),
                    ),
                )
            );
        }

        // Optional: also sync to Nehtw using /api/sendpoint
        if ( function_exists( 'nehtw_gateway_send_points_to_nehtw' ) ) {
            nehtw_gateway_send_points_to_nehtw( $user_id, $points, $sub );
        }

        // Update next_renewal_at (+1 month for now)
        $next_ts = strtotime( $sub['next_renewal_at'] );
        if ( $next_ts ) {
            $next = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month', $next_ts ) );
        } else {
            $next = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) );
        }

        $wpdb->update(
            $table,
            array(
                'next_renewal_at' => $next,
                'updated_at'      => $now,
            ),
            array( 'id' => $sub['id'] ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }
}

/**
 * Send subscription reminder emails.
 *
 * @param string $table Table name
 * @param string $now   Current datetime
 */
function nehtw_gateway_send_subscription_reminders( $table, $now ) {
    global $wpdb;

    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE status = %s",
        'active'
    );

    $subs = $wpdb->get_results( $sql, ARRAY_A );

    if ( empty( $subs ) ) {
        return;
    }

    foreach ( $subs as $sub ) {
        $user_id         = (int) $sub['user_id'];
        $next_renewal_at = isset( $sub['next_renewal_at'] ) ? $sub['next_renewal_at'] : '';

        if ( ! $next_renewal_at || $user_id <= 0 ) {
            continue;
        }

        $meta = array();
        if ( ! empty( $sub['meta'] ) ) {
            $decoded = json_decode( $sub['meta'], true );
            if ( is_array( $decoded ) ) {
                $meta = $decoded;
            }
        }

        $next_ts = strtotime( $next_renewal_at );
        if ( ! $next_ts ) {
            continue;
        }

        $now_ts = strtotime( $now );
        $diff   = $next_ts - $now_ts;
        $days   = floor( $diff / DAY_IN_SECONDS );

        $existing = isset( $meta['reminders_sent'] ) && is_array( $meta['reminders_sent'] )
            ? $meta['reminders_sent']
            : array();

        $should_update = false;

        if ( $days === 3 && empty( $existing['3d'] ) ) {
            nehtw_gateway_send_subscription_email( $user_id, $sub, '3d' );
            $existing['3d'] = true;
            $should_update  = true;
        } elseif ( $days === 1 && empty( $existing['1d'] ) ) {
            nehtw_gateway_send_subscription_email( $user_id, $sub, '1d' );
            $existing['1d'] = true;
            $should_update  = true;
        }

        if ( $should_update ) {
            $meta['reminders_sent'] = $existing;

            // Save meta back
            $wpdb->update(
                $table,
                array(
                    'meta'       => wp_json_encode( $meta ),
                    'updated_at' => $now,
                ),
                array( 'id' => $sub['id'] ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        }
    }
}

/**
 * Send reminder email.
 *
 * @param int    $user_id User ID
 * @param array  $sub     Subscription data
 * @param string $when    '3d' or '1d'
 */
function nehtw_gateway_send_subscription_email( $user_id, $sub, $when ) {
    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return;
    }

    $to   = $user->user_email;
    $plan = isset( $sub['plan_key'] ) ? $sub['plan_key'] : '';

    $subject = '';
    if ( '3d' === $when ) {
        $subject = sprintf( __( 'Your %s plan will renew in 3 days', 'nehtw-gateway' ), $plan );
    } else {
        $subject = sprintf( __( 'Your %s plan renews tomorrow', 'nehtw-gateway' ), $plan );
    }

    $next_renewal = isset( $sub['next_renewal_at'] ) ? $sub['next_renewal_at'] : '';
    $points       = isset( $sub['points_per_interval'] ) ? (float) $sub['points_per_interval'] : 0.0;

    $message = sprintf(
        "Hi %s,\n\nYour subscription plan (%s) is scheduled to renew on %s.\nYou will receive %.2f points as part of this renewal.\n\nIf you no longer want to renew, you can contact support or update your plan.\n\nThanks,\nArtly",
        $user->display_name,
        $plan,
        $next_renewal,
        $points
    );

    wp_mail( $to, $subject, $message );
}

/**
 * Optional helper for syncing points to Nehtw via /api/sendpoint.
 *
 * @param int    $user_id User ID
 * @param float  $points  Points to send
 * @param array  $sub     Subscription data
 */
function nehtw_gateway_send_points_to_nehtw( $user_id, $points, $sub ) {
    // This is optional - only implement if you have Nehtw username stored for users
    // For now, just a placeholder that logs errors but doesn't block local wallet top-up

    $nehtw_username = get_user_meta( $user_id, 'nehtw_username', true );

    if ( empty( $nehtw_username ) ) {
        // No Nehtw username stored, skip sync
        return;
    }

    $key_check = nehtw_gateway_require_api_key();
    if ( is_wp_error( $key_check ) ) {
        return;
    }

    $api_key = nehtw_gateway_get_api_key();

    $url = add_query_arg(
        array(
            'apikey'   => $api_key,
            'receiver' => $nehtw_username,
            'amount'   => $points,
        ),
        NEHTW_GATEWAY_API_BASE . '/api/sendpoint'
    );

    $response = wp_remote_get(
        $url,
        array(
            'timeout' => 10,
            'headers' => nehtw_gateway_build_api_headers(),
        )
    );

    if ( is_wp_error( $response ) ) {
        // Log error but don't block local wallet top-up
        error_log( 'Nehtw sync failed for user ' . $user_id . ': ' . $response->get_error_message() );
        return;
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code < 200 || $code >= 300 ) {
        error_log( 'Nehtw sync failed for user ' . $user_id . ': HTTP ' . $code );
    }
}

/**
 * When a WooCommerce order is completed, assign subscription(s) based on products.
 *
 * This function MUST NOT output anything to the front-end.
 * It only updates internal subscription tables.
 *
 * @param int $order_id Order ID
 */
function nehtw_gateway_handle_order_completed_for_subscriptions( $order_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $user_id = $order->get_user_id();
    if ( ! $user_id ) {
        return;
    }

    // Load plans from option
    if ( ! function_exists( 'nehtw_gateway_get_subscription_plans' ) ) {
        return;
    }

    $plans = nehtw_gateway_get_subscription_plans();
    if ( empty( $plans ) ) {
        return;
    }

    // Map product_id => plan data for quick lookup
    $product_map = array();
    foreach ( $plans as $plan ) {
        if ( empty( $plan['product_id'] ) ) {
            continue;
        }

        $product_id = (int) $plan['product_id'];
        if ( $product_id > 0 ) {
            $product_map[ $product_id ] = $plan;
        }
    }

    if ( empty( $product_map ) ) {
        return;
    }

    // Check line items
    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        if ( ! $product_id || ! isset( $product_map[ $product_id ] ) ) {
            continue;
        }

        $plan = $product_map[ $product_id ];

        $points = isset( $plan['points'] ) ? (float) $plan['points'] : 0.0;
        if ( $points <= 0 ) {
            continue;
        }

        // Calculate next renewal (1 month from now for now)
        $now      = current_time( 'mysql', true ); // GMT
        $interval = 'month';
        $next     = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month', strtotime( $now ) ) );

        if ( function_exists( 'nehtw_gateway_save_subscription' ) ) {
            nehtw_gateway_save_subscription(
                array(
                    'user_id'             => $user_id,
                    'plan_key'            => isset( $plan['key'] ) ? $plan['key'] : '',
                    'points_per_interval' => $points,
                    'interval'           => $interval,
                    'status'             => 'active',
                    'next_renewal_at'    => $next,
                    'meta'               => array(
                        // INTERNAL META (never surfaced on front)
                        'product_id' => $product_id,
                        'order_id'   => $order_id,
                    ),
                )
            );
        }

        // Optional: immediately credit the first interval of points now
        if ( function_exists( 'nehtw_gateway_add_transaction' ) ) {
            nehtw_gateway_add_transaction(
                $user_id,
                'subscription_initial',
                $points,
                array(
                    'meta' => array(
                        'source'   => 'subscription_initial_payment',
                        'plan_key' => isset( $plan['key'] ) ? $plan['key'] : '',
                        'order_id' => $order_id,
                    ),
                )
            );
        }
    }
}
add_action( 'woocommerce_order_status_completed', 'nehtw_gateway_handle_order_completed_for_subscriptions', 20, 1 );

/**
 * Handle WooCommerce Subscriptions payment renewals.
 *
 * @param WC_Order $order Order object
 */
function nehtw_gateway_handle_subscription_renewal_order( $order ) {
    if ( ! $order instanceof WC_Order ) {
        return;
    }

    $user_id = $order->get_user_id();
    if ( ! $user_id ) {
        return;
    }

    if ( ! function_exists( 'nehtw_gateway_get_subscription_plans' ) || ! function_exists( 'nehtw_gateway_add_transaction' ) ) {
        return;
    }

    $plans = nehtw_gateway_get_subscription_plans();
    if ( empty( $plans ) ) {
        return;
    }

    // Map product_id => plan
    $product_map = array();
    foreach ( $plans as $plan ) {
        if ( empty( $plan['product_id'] ) ) {
            continue;
        }

        $product_id = (int) $plan['product_id'];
        if ( $product_id > 0 ) {
            $product_map[ $product_id ] = $plan;
        }
    }

    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        if ( ! $product_id || ! isset( $product_map[ $product_id ] ) ) {
            continue;
        }

        $plan   = $product_map[ $product_id ];
        $points = isset( $plan['points'] ) ? (float) $plan['points'] : 0.0;

        if ( $points <= 0 ) {
            continue;
        }

        // Credit points for this renewal
        nehtw_gateway_add_transaction(
            $user_id,
            'subscription_renewal',
            $points,
            array(
                'meta' => array(
                    'source'   => 'subscription_renewal_payment',
                    'plan_key' => isset( $plan['key'] ) ? $plan['key'] : '',
                    'order_id' => $order->get_id(),
                ),
            )
        );

        // Optionally update "next_renewal_at" in the internal subscriptions table,
        // but the cron may already be doing that. Keep behavior consistent with existing cron.
    }
}

// Hook into WooCommerce Subscriptions if available
if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
    add_action( 'woocommerce_subscription_payment_complete', 'nehtw_gateway_handle_subscription_renewal_order', 10, 1 );
}

/**
 * Handle WooCommerce order completion for wallet top-up packs.
 *
 * This is INTERNAL ONLY: no front-end output.
 *
 * @param int $order_id Order ID
 */
function nehtw_gateway_handle_wallet_topup_order_completed( $order_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return;
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return;
    }

    $user_id = $order->get_user_id();
    if ( ! $user_id ) {
        return;
    }

    if ( ! function_exists( 'nehtw_gateway_get_wallet_topup_packs' ) || ! function_exists( 'nehtw_gateway_add_transaction' ) ) {
        return;
    }

    $packs = nehtw_gateway_get_wallet_topup_packs();
    if ( empty( $packs ) ) {
        return;
    }

    // Map product_id => points
    $product_map = array();
    foreach ( $packs as $pack ) {
        if ( empty( $pack['product_id'] ) ) {
            continue;
        }

        $product_id = (int) $pack['product_id'];
        if ( $product_id <= 0 ) {
            continue;
        }

        $points = isset( $pack['points'] ) ? (float) $pack['points'] : 0.0;
        if ( $points <= 0 ) {
            continue;
        }

        $product_map[ $product_id ] = array(
            'points' => $points,
            'key'    => isset( $pack['key'] ) ? $pack['key'] : '',
            'name'   => isset( $pack['name'] ) ? $pack['name'] : '',
        );
    }

    if ( empty( $product_map ) ) {
        return;
    }

    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        if ( ! $product_id || ! isset( $product_map[ $product_id ] ) ) {
            continue;
        }

        $pack = $product_map[ $product_id ];

        // Credit wallet points
        nehtw_gateway_add_transaction(
            $user_id,
            'wallet_topup',
            $pack['points'],
            array(
                'meta' => array(
                    'source'    => 'wallet_topup_purchase',
                    'pack_key'  => $pack['key'],
                    'pack_name' => $pack['name'],
                    'order_id'  => $order_id,
                ),
            )
        );
    }
}
add_action( 'woocommerce_order_status_completed', 'nehtw_gateway_handle_wallet_topup_order_completed', 15, 1 );

function nehtw_gateway_init() {
    // Future: Additional initialization
}
add_action( 'plugins_loaded', 'nehtw_gateway_init' );
/**
 * Shortcode: [nehtw_gateway_my_downloads]
 * Renders the React dashboard container.
 */