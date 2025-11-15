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

require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-activator.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-maint-scheduler.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-sites.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-audit-log.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-site-notifier.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/admin/bootstrap-seeder.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/admin/class-nehtw-admin-sites.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/rest/class-nehtw-rest-sites.php';

require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-stock-orders.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-download-history.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-subscriptions.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-wallet-topups.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-email-templates.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-stock.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-webhooks.php';

// Admin Dashboard Components
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/database/class-nehtw-database.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/database/class-nehtw-order-sync.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/cron/class-nehtw-order-poller.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/admin/class-nehtw-analytics-engine.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/admin/class-nehtw-admin-rest-api.php';

// Balance System Components
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/balance/class-nehtw-balance-database.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/balance/class-nehtw-transaction-manager.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/balance/class-nehtw-balance-api.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/balance/class-nehtw-refund-processor.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/balance/class-nehtw-balance-sync.php';

// Billing System Components
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/billing/class-nehtw-invoice-manager.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/billing/class-nehtw-payment-retry.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/billing/class-nehtw-dunning-manager.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/billing/class-nehtw-subscription-manager.php';
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/billing/class-nehtw-usage-tracker.php';

// Billing REST API
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/admin/class-nehtw-subscription-rest-api.php';

// Billing Cron Jobs
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/cron/class-nehtw-billing-cron.php';

// Payment Gateway Settings
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/billing/class-nehtw-payment-gateway-settings.php';

// Dunning Manager Admin Page
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/admin/class-nehtw-dunning-admin.php';

// WooCommerce integration for wallet top-ups
require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/class-nehtw-woo-points.php';

add_action(
    'plugins_loaded',
    function () {
        if ( class_exists( 'Nehtw_Gateway_Woo_Points' ) ) {
            Nehtw_Gateway_Woo_Points::init();
        }
    }
);

register_activation_hook( NEHTW_GATEWAY_PLUGIN_FILE, array( 'Nehtw_Activator', 'activate' ) );

function nehtw_gateway_get_advanced_controls_defaults() {
    return array(
        'enable_scheduled_maintenance' => false,
        'enable_notify_back_online'    => false,
        'enable_audit_log'             => false,
        'provider_list_chips_public'   => false,
    );
}

function nehtw_gateway_get_advanced_controls() {
    $saved = get_option( 'nehtw_gateway_advanced_controls', array() );
    if ( ! is_array( $saved ) ) {
        $saved = array();
    }
    return wp_parse_args( $saved, nehtw_gateway_get_advanced_controls_defaults() );
}

function nehtw_gateway_update_advanced_controls( $data ) {
    $defaults = nehtw_gateway_get_advanced_controls_defaults();
    $clean    = array();
    foreach ( $defaults as $key => $default ) {
        $clean[ $key ] = ! empty( $data[ $key ] ) ? 1 : 0;
    }
    update_option( 'nehtw_gateway_advanced_controls', $clean );
}

function nehtw_gateway_is_control_enabled( $key ) {
    $controls = nehtw_gateway_get_advanced_controls();
    return ! empty( $controls[ $key ] );
}

function nehtw_gateway_ensure_manage_cap() {
    $role = get_role( 'administrator' );
    if ( $role && ! $role->has_cap( 'manage_nehtw' ) ) {
        $role->add_cap( 'manage_nehtw' );
    }
}
add_action( 'admin_init', 'nehtw_gateway_ensure_manage_cap' );

function nehtw_gateway_enqueue_site_controls_assets() {
    if ( is_admin() ) {
        return;
    }

    wp_register_style(
        'nehtw-site-controls',
        NEHTW_GATEWAY_PLUGIN_URL . 'assets/css/nehtw-site-controls.css',
        array(),
        NEHTW_GATEWAY_VERSION
    );

    wp_register_script(
        'nehtw-stock-input-guard',
        NEHTW_GATEWAY_PLUGIN_URL . 'assets/js/stock-input-guard.js',
        array(),
        NEHTW_GATEWAY_VERSION,
        true
    );

    $strings = array(
        'unsupported' => __( 'Unsupported provider', 'nehtw-gateway' ),
        'maintenance' => __( 'Maintenance', 'nehtw-gateway' ),
        'offline'     => __( 'Offline', 'nehtw-gateway' ),
        'active'      => __( 'Active', 'nehtw-gateway' ),
        'unavailable' => __( 'is not available right now.', 'nehtw-gateway' ),
        'points'      => __( '%d points per file', 'nehtw-gateway' ),
        'error'       => __( 'Something went wrong.', 'nehtw-gateway' ),
        'notifyLabel' => __( 'Email me when %s is back online', 'nehtw-gateway' ),
        'notifyEmail' => __( 'your@email.com', 'nehtw-gateway' ),
        'notifyCta'   => __( 'Notify me', 'nehtw-gateway' ),
        'notifyThanks'=> __( 'We will email you once it is active.', 'nehtw-gateway' ),
        'tooltip'     => __( 'Temporarily unavailable; we will be back soon.', 'nehtw-gateway' ),
    );

    wp_localize_script(
        'nehtw-stock-input-guard',
        'nehtwSiteControls',
        array(
            'root'          => esc_url_raw( rest_url() ),
            'notifyEnabled' => nehtw_gateway_is_control_enabled( 'enable_notify_back_online' ),
            'providerChips' => nehtw_gateway_is_control_enabled( 'provider_list_chips_public' ),
            'l10n'          => $strings,
        )
    );

    wp_enqueue_style( 'nehtw-site-controls' );
    wp_enqueue_script( 'nehtw-stock-input-guard' );
}
add_action( 'wp_enqueue_scripts', 'nehtw_gateway_enqueue_site_controls_assets' );

add_action( 'init', function() {
    if ( nehtw_gateway_is_control_enabled( 'enable_scheduled_maintenance' ) ) {
        Nehtw_Maint_Scheduler::schedule_event();
    }
} );

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
    
    // ========== BILLING SYSTEM TABLES ==========
    
    // Invoices Table
    $table_invoices = $wpdb->prefix . 'nehtw_invoices';
    $sql_invoices = "CREATE TABLE {$table_invoices} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        subscription_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        invoice_number VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        total_amount DECIMAL(10,2) NOT NULL,
        currency VARCHAR(10) NOT NULL DEFAULT 'USD',
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        billing_period_start DATETIME NOT NULL,
        billing_period_end DATETIME NOT NULL,
        due_date DATETIME NOT NULL,
        paid_at DATETIME NULL,
        payment_method VARCHAR(50) NULL,
        payment_gateway VARCHAR(50) NULL,
        gateway_transaction_id VARCHAR(191) NULL,
        pdf_path TEXT NULL,
        notes TEXT NULL,
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY invoice_number (invoice_number),
        KEY subscription_id (subscription_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY due_date (due_date),
        KEY paid_at (paid_at)
    ) {$charset_collate};";
    dbDelta( $sql_invoices );
    
    // Payment Attempts Table
    $table_payment_attempts = $wpdb->prefix . 'nehtw_payment_attempts';
    $sql_payment_attempts = "CREATE TABLE {$table_payment_attempts} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        invoice_id BIGINT(20) UNSIGNED NOT NULL,
        subscription_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        attempt_number INT NOT NULL DEFAULT 1,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) NOT NULL,
        error_code VARCHAR(50) NULL,
        error_message TEXT NULL,
        gateway_response LONGTEXT NULL,
        attempted_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY invoice_id (invoice_id),
        KEY subscription_id (subscription_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY attempted_at (attempted_at)
    ) {$charset_collate};";
    dbDelta( $sql_payment_attempts );
    
    // Subscription History Table
    $table_subscription_history = $wpdb->prefix . 'nehtw_subscription_history';
    $sql_subscription_history = "CREATE TABLE {$table_subscription_history} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        subscription_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        action VARCHAR(50) NOT NULL,
        old_status VARCHAR(20) NULL,
        new_status VARCHAR(20) NULL,
        old_plan_key VARCHAR(64) NULL,
        new_plan_key VARCHAR(64) NULL,
        amount_change DECIMAL(10,2) NULL,
        note TEXT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        meta LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        created_by BIGINT(20) UNSIGNED NULL,
        PRIMARY KEY  (id),
        KEY subscription_id (subscription_id),
        KEY user_id (user_id),
        KEY action (action),
        KEY created_at (created_at)
    ) {$charset_collate};";
    dbDelta( $sql_subscription_history );
    
    // Dunning Emails Table
    $table_dunning_emails = $wpdb->prefix . 'nehtw_dunning_emails';
    $sql_dunning_emails = "CREATE TABLE {$table_dunning_emails} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        subscription_id BIGINT(20) UNSIGNED NOT NULL,
        invoice_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        dunning_level INT NOT NULL,
        email_type VARCHAR(50) NOT NULL,
        sent_at DATETIME NOT NULL,
        opened_at DATETIME NULL,
        clicked_at DATETIME NULL,
        converted_at DATETIME NULL,
        PRIMARY KEY  (id),
        KEY subscription_id (subscription_id),
        KEY invoice_id (invoice_id),
        KEY user_id (user_id),
        KEY sent_at (sent_at)
    ) {$charset_collate};";
    dbDelta( $sql_dunning_emails );
    
    // Usage Tracking Table
    $table_usage_tracking = $wpdb->prefix . 'nehtw_usage_tracking';
    $sql_usage_tracking = "CREATE TABLE {$table_usage_tracking} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        subscription_id BIGINT(20) UNSIGNED NOT NULL,
        usage_type VARCHAR(50) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        unit VARCHAR(20) NOT NULL DEFAULT 'points',
        recorded_at DATETIME NOT NULL,
        billing_period_start DATETIME NOT NULL,
        billing_period_end DATETIME NOT NULL,
        meta LONGTEXT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY subscription_id (subscription_id),
        KEY usage_type (usage_type),
        KEY billing_period_start (billing_period_start),
        KEY billing_period_end (billing_period_end),
        KEY recorded_at (recorded_at)
    ) {$charset_collate};";
    dbDelta( $sql_usage_tracking );
    
    // Payment Retries Table (for retry scheduling)
    $table_payment_retries = $wpdb->prefix . 'nehtw_payment_retries';
    $sql_payment_retries = "CREATE TABLE {$table_payment_retries} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        invoice_id BIGINT(20) UNSIGNED NOT NULL,
        subscription_id BIGINT(20) UNSIGNED NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        attempt_number INT NOT NULL DEFAULT 1,
        scheduled_at DATETIME NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'scheduled',
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY invoice_id (invoice_id),
        KEY subscription_id (subscription_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY scheduled_at (scheduled_at)
    ) {$charset_collate};";
    dbDelta( $sql_payment_retries );
    
    // Update Subscriptions Table - Add new columns
    $subscription_columns = $wpdb->get_col( "DESC {$table_subscriptions}", 0 );
    
    $columns_to_add = [
        'wc_subscription_id' => "ADD COLUMN wc_subscription_id BIGINT(20) NULL AFTER id",
        'payment_method' => "ADD COLUMN payment_method VARCHAR(50) NULL AFTER `interval`",
        'failed_payment_count' => "ADD COLUMN failed_payment_count INT NOT NULL DEFAULT 0 AFTER status",
        'last_payment_attempt' => "ADD COLUMN last_payment_attempt DATETIME NULL AFTER failed_payment_count",
        'dunning_level' => "ADD COLUMN dunning_level INT NOT NULL DEFAULT 0 AFTER last_payment_attempt",
        'paused_at' => "ADD COLUMN paused_at DATETIME NULL AFTER dunning_level",
        'cancelled_at' => "ADD COLUMN cancelled_at DATETIME NULL AFTER paused_at",
        'trial_end_at' => "ADD COLUMN trial_end_at DATETIME NULL AFTER next_renewal_at",
        'usage_data' => "ADD COLUMN usage_data LONGTEXT NULL AFTER meta"
    ];
    
    foreach ( $columns_to_add as $column_name => $sql_fragment ) {
        if ( ! in_array( $column_name, $subscription_columns, true ) ) {
            $wpdb->query( "ALTER TABLE {$table_subscriptions} {$sql_fragment}" );
        }
    }
    
    // Initialize webhook secret if not already set
    $webhook_secret = get_option( 'nehtw_webhook_secret' );
    if ( ! $webhook_secret ) {
        $webhook_secret = wp_generate_password( 32, false );
        update_option( 'nehtw_webhook_secret', $webhook_secret );
    }
    
    // Add new columns if they don't exist
    if ( $needs_preview_thumb ) {
        $wpdb->query( "ALTER TABLE {$table_stock} ADD COLUMN preview_thumb TEXT NULL AFTER source_url" );
    }
    if ( $needs_provider_label ) {
        $wpdb->query( "ALTER TABLE {$table_stock} ADD COLUMN provider_label VARCHAR(100) NULL AFTER site" );
    }
    
    // Create admin dashboard database tables
    if ( class_exists( 'Nehtw_Database' ) ) {
        Nehtw_Database::create_tables();
    }
    
    // Create balance system database tables
    if ( class_exists( 'Nehtw_Balance_Database' ) ) {
        Nehtw_Balance_Database::create_tables();
    }
    
    // Schedule cron jobs for order polling
    if ( class_exists( 'Nehtw_Order_Poller' ) ) {
        Nehtw_Order_Poller::schedule_events();
    }
    
    // Schedule balance sync
    if ( class_exists( 'Nehtw_Balance_Sync' ) ) {
        Nehtw_Balance_Sync::schedule_events();
    }
    
    // Schedule billing system cron jobs
    if ( class_exists( 'Nehtw_Billing_Cron' ) ) {
        $billing_cron = new Nehtw_Billing_Cron();
        $billing_cron->schedule_events();
    }
    
    // Schedule daily analytics aggregation
    if ( ! wp_next_scheduled( 'nehtw_daily_aggregation' ) ) {
        wp_schedule_event( strtotime( 'tomorrow 1:00am' ), 'daily', 'nehtw_daily_aggregation' );
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin Deactivation
 */
function nehtw_gateway_deactivate() {
    // Clear scheduled events
    if ( class_exists( 'Nehtw_Order_Poller' ) ) {
        Nehtw_Order_Poller::clear_scheduled_events();
    }
    if ( class_exists( 'Nehtw_Balance_Sync' ) ) {
        Nehtw_Balance_Sync::clear_events();
    }
    if ( class_exists( 'Nehtw_Billing_Cron' ) ) {
        $billing_cron = new Nehtw_Billing_Cron();
        $billing_cron->clear_events();
    }
    wp_clear_scheduled_hook( 'nehtw_daily_aggregation');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( NEHTW_GATEWAY_PLUGIN_FILE, 'nehtw_gateway_deactivate' );

/**
 * Enqueue subscription dashboard assets
 */
function nehtw_enqueue_subscription_dashboard() {
    // Only enqueue on subscription management pages
    if ( ! is_account_page() && ! is_page( 'subscription' ) ) {
        return;
    }
    
    wp_enqueue_script(
        'nehtw-subscription-dashboard',
        NEHTW_GATEWAY_PLUGIN_URL . 'assets/js/subscription-dashboard.min.js',
        [ 'wp-element' ],
        NEHTW_GATEWAY_VERSION,
        true
    );
    
    wp_enqueue_style(
        'nehtw-subscriptions',
        NEHTW_GATEWAY_PLUGIN_URL . 'assets/css/nehtw-subscriptions.css',
        [],
        NEHTW_GATEWAY_VERSION
    );
    
    // Localize script with REST API URL
    wp_localize_script( 'nehtw-subscription-dashboard', 'nehtwSubscription', [
        'restUrl' => rest_url( 'nehtw/v1/' ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
    ] );
}
add_action( 'wp_enqueue_scripts', 'nehtw_enqueue_subscription_dashboard' );

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
        
        $order_id = $updated !== false ? (int) $existing['id'] : false;
        
        // Sync to dashboard orders table
        if ( $order_id && class_exists( 'Nehtw_Order_Sync' ) ) {
            $stock_order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $order_id ), ARRAY_A );
            if ( $stock_order ) {
                Nehtw_Order_Sync::sync_order_to_dashboard( $stock_order );
            }
        }
        
        return $order_id;
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
    
    $order_id = $wpdb->insert_id;
    
    // Sync to dashboard orders table
    if ( class_exists( 'Nehtw_Order_Sync' ) ) {
        $stock_order = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $order_id ), ARRAY_A );
        if ( $stock_order ) {
            Nehtw_Order_Sync::sync_order_to_dashboard( $stock_order );
        }
    }
    
    return $order_id;
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
    
    // Sync to dashboard orders table
    if ( false !== $updated && class_exists( 'Nehtw_Order_Sync' ) ) {
        Nehtw_Order_Sync::sync_status_update( $task_id, $status, $fields );
    }
    
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

/**
 * Internal logging helper for Nehtw gateway debug.
 *
 * Only logs when WP_DEBUG is enabled. Never logs secrets (API keys, auth headers).
 * All logs go to WordPress debug log via error_log().
 *
 * @param string $operation Short label for where this is logged (e.g. 'download_redownload').
 * @param array  $context   Additional context: user_id, task_id, history_id, status, request, response, message.
 */
function nehtw_gateway_log_event( $operation, array $context = array() ) {
    // Only log when WP_DEBUG is enabled.
    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
        return;
    }

    // Never log secrets - remove API keys and auth headers from context.
    if ( isset( $context['request'] ) && is_array( $context['request'] ) ) {
        if ( isset( $context['request']['headers'] ) && is_array( $context['request']['headers'] ) ) {
            if ( isset( $context['request']['headers']['X-Api-Key'] ) ) {
                unset( $context['request']['headers']['X-Api-Key'] );
            }
            if ( isset( $context['request']['headers']['Authorization'] ) ) {
                unset( $context['request']['headers']['Authorization'] );
            }
        }
        // Remove API key from URL query params if present.
        if ( isset( $context['request']['url'] ) ) {
            $context['request']['url'] = preg_replace( '/[?&]apikey=[^&]*/', '', $context['request']['url'] );
        }
    }

    // Remove API keys from response data if present.
    if ( isset( $context['response'] ) && is_array( $context['response'] ) ) {
        if ( isset( $context['response']['api_key'] ) ) {
            unset( $context['response']['api_key'] );
        }
        if ( isset( $context['response']['apikey'] ) ) {
            unset( $context['response']['apikey'] );
        }
    }

    $user_id = get_current_user_id();

    $base = array(
        'operation' => sanitize_key( $operation ),
        'user_id'   => $user_id > 0 ? $user_id : null,
        'timestamp' => gmdate( 'Y-m-d H:i:s' ),
    );

    $log_entry = array_merge( $base, $context );

    // Keep the log line compact but informative.
    error_log( '[nehtw-gateway] ' . wp_json_encode( $log_entry ) );
}

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

        // Preserve HTTP status code and raw response data for callers.
        $error_data = array(
                'status'        => (int) $code,
            'body'          => $decoded,
                'response_body' => $body,
                'response_json' => $decoded,
                'endpoint'      => $path,
            'message'       => $message,
        );

        return new WP_Error(
            'nehtw_http_error',
            $message,
            $error_data
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

        // Preserve HTTP status code and raw response data for callers.
        $error_data = array(
            'status'        => (int) $code,
            'body'          => $decoded,
            'response_body' => $body,
            'response_json' => $decoded,
            'endpoint'      => $path,
            'message'       => $message,
        );

        // Log HTTP error for debugging.
        nehtw_gateway_log_event(
            'api_http_error',
            array(
                'status'   => (int) $code,
                'request'  => array(
                    'url'    => preg_replace( '/[?&]apikey=[^&]*/', '', $url ),
                    'method' => 'POST',
                    'path'   => $path,
                ),
                'response' => $error_data,
            )
        );

        return new WP_Error(
            'nehtw_http_error',
            $message,
            $error_data
        );
    }

    $data = json_decode( $body, true );

    if ( null === $data ) {
        return new WP_Error( 'nehtw_bad_json', sprintf( 'Nehtw API returned invalid JSON (HTTP %d).', $code ) );
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

    // Artly-branded CSV export endpoint
    register_rest_route( 'artly/v1', '/downloads-export', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'artly_rest_downloads_export',
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
    register_rest_route( 'artly/v1', '/subscription-plans', array(
        'methods'             => 'GET',
        'callback'            => 'artly_rest_get_subscription_plans',
        'permission_callback' => '__return_true', // Public endpoint - no auth required
    ) );

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

    /*
     * NEHTW Webhook configuration:
     *
     * 1. Set webhook URL in your NEHTW dashboard to:
     *    https://YOUR_SITE/wp-json/artly/v1/nehtw-webhook?key=YOUR_SECRET
     *
     * 2. Enable "Download status changing" event.
     * 3. NEHTW will send headers:
     *      x-neh-event_name
     *      x-neh-status
     *    and query param task_id, which we map to our local stock order.
     *
     * This keeps our local order table in sync and powers real-time UI updates.
     */
    // Webhook endpoint is now handled by Nehtw_Gateway_Webhooks class
    // Keeping this route for backward compatibility but it will use the class method
    register_rest_route(
        'artly/v1',
        '/nehtw-webhook',
        array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array( 'Nehtw_Gateway_Webhooks', 'handle_webhook' ),
            'permission_callback' => '__return_true',
        )
    );

    // POST endpoint for order status polling (task_ids based)
    register_rest_route(
        'artly/v1',
        '/stock-order-status',
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'artly_stock_order_status_endpoint',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
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

    if ( class_exists( 'Nehtw_Sites' ) ) {
        $site_row = $site ? Nehtw_Sites::get( $site ) : null;
        if ( ! $site_row && $source_url ) {
            $site_row = Nehtw_Sites::match_from_url( $source_url );
        }

        if ( ! $site_row ) {
            return new WP_Error( 'nehtw_site_unknown', __( 'This provider is not supported.', 'nehtw-gateway' ), array( 'status' => 400 ) );
        }

        $site = $site_row->site_key;
        $cost_points = (float) $site_row->points_per_file;

        if ( 'active' !== $site_row->status ) {
            $message = 'maintenance' === $site_row->status
                ? sprintf( __( '%s is under maintenance. Try again soon.', 'nehtw-gateway' ), $site_row->label )
                : sprintf( __( '%s is offline right now.', 'nehtw-gateway' ), $site_row->label );
            return new WP_Error( 'nehtw_site_disabled', $message, array( 'status' => 403 ) );
        }
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

        $site_meta = $sites_config[ $site ];
        if ( isset( $site_meta['status'] ) && 'active' !== $site_meta['status'] ) {
            $result['status']  = 'error';
            $result['message'] = sprintf( __( '%s is temporarily unavailable.', 'nehtw-gateway' ), $site_meta['label'] );
            $results[] = $result;
            continue;
        }

        $cost_points = (float) $site_meta['points'];

        // Check for existing completed order (no double charge)
        $existing = function_exists( 'nehtw_gateway_get_existing_stock_order' )
            ? nehtw_gateway_get_existing_stock_order( $user_id, $site, $remote_id )
            : null;

        if ( $existing ) {
            $result['status']   = 'already_downloaded';
            $result['message']  = __( 'You already downloaded this asset. We will generate a fresh download link without charging points.', 'nehtw-gateway' );
            $result['order_id'] = isset( $existing['id'] ) ? (int) $existing['id'] : 0;
            $result['task_id']  = isset( $existing['task_id'] ) ? (string) $existing['task_id'] : '';
            $result['fresh_download_required'] = true;

            // Do not return the cached download link here. The frontend will
            // request a fresh link via the download endpoint to ensure the
            // temporary URL is still valid.

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

        // Record transaction for balance system
        if ( $order_id && class_exists( 'Nehtw_Transaction_Manager' ) ) {
            $stock_title = isset( $api_resp['title'] ) ? $api_resp['title'] : ( isset( $api_resp['stock_id'] ) ? $api_resp['stock_id'] : 'Stock order' );
            $provider_name = isset( $sites_config[ $site ]['label'] ) ? $sites_config[ $site ]['label'] : ucfirst( $site );
            
            Nehtw_Transaction_Manager::record_order_deduction(
                $user_id,
                $order_id,
                $cost_points,
                sprintf( 'Stock order: %s from %s', $stock_title, $provider_name )
            );
        }

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
    
    // Check if client requested fresh link (for already_downloaded items)
    // Check both query params and body params (REST API can use either)
    $fresh_param = $request->get_param( 'fresh' );
    $force_fresh_param = $request->get_param( 'force_fresh' );
    
    // Accept multiple formats: 'true', true, '1', 1
    $force_fresh = false;
    if ( $fresh_param !== null ) {
        $force_fresh = ( $fresh_param === 'true' || $fresh_param === true || $fresh_param === '1' || $fresh_param === 1 );
    }
    if ( ! $force_fresh && $force_fresh_param !== null ) {
        $force_fresh = ( $force_fresh_param === 'true' || $force_fresh_param === true || $force_fresh_param === '1' || $force_fresh_param === 1 );
    }
    
    // For already_downloaded items, always generate fresh link (don't use cache)
    // Note: Order status in DB is usually "completed", not "already_downloaded"
    // The "already_downloaded" status is only in the API response, so we rely on ?fresh=true param
    // If fresh=true is explicitly requested, ALWAYS skip cache and generate new link
    $skip_cache = $force_fresh;

    if ( ! $skip_cache && Nehtw_Gateway_Stock_Orders::order_download_is_valid( $order, $raw_data ) ) {
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
 * Handle NEHTW webhook callbacks for order/download status changes.
 *
 * NEHTW will send GET requests with headers:
 *  - x-neh-event_name
 *  - x-neh-status
 *  - x-neh-... (other context)
 *
 * We expect a query param "key" that must match our stored secret.
 *
 * @param WP_REST_Request $request Request instance.
 * @return WP_REST_Response
 */
function artly_nehtw_webhook_handler( WP_REST_Request $request ) {
    $secret      = get_option( 'nehtw_webhook_secret' );
    $incoming    = $request->get_param( 'key' );
    $event_name  = $request->get_header( 'x-neh-event_name' );
    $status      = $request->get_header( 'x-neh-status' );
    $task_id     = $request->get_param( 'task_id' ); // NEHTW should send the order/task id as query param
    $remote_body = $request->get_params();

    // 1) Basic auth: secret must match if set
    if ( ! empty( $secret ) && hash_equals( (string) $secret, (string) $incoming ) === false ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Invalid webhook key.',
            ),
            403
        );
    }

    if ( empty( $task_id ) || empty( $event_name ) ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Missing task_id or event_name.',
            ),
            400
        );
    }

    // 2) Map webhook to our local order record
    $order = Nehtw_Gateway_Stock_Orders::get_by_task_id( $task_id );
    if ( ! $order ) {
        // Not fatal, but log for debugging
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log( '[artly_nehtw_webhook_handler] Unknown task_id ' . $task_id . ' event: ' . $event_name );
        }
        return new WP_REST_Response(
            array(
                'success' => true,
                'message' => 'Order not found locally, ignoring.',
            ),
            200
        );
    }

    // 3) Interpret NEHTW status codes: "queued", "processing", "ready", "error", "refunded", etc.
    // We only care about status transitions and potential download link payloads.
    $mapped_status = strtolower( (string) $status );
    if ( empty( $mapped_status ) ) {
        $mapped_status = 'processing';
    }

    // Normalize status using existing helper
    $mapped_status = nehtw_gateway_normalize_remote_status( $mapped_status );

    $extra_data = maybe_unserialize( $order['raw_response'] );
    if ( ! is_array( $extra_data ) ) {
        $extra_data = array();
    }

    $extra_data['last_webhook'] = array(
        'ts'      => current_time( 'mysql' ),
        'event'   => $event_name,
        'status'  => $mapped_status,
        'payload' => $remote_body,
    );

    // If webhook includes an updated download link, store it.
    $download_link = '';
    $download_keys = array( 'downloadLink', 'download_link', 'url', 'link' );
    foreach ( $download_keys as $k ) {
        if ( isset( $remote_body[ $k ] ) && ! empty( $remote_body[ $k ] ) ) {
            $download_link = esc_url_raw( $remote_body[ $k ] );
            break;
        }
    }

    // Extract file_name and link_type if present
    $file_name = '';
    $file_name_keys = array( 'file_name', 'filename', 'fileName', 'file' );
    foreach ( $file_name_keys as $k ) {
        if ( isset( $remote_body[ $k ] ) && ! empty( $remote_body[ $k ] ) ) {
            $file_name = sanitize_text_field( $remote_body[ $k ] );
            break;
        }
    }

    $link_type = '';
    $link_type_keys = array( 'link_type', 'linkType', 'type', 'response_type' );
    foreach ( $link_type_keys as $k ) {
        if ( isset( $remote_body[ $k ] ) && ! empty( $remote_body[ $k ] ) ) {
            $link_type = sanitize_text_field( $remote_body[ $k ] );
            break;
        }
    }

    $update_args = array(
        'raw_response' => maybe_serialize( $extra_data ),
    );

    if ( ! empty( $download_link ) ) {
        $update_args['download_link'] = $download_link;
        $mapped_status                = 'completed';
    }

    if ( ! empty( $file_name ) ) {
        $update_args['file_name'] = $file_name;
    }

    if ( ! empty( $link_type ) ) {
        $update_args['link_type'] = $link_type;
    }

    // Also check for nehtw_cost if present
    if ( isset( $remote_body['nehtw_cost'] ) || isset( $remote_body['cost'] ) ) {
        $nehtw_cost = isset( $remote_body['nehtw_cost'] ) ? floatval( $remote_body['nehtw_cost'] ) : floatval( $remote_body['cost'] );
        if ( $nehtw_cost > 0 ) {
            $update_args['nehtw_cost'] = $nehtw_cost;
        }
    }

    // Update status if it changed
    if ( $mapped_status && $mapped_status !== $order['status'] ) {
        Nehtw_Gateway_Stock_Orders::update_status( $task_id, $mapped_status, $update_args );
        // Sync is handled inside update_status method
    } else {
        // Status unchanged, but we still want to update raw_response
        // Use update_status with same status to update other fields
        Nehtw_Gateway_Stock_Orders::update_status( $task_id, $order['status'], $update_args );
        // Sync is handled inside update_status method
    }

    // 4) Optional: send email when status becomes completed
    if ( 'completed' === $mapped_status ) {
        $user_id = (int) $order['user_id'];
        if ( $user_id ) {
            $notify = get_user_meta( $user_id, '_artly_stock_email_notify', true );
            if ( 'yes' === $notify ) {
                $user    = get_user_by( 'id', $user_id );
                $subject = sprintf( __( 'Your Artly download is ready (ID %s)', 'artly' ), $order['id'] );
                $message = __( "Hi,\n\nYour stock file download is now ready inside your Artly account.\n\nVisit: ", 'artly' ) . home_url( '/my-downloads/' );

                if ( $user && $user->user_email ) {
                    wp_mail( $user->user_email, $subject, $message );
                }
            }
        }
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'message' => 'Webhook processed.',
        ),
        200
    );
}

/**
 * Return status info for one or more stock orders for the current user.
 *
 * Expects JSON body: { "task_ids": ["abc123", "xyz456"] }
 *
 * @param WP_REST_Request $request Request instance.
 * @return WP_REST_Response
 */
function artly_stock_order_status_endpoint( WP_REST_Request $request ) {
    $user_id  = get_current_user_id();
    $body     = $request->get_json_params();
    $task_ids = isset( $body['task_ids'] ) ? (array) $body['task_ids'] : array();

    $task_ids = array_filter( array_map( 'sanitize_text_field', $task_ids ) );

    if ( ! $user_id || empty( $task_ids ) ) {
        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => 'Missing user or task_ids.',
            ),
            400
        );
    }

    $results = array();

    foreach ( $task_ids as $task_id ) {
        $order = Nehtw_Gateway_Stock_Orders::get_by_task_id( $task_id );

        if ( ! $order || (int) $order['user_id'] !== (int) $user_id ) {
            continue;
        }

        $raw_data = Nehtw_Gateway_Stock_Orders::get_order_raw_data( $order );
        $formatted = Nehtw_Gateway_Stock_Orders::format_order_for_api( $order );

        // Get status label
        $status_label = $formatted['status'];
        if ( isset( $formatted['status'] ) ) {
            $status_map = array(
                'queued'     => __( 'Queued', 'artly' ),
                'pending'    => __( 'Pending', 'artly' ),
                'processing' => __( 'Processing', 'artly' ),
                'ready'      => __( 'Ready', 'artly' ),
                'completed'  => __( 'Completed', 'artly' ),
                'failed'     => __( 'Failed', 'artly' ),
            );
            $status_normalized = strtolower( $formatted['status'] );
            if ( isset( $status_map[ $status_normalized ] ) ) {
                $status_label = $status_map[ $status_normalized ];
            }
        }

        // Get site label
        $site_label = '';
        if ( isset( $formatted['provider_label'] ) && ! empty( $formatted['provider_label'] ) ) {
            $site_label = $formatted['provider_label'];
        } elseif ( isset( $formatted['site'] ) && ! empty( $formatted['site'] ) ) {
            $site_label = ucfirst( $formatted['site'] );
        }

        $results[ $task_id ] = array(
            'task_id'       => $task_id,
            'status'        => $formatted['status'],
            'status_label'  => $status_label,
            'download_link' => ! empty( $formatted['download_link'] ) ? $formatted['download_link'] : '',
            'site'          => $site_label,
            'stock_id'      => isset( $formatted['stock_id'] ) ? $formatted['stock_id'] : '',
            'cost_points'   => isset( $formatted['cost_points'] ) ? (float) $formatted['cost_points'] : null,
        );
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'orders'  => $results,
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
/**
 * Simple per-user rate limiting for re-download requests.
 *
 * Limit: max 10 re-downloads per rolling 60 seconds per user.
 *
 * @param int $user_id User ID.
 *
 * @return true|WP_Error True if allowed, WP_Error if rate limit exceeded.
 */
function nehtw_gateway_check_redownload_rate_limit( $user_id ) {
    if ( ! $user_id ) {
        // Should not happen, endpoint already requires logged-in user,
        // but fail-safe: block anonymous flood.
        return new WP_Error(
            'too_many_requests',
            __( 'You are making too many download requests. Please wait a moment and try again.', 'nehtw-gateway' ),
            array(
                'status'  => 429,
                'user_id' => 0,
                'reason'  => 'no_user_id',
            )
        );
    }

    $key            = 'nehtw_redownload_rate_' . (int) $user_id;
    $window_seconds = 60;   // 1 minute window
    $max_requests   = 10;   // max 10 requests per window
    $now            = time();
    $state          = get_transient( $key );

    // State structure: array( 'count' => int, 'start' => timestamp )
    if ( empty( $state ) || ! is_array( $state ) || empty( $state['start'] ) || ( $now - (int) $state['start'] ) >= $window_seconds ) {
        // New window
        $state = array(
            'count' => 1,
            'start' => $now,
        );

        // Expire slightly after window end
        set_transient( $key, $state, $window_seconds + 10 );

        return true;
    }

    // Existing window
    $count = (int) $state['count'];

    if ( $count >= $max_requests ) {
        // Log for monitoring
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log(
                sprintf(
                    '[nehtw-gateway] Re-download rate limit hit for user_id=%d (count=%d in %d seconds)',
                    $user_id,
                    $count,
                    $window_seconds
                )
            );
        }

        return new WP_Error(
            'too_many_requests',
            __( 'You are making too many download requests. Please wait a moment and try again.', 'nehtw-gateway' ),
            array(
                'status'  => 429,
                'user_id' => $user_id,
                'reason' => 'rate_limit_exceeded',
                'window'  => $window_seconds,
                'max'     => $max_requests,
                'count'   => $count,
            )
        );
    }

    // Increment count in same window
    $state['count'] = $count + 1;
    set_transient( $key, $state, $window_seconds + 10 );

    return true;
}

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
    // SQL is parameterized for defense in depth
    $order = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND user_id = %d LIMIT 1",
            $history_id,
            $user_id
        ),
        ARRAY_A
    );

    // Explicit ownership validation after query (second line of defense)
    if ( ! $order ) {
        // History record not found or doesn't belong to this user.
        // Log and return 404 to avoid revealing the existence of other users' records.
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log(
                sprintf(
                    '[nehtw-gateway] Download history not found in redownload endpoint. user_id=%d, history_id=%d, ip=%s',
                    (int) $user_id,
                    (int) $history_id,
                    $ip
                )
            );
        }

        return new WP_Error(
            'history_not_found',
            __( 'Download history item not found.', 'nehtw-gateway' ),
            array( 'status' => 404 )
        );
    }

    // Extra safety: verify ownership again in PHP (explicit validation).
    if ( (int) $order['user_id'] !== (int) $user_id ) {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            error_log(
                sprintf(
                    '[nehtw-gateway] Suspicious redownload access: user_id=%d tried history_id=%d owned by user_id=%d, ip=%s',
                    (int) $user_id,
                    (int) $history_id,
                    (int) $order['user_id'],
                    $ip
                )
            );
        }

        return new WP_Error(
            'forbidden',
            __( 'You are not allowed to access this download.', 'nehtw-gateway' ),
            array( 'status' => 403 )
        );
    }

    $task_id = isset( $order['task_id'] ) ? sanitize_text_field( $order['task_id'] ) : '';
    if ( '' === $task_id ) {
        return new WP_REST_Response(
            array( 'message' => __( 'Invalid order.', 'nehtw-gateway' ) ),
            400
        );
    }

    // Always request a fresh link from the provider; do not reuse cached URLs.

    // Rate limiting: prevent re-download abuse (max 10/min per user).
    $rate_check = nehtw_gateway_check_redownload_rate_limit( $user_id );
    if ( is_wp_error( $rate_check ) ) {
        // Just bubble up as REST error.
        return $rate_check;
    }

    // Call Nehtw API to get fresh download link (does not charge again)
    $api = nehtw_gateway_api_order_download( $task_id, 'any' );

    if ( is_wp_error( $api ) ) {
        $status     = 502;
        $error_data = $api->get_error_data();
        $message    = $api->get_error_message();

        // Extract HTTP status code from Nehtw API wrapper error if available.
        if ( 'nehtw_http_error' === $api->get_error_code() && is_array( $error_data ) ) {
            if ( ! empty( $error_data['status'] ) ) {
            $status = (int) $error_data['status'];
        }
            if ( ! empty( $error_data['body']['message'] ) ) {
                $message = (string) $error_data['body']['message'];
            } elseif ( ! empty( $error_data['message'] ) ) {
                $message = (string) $error_data['message'];
            }
        }

        // Log WP_Error for debugging.
        nehtw_gateway_log_event(
            'download_redownload_error_wp_error',
            array(
                'history_id' => $history_id,
                'task_id'    => $task_id,
                'status'     => $status,
                'error_code' => $api->get_error_code(),
                'message'    => $message,
                'response'   => isset( $error_data['body'] ) ? $error_data['body'] : null,
            )
        );

        // Retry on 5xx errors only (server errors that might be transient).
        if ( $status >= 500 ) {
            $api_retry = nehtw_gateway_api_order_download( $task_id, 'any' );

            if ( ! is_wp_error( $api_retry ) ) {
                $api = $api_retry;
            } else {
                // Retry also failed, use retry error data.
                $retry_error_data = $api_retry->get_error_data();
                $retry_message    = $api_retry->get_error_message();

                if ( 'nehtw_http_error' === $api_retry->get_error_code() && is_array( $retry_error_data ) ) {
                    if ( ! empty( $retry_error_data['status'] ) ) {
                        $status = (int) $retry_error_data['status'];
                }
                    if ( ! empty( $retry_error_data['body']['message'] ) ) {
                        $message = (string) $retry_error_data['body']['message'];
                    } elseif ( ! empty( $retry_error_data['message'] ) ) {
                        $message = (string) $retry_error_data['message'];
                    }
                } else {
                    $message = $retry_message;
            }

                // Still return error after retry.
                $status = $status >= 500 ? 502 : $status;
            }
        }

        // If still an error after potential retry, map status codes to appropriate responses.
        if ( is_wp_error( $api ) ) {
            // Normalize known statuses with generic messages.
            if ( 409 === $status ) {
                // Download not ready yet.
                $message = __( 'Download not ready.', 'nehtw-gateway' );
            } elseif ( 404 === $status ) {
                $message = __( 'Download not found.', 'nehtw-gateway' );
            } elseif ( 401 === $status || 403 === $status ) {
                $message = __( 'Unauthorized.', 'nehtw-gateway' );
            } elseif ( $status >= 500 ) {
                $message = __( 'We couldn\'t generate a download link right now. Please try again later.', 'nehtw-gateway' );
            } else {
                // Default for unknown statuses.
                $message = $message ? $message : __( 'Failed to generate download link.', 'nehtw-gateway' );
            }

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => $message,
                ),
                $status
            );
        }
    }

    if ( isset( $api['error'] ) && $api['error'] ) {
        $message = isset( $api['message'] ) ? (string) $api['message'] : '';

        // Log API body error for debugging.
        nehtw_gateway_log_event(
            'download_redownload_error_api_body',
            array(
                'history_id' => $history_id,
                'task_id'       => $task_id,
                'status'        => isset( $api['status'] ) ? (int) $api['status'] : null,
                'message'       => $message,
                'response'      => $api,
            )
        );

        return new WP_REST_Response(
            array(
                'success' => false,
                'message' => __( 'We couldn\'t generate a download link right now. Please try again later.', 'nehtw-gateway' ),
            ),
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
            array(
                'success' => false,
                'message' => __( 'Download link not available at the moment. Please try again later.', 'nehtw-gateway' ),
            ),
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

/**
 * Export user download history as CSV based on current filters.
 *
 * Note: This should not expose any internal API keys or Nehtw-specific details.
 */
function artly_rest_downloads_export( WP_REST_Request $request ) {
    if ( ! is_user_logged_in() ) {
        return new WP_Error(
            'forbidden',
            __( 'You must be logged in to export your downloads.', 'artly' ),
            array( 'status' => 403 )
        );
    }

    $user_id = get_current_user_id();
    $type      = $request->get_param( 'type' );
    $date_from = $request->get_param( 'from' );
    $date_to   = $request->get_param( 'to' );

    if ( ! in_array( $type, array( 'all', 'stock', 'ai' ), true ) ) {
        $type = 'all';
    }

    // Get all history items (no pagination for export)
    if ( ! function_exists( 'nehtw_gateway_get_user_download_history' ) ) {
        return new WP_Error(
            'function_not_found',
            __( 'Download history function not available.', 'artly' ),
            array( 'status' => 500 )
        );
    }

    // Fetch all items by using a large per_page value
    $history = nehtw_gateway_get_user_download_history( $user_id, 1, 10000, $type );
    $items   = isset( $history['items'] ) && is_array( $history['items'] ) ? $history['items'] : array();

    // Filter by date range if provided
    if ( ! empty( $date_from ) || ! empty( $date_to ) ) {
        $filtered_items = array();
        foreach ( $items as $item ) {
            $created_at = isset( $item['created_at'] ) ? intval( $item['created_at'] ) : 0;
            if ( $created_at > 0 ) {
                $item_date = date( 'Y-m-d', $created_at );
                if ( ! empty( $date_from ) && $item_date < $date_from ) {
                    continue;
                }
                if ( ! empty( $date_to ) && $item_date > $date_to ) {
                    continue;
                }
            }
            $filtered_items[] = $item;
        }
        $items = $filtered_items;
    }

    // Build CSV in memory
    $rows   = array();
    $header = array( 'Date', 'Provider', 'Title', 'Points Spent', 'Status', 'Link' );
    $rows[] = $header;

    foreach ( $items as $item ) {
        $date_str = '';
        if ( isset( $item['created_at'] ) && $item['created_at'] > 0 ) {
            $date_str = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['created_at'] );
        }

        $provider = '';
        if ( isset( $item['provider_label'] ) && ! empty( $item['provider_label'] ) ) {
            $provider = $item['provider_label'];
        } elseif ( isset( $item['site'] ) && ! empty( $item['site'] ) ) {
            $provider = ucfirst( $item['site'] );
        } elseif ( isset( $item['kind'] ) && 'ai' === $item['kind'] ) {
            $provider = __( 'AI', 'artly' );
        }

        $title = '';
        if ( isset( $item['title'] ) && ! empty( $item['title'] ) ) {
            $title = $item['title'];
        } elseif ( isset( $item['remote_id'] ) && ! empty( $item['remote_id'] ) ) {
            $title = $item['remote_id'];
        } elseif ( isset( $item['file_name'] ) && ! empty( $item['file_name'] ) ) {
            $title = $item['file_name'];
        }

        $points = isset( $item['points'] ) ? floatval( $item['points'] ) : 0.0;
        $status = isset( $item['status'] ) ? $item['status'] : '';
        $link   = isset( $item['download_url'] ) ? $item['download_url'] : ( isset( $item['stock_url'] ) ? $item['stock_url'] : '' );

        $rows[] = array(
            $date_str,
            $provider,
            $title,
            number_format_i18n( $points, 2 ),
            $status,
            $link,
        );
    }

    // Build CSV string
    $csv_output = '';
    foreach ( $rows as $row ) {
        $csv_output .= '"' . implode( '","', array_map( function( $field ) {
            return str_replace( '"', '""', $field );
        }, $row ) ) . '"' . "\n";
    }

    // Return CSV as REST response with proper headers
    $response = new WP_REST_Response( $csv_output, 200 );
    $response->header( 'Content-Type', 'text/csv; charset=utf-8' );
    $response->header( 'Content-Disposition', 'attachment; filename="artly-downloads-' . date( 'Y-m-d' ) . '.csv"' );
    $response->header( 'X-Artly-Export', 'downloads' );

    return $response;
}

/**
 * Get subscription plans with product URLs for frontend.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function artly_rest_get_subscription_plans( WP_REST_Request $request ) {
    if ( ! function_exists( 'nehtw_gateway_get_subscription_plans' ) ) {
        return new WP_Error(
            'function_not_found',
            __( 'Subscription plans function not available.', 'artly' ),
            array( 'status' => 500 )
        );
    }

    $backend_plans = nehtw_gateway_get_subscription_plans();
    $plans         = array();

    foreach ( $backend_plans as $backend_plan ) {
        $plan_key   = isset( $backend_plan['key'] ) ? $backend_plan['key'] : '';
        $plan_name  = isset( $backend_plan['name'] ) ? $backend_plan['name'] : '';
        $plan_points = isset( $backend_plan['points'] ) ? floatval( $backend_plan['points'] ) : 0;
        $plan_desc  = isset( $backend_plan['description'] ) ? $backend_plan['description'] : '';
        $plan_highlight = ! empty( $backend_plan['highlight'] );
        $product_id = isset( $backend_plan['product_id'] ) ? intval( $backend_plan['product_id'] ) : 0;

        // Parse price_label to extract EGP and USD prices
        $price_label = isset( $backend_plan['price_label'] ) ? $backend_plan['price_label'] : '';
        $price_egp   = null;
        $price_usd   = null;

        if ( ! empty( $price_label ) ) {
            // Try to match EGP price
            if ( preg_match( '/EGP\s*([\d,]+)/i', $price_label, $egp_matches ) ) {
                $price_egp = floatval( str_replace( ',', '', $egp_matches[1] ) );
            }
            // Try to match USD price
            if ( preg_match( '/\$?\s*([\d,]+)/', $price_label, $usd_matches ) ) {
                $price_usd = floatval( str_replace( ',', '', $usd_matches[1] ) );
            }
        }

        // Get product URL if product_id exists
        $product_url = '';
        if ( $product_id > 0 && function_exists( 'get_permalink' ) && function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( $product_id );
            if ( $product && $product->is_purchasable() ) {
                $product_url = get_permalink( $product_id );
            }
        }

        // Only include plans with valid data
        if ( ! empty( $plan_name ) && $plan_points > 0 ) {
            $plans[] = array(
                'key'         => $plan_key,
                'name'        => $plan_name,
                'points'      => $plan_points,
                'price_egp'   => $price_egp,
                'price_usd'   => $price_usd,
                'price_label' => $price_label,
                'description' => $plan_desc,
                'highlight'   => $plan_highlight,
                'product_id'  => $product_id > 0 ? $product_id : null,
                'product_url' => $product_url,
            );
        }
    }

    return new WP_REST_Response(
        array(
            'success' => true,
            'plans'   => $plans,
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
                $status     = 502;
                $error_data = $api->get_error_data();
                $message    = $api->get_error_message();

                // Extract HTTP status code from Nehtw API wrapper error if available.
                if ( 'nehtw_http_error' === $api->get_error_code() && is_array( $error_data ) ) {
                    if ( ! empty( $error_data['status'] ) ) {
                        $status = (int) $error_data['status'];
                    }
                    if ( ! empty( $error_data['body']['message'] ) ) {
                        $message = (string) $error_data['body']['message'];
                    } elseif ( ! empty( $error_data['message'] ) ) {
                        $message = (string) $error_data['message'];
                    }
                }

                // Log WP_Error for debugging.
                nehtw_gateway_log_event(
                    'stock_download_link_error_wp_error',
                    array(
                        'task_id'    => $id,
                        'status'      => $status,
                        'error_code'  => $api->get_error_code(),
                        'message'     => $message,
                        'response'    => isset( $error_data['body'] ) ? $error_data['body'] : null,
                    )
                );

                // Normalize known statuses with generic messages.
                if ( 409 === $status ) {
                    // Download not ready yet.
                    $message = __( 'Download not ready.', 'nehtw-gateway' );
                } elseif ( 404 === $status ) {
                    $message = __( 'Download not found.', 'nehtw-gateway' );
                } elseif ( 401 === $status || 403 === $status ) {
                    $message = __( 'Unauthorized.', 'nehtw-gateway' );
                } elseif ( $status >= 500 ) {
                    $message = __( 'Service temporarily unavailable.', 'nehtw-gateway' );
                } else {
                    // Default for unknown statuses.
                    $message = $message ? $message : __( 'Unable to refresh the download link.', 'nehtw-gateway' );
                }

                return new WP_REST_Response(
                    array(
                        'success' => false,
                        'message' => $message,
                    ),
                    $status
                );
            }

            if ( isset( $api['error'] ) && $api['error'] ) {
                $message = isset( $api['message'] ) ? (string) $api['message'] : __( 'Unable to refresh the download link.', 'nehtw-gateway' );

                // Log API body error for debugging.
                nehtw_gateway_log_event(
                    'stock_download_link_error_api_body',
                    array(
                        'task_id'  => $id,
                        'status'   => isset( $api['status'] ) ? (int) $api['status'] : null,
                        'message'  => $message,
                        'response' => $api,
                    )
                );

                return new WP_REST_Response(
                    array(
                        'success' => false,
                        'message' => $message,
                    ),
                    502
                );
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
 * Register the main settings page as first submenu (prevents redirect issues)
 */
function nehtw_gateway_register_main_submenu() {
    add_submenu_page(
        'nehtw-gateway',
        __( 'Nehtw Gateway', 'nehtw-gateway' ),
        __( 'Settings', 'nehtw-gateway' ),
        'manage_options',
        'nehtw-gateway',
        'nehtw_gateway_render_admin_page'
    );
}
add_action( 'admin_menu', 'nehtw_gateway_register_main_submenu', 1 );

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
 * Register the Nehtw Dashboard menu.
 */
function nehtw_gateway_register_dashboard_menu() {
    add_menu_page(
        __( 'Nehtw Dashboard', 'nehtw-gateway' ),
        __( 'Nehtw Dashboard', 'nehtw-gateway' ),
        'manage_options',
        'nehtw-dashboard',
        'nehtw_gateway_render_dashboard',
        'dashicons-chart-area',
        3
    );
}
add_action( 'admin_menu', 'nehtw_gateway_register_dashboard_menu' );

/**
 * Register the Transaction History submenu under Nehtw Gateway.
 */
function nehtw_gateway_register_transaction_history_submenu() {
    add_submenu_page(
        'nehtw-gateway',
        __( 'Transaction History', 'nehtw-gateway' ),
        __( 'Transaction History', 'nehtw-gateway' ),
        'manage_options',
        'nehtw-gateway-transactions',
        'nehtw_gateway_render_transaction_history_page'
    );
}
add_action( 'admin_menu', 'nehtw_gateway_register_transaction_history_submenu' );

/**
 * Render transaction history page.
 */
function nehtw_gateway_render_transaction_history_page() {
    if (!class_exists('Nehtw_Transaction_Manager')) {
        echo '<div class="wrap"><h1>Transaction History</h1><p>Balance system not loaded. Please ensure the plugin is activated.</p></div>';
        return;
    }
    
    global $wpdb;
    
    // Get selected user (if any)
    $selected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    $selected_user = $selected_user_id ? get_userdata($selected_user_id) : null;
    
    // Pagination
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $per_page = 50;
    $offset = ($page - 1) * $per_page;
    
    // Filters
    $category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
    
    // Build query
    $where = ['1=1'];
    $params = [];
    
    if ($selected_user_id) {
        $where[] = 'user_id = %d';
        $params[] = $selected_user_id;
    }
    
    if ($category) {
        $where[] = 'category = %s';
        $params[] = $category;
    }
    
    if ($type) {
        $where[] = 'type = %s';
        $params[] = $type;
    }
    
    if ($start_date) {
        $where[] = 'created_at >= %s';
        $params[] = $start_date . ' 00:00:00';
    }
    
    if ($end_date) {
        $where[] = 'created_at <= %s';
        $params[] = $end_date . ' 23:59:59';
    }
    
    $where_clause = implode(' AND ', $where);
    
    // Get transactions
    $query = "SELECT * FROM {$wpdb->prefix}nehtw_transactions WHERE {$where_clause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $params[] = $per_page;
    $params[] = $offset;
    
    $transactions = $wpdb->get_results($wpdb->prepare($query, $params));
    
    // Get total count
    $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_transactions WHERE {$where_clause}";
    $total = $wpdb->get_var($wpdb->prepare($count_query, array_slice($params, 0, -2)));
    $total_pages = ceil($total / $per_page);
    
    // Get summary stats
    $stats_query = "SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as total_credits,
        SUM(CASE WHEN type = 'debit' THEN ABS(amount) ELSE 0 END) as total_debits
        FROM {$wpdb->prefix}nehtw_transactions 
        WHERE {$where_clause}";
    $stats = $wpdb->get_row($wpdb->prepare($stats_query, array_slice($params, 0, -2)));
    ?>
    <div class="wrap">
        <h1>
            <span class="dashicons dashicons-list-view"></span>
            Transaction History
        </h1>
        
        <!-- Summary Stats -->
        <div class="nehtw-transaction-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
            <div class="postbox" style="padding: 15px;">
                <h3 style="margin: 0 0 10px;">Total Transactions</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 0; color: #2271b1;">
                    <?php echo number_format($stats->total_transactions ?? 0); ?>
                </p>
            </div>
            <div class="postbox" style="padding: 15px;">
                <h3 style="margin: 0 0 10px;">Total Credits</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 0; color: #00a32a;">
                    $<?php echo number_format($stats->total_credits ?? 0, 2); ?>
                </p>
            </div>
            <div class="postbox" style="padding: 15px;">
                <h3 style="margin: 0 0 10px;">Total Debits</h3>
                <p style="font-size: 24px; font-weight: bold; margin: 0; color: #d63638;">
                    $<?php echo number_format($stats->total_debits ?? 0, 2); ?>
                </p>
            </div>
        </div>
        
        <!-- Filters -->
        <form method="get" action="" style="margin: 20px 0;">
            <input type="hidden" name="page" value="nehtw-gateway-transactions">
            
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 15px;">
                <div>
                    <label>User ID:</label>
                    <input type="number" name="user_id" value="<?php echo esc_attr($selected_user_id); ?>" class="regular-text" placeholder="Filter by user">
                </div>
                <div>
                    <label>Category:</label>
                    <select name="category" class="regular-text">
                        <option value="">All Categories</option>
                        <option value="order" <?php selected($category, 'order'); ?>>Orders</option>
                        <option value="subscription" <?php selected($category, 'subscription'); ?>>Subscriptions</option>
                        <option value="refund" <?php selected($category, 'refund'); ?>>Refunds</option>
                        <option value="transfer_sent" <?php selected($category, 'transfer_sent'); ?>>Transfers Sent</option>
                        <option value="transfer_received" <?php selected($category, 'transfer_received'); ?>>Transfers Received</option>
                        <option value="bonus" <?php selected($category, 'bonus'); ?>>Bonuses</option>
                    </select>
                </div>
                <div>
                    <label>Type:</label>
                    <select name="type" class="regular-text">
                        <option value="">All Types</option>
                        <option value="credit" <?php selected($type, 'credit'); ?>>Credits</option>
                        <option value="debit" <?php selected($type, 'debit'); ?>>Debits</option>
                    </select>
                </div>
                <div>
                    <label>Date Range:</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="date" name="start_date" value="<?php echo esc_attr($start_date); ?>" placeholder="Start">
                        <input type="date" name="end_date" value="<?php echo esc_attr($end_date); ?>" placeholder="End">
                    </div>
                </div>
            </div>
            
            <button type="submit" class="button button-primary">Filter</button>
            <a href="?page=nehtw-gateway-transactions" class="button">Clear</a>
        </form>
        
        <!-- Transactions Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            No transactions found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($transactions as $tx): ?>
                        <?php $user = get_userdata($tx->user_id); ?>
                        <tr>
                            <td><?php echo $tx->id; ?></td>
                            <td>
                                <?php if ($user): ?>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $tx->user_id); ?>">
                                        <?php echo esc_html($user->display_name); ?> (ID: <?php echo $tx->user_id; ?>)
                                    </a>
                                <?php else: ?>
                                    User #<?php echo $tx->user_id; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($tx->created_at)); ?></td>
                            <td>
                                <span class="dashicons dashicons-<?php echo $tx->type === 'credit' ? 'arrow-up-alt' : 'arrow-down-alt'; ?>" 
                                      style="color: <?php echo $tx->type === 'credit' ? '#00a32a' : '#d63638'; ?>;"></span>
                                <?php echo ucfirst($tx->type); ?>
                            </td>
                            <td><?php echo ucfirst(str_replace('_', ' ', $tx->category)); ?></td>
                            <td><?php echo esc_html($tx->description); ?></td>
                            <td style="font-weight: bold; color: <?php echo $tx->type === 'credit' ? '#00a32a' : '#d63638'; ?>;">
                                <?php echo $tx->type === 'credit' ? '+' : '-'; ?>$<?php echo number_format(abs($tx->amount), 2); ?>
                            </td>
                            <td>$<?php echo number_format($tx->balance_after, 2); ?></td>
                            <td>
                                <span style="padding: 3px 8px; border-radius: 3px; background: <?php 
                                    echo $tx->status === 'completed' ? '#00a32a' : ($tx->status === 'pending' ? '#f0b849' : '#d63638'); 
                                ?>; color: white; font-size: 11px;">
                                    <?php echo ucfirst($tx->status); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $pagination_args = [
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $page
                    ];
                    echo paginate_links($pagination_args);
                    ?>
                </div>
            </div>
        <?php endif; ?>
        
        <p style="margin-top: 20px; color: #666;">
            <strong>Total:</strong> <?php echo number_format($total); ?> transactions
            <?php if ($selected_user_id && $selected_user): ?>
                for user: <strong><?php echo esc_html($selected_user->display_name); ?></strong>
            <?php endif; ?>
        </p>
    </div>
    <?php
}

/**
 * Render dashboard page.
 */
function nehtw_gateway_render_dashboard() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
    }
    
    require_once NEHTW_GATEWAY_PLUGIN_DIR . 'includes/admin/dashboard-page.php';
}

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
                
                // Auto-create/update WooCommerce product for this plan
                if ( class_exists( 'WooCommerce' ) && function_exists( 'nehtw_gateway_ensure_subscription_product' ) ) {
                    nehtw_gateway_ensure_subscription_product( $plan );
                    // Reload plans to get updated product_id
                    $plans = nehtw_gateway_get_subscription_plans();
                }
                
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
    $currency_rate_notice      = '';
    $currency_rate_notice_class = 'notice-success';

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

    // Handle currency rate save
    if ( isset( $_POST['nehtw_gateway_action'] ) && 'save_currency_rate' === $_POST['nehtw_gateway_action'] ) {
        check_admin_referer( 'nehtw_gateway_save_currency_rate', 'nehtw_gateway_currency_rate_nonce' );

        $raw_rate = isset( $_POST['nehtw_usd_egp_rate'] ) ? sanitize_text_field( wp_unslash( $_POST['nehtw_usd_egp_rate'] ) ) : '';
        $rate     = floatval( $raw_rate );

        if ( $rate > 0 ) {
            update_option( 'nehtw_usd_egp_rate', $rate );
            $currency_rate_notice       = sprintf( __( 'Currency conversion rate updated: 1 USD = %.2f EGP', 'nehtw-gateway' ), $rate );
            $currency_rate_notice_class = 'notice-success';
        } else {
            $currency_rate_notice       = __( 'Invalid rate. Please enter a positive number.', 'nehtw-gateway' );
            $currency_rate_notice_class = 'notice-error';
        }
    }

    $current_user_id = get_current_user_id();
    $wallet_message = $api_error = $api_result = $stock_order_message = $stock_order_error = $stock_order_result = $download_check_error = $download_check_result = '';
    $advanced_controls = nehtw_gateway_get_advanced_controls();

    if ( isset( $_POST['nehtw_gateway_action'] ) && 'save_advanced_controls' === $_POST['nehtw_gateway_action'] ) {
        check_admin_referer( 'nehtw_gateway_save_advanced_controls', 'nehtw_gateway_advanced_controls_nonce' );
        $payload = array(
            'enable_scheduled_maintenance' => isset( $_POST['enable_scheduled_maintenance'] ),
            'enable_notify_back_online'    => isset( $_POST['enable_notify_back_online'] ),
            'enable_audit_log'             => isset( $_POST['enable_audit_log'] ),
            'provider_list_chips_public'   => isset( $_POST['provider_list_chips_public'] ),
        );
        nehtw_gateway_update_advanced_controls( $payload );
        $advanced_controls = nehtw_gateway_get_advanced_controls();
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Advanced controls updated.', 'nehtw-gateway' ) . '</p></div>';
    }

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

        <h2><?php esc_html_e( 'Currency Settings', 'nehtw-gateway' ); ?></h2>
        <?php if ( ! empty( $currency_rate_notice ) ) : ?>
            <div class="notice <?php echo esc_attr( $currency_rate_notice_class ); ?> is-dismissible">
                <p><?php echo esc_html( $currency_rate_notice ); ?></p>
            </div>
        <?php endif; ?>
        <form method="post">
            <?php wp_nonce_field( 'nehtw_gateway_save_currency_rate', 'nehtw_gateway_currency_rate_nonce' ); ?>
            <input type="hidden" name="nehtw_gateway_action" value="save_currency_rate" />
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="nehtw_usd_egp_rate"><?php esc_html_e( 'USD to EGP Conversion Rate', 'nehtw-gateway' ); ?></label></th>
                    <td>
                        <input type="number" step="0.01" min="0.01" class="regular-text" id="nehtw_usd_egp_rate" name="nehtw_usd_egp_rate" value="<?php echo esc_attr( get_option( 'nehtw_usd_egp_rate', 50 ) ); ?>" placeholder="50.00" />
                        <p class="description">
                            <?php esc_html_e( 'Enter the conversion rate: 1 USD = X EGP. This rate is used for currency conversion in the pricing system. Default: 50 EGP per USD.', 'nehtw-gateway' ); ?>
                        </p>
                        <p class="description">
                            <?php
                            $current_rate = get_option( 'nehtw_usd_egp_rate', 50 );
                            printf(
                                esc_html__( 'Current rate: 1 USD = %.2f EGP', 'nehtw-gateway' ),
                                $current_rate
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Currency Rate', 'nehtw-gateway' ) ); ?>
        </form>

        <?php if ( ! empty( $wallet_message ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html( $wallet_message ); ?></p>
            </div>
        <?php endif; ?>

        <h2><?php esc_html_e( 'Your Current Balance', 'nehtw-gateway' ); ?></h2>
        <p><?php printf( esc_html__( 'You currently have %s points.', 'nehtw-gateway' ), '<strong>' . esc_html( $balance ) . '</strong>' ); ?></p>

        <hr />

        <h2><?php esc_html_e( 'Advanced Controls', 'nehtw-gateway' ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'nehtw_gateway_save_advanced_controls', 'nehtw_gateway_advanced_controls_nonce' ); ?>
            <input type="hidden" name="nehtw_gateway_action" value="save_advanced_controls" />
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Scheduled maintenance', 'nehtw-gateway' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_scheduled_maintenance" <?php checked( $advanced_controls['enable_scheduled_maintenance'] ); ?> />
                            <?php esc_html_e( 'Enable scheduler to flip provider statuses automatically.', 'nehtw-gateway' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Notify when back online', 'nehtw-gateway' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_notify_back_online" <?php checked( $advanced_controls['enable_notify_back_online'] ); ?> />
                            <?php esc_html_e( 'Allow users to subscribe for email notifications.', 'nehtw-gateway' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Audit log', 'nehtw-gateway' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_audit_log" <?php checked( $advanced_controls['enable_audit_log'] ); ?> />
                            <?php esc_html_e( 'Track every status or points update.', 'nehtw-gateway' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Provider chips on public page', 'nehtw-gateway' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="provider_list_chips_public" <?php checked( $advanced_controls['provider_list_chips_public'] ); ?> />
                            <?php esc_html_e( 'Render status chips on the Stock page.', 'nehtw-gateway' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Save Advanced Controls', 'nehtw-gateway' ) ); ?>
        </form>

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
 * Process subscription renewals - credits points when next_renewal_at passes.
 * This function is called by cron to automatically credit points for active subscriptions.
 *
 * @return array Results array with processed count and errors.
 */
function nehtw_gateway_process_subscription_renewals() {
    global $wpdb;

    $table = nehtw_gateway_get_subscriptions_table();
    if ( ! $table ) {
        return array(
            'processed' => 0,
            'credited'  => 0,
            'errors'    => array( 'Table not found' ),
        );
    }

    $now = gmdate( 'Y-m-d H:i:s' );
    $results = array(
        'processed' => 0,
        'credited'  => 0,
        'errors'    => array(),
    );

    // Get active subscriptions due for renewal
    $sql = $wpdb->prepare(
        "SELECT * FROM {$table} WHERE status = %s AND next_renewal_at <= %s ORDER BY next_renewal_at ASC",
        'active',
        $now
    );

    $subs = $wpdb->get_results( $sql, ARRAY_A );

    if ( empty( $subs ) ) {
        return $results;
    }

    foreach ( $subs as $sub ) {
        $results['processed']++;
        
        $user_id = (int) $sub['user_id'];
        $points  = isset( $sub['points_per_interval'] ) ? (float) $sub['points_per_interval'] : 0.0;
        $plan_key = isset( $sub['plan_key'] ) ? $sub['plan_key'] : '';

        if ( $user_id <= 0 || $points <= 0 ) {
            $results['errors'][] = sprintf(
                'Invalid subscription #%d: user_id=%d, points=%.2f',
                $sub['id'],
                $user_id,
                $points
            );
            continue;
        }

        // Verify user exists
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            $results['errors'][] = sprintf( 'User #%d not found for subscription #%d', $user_id, $sub['id'] );
            continue;
        }

        // Credit points to wallet
        if ( function_exists( 'nehtw_gateway_add_transaction' ) ) {
            $transaction_id = nehtw_gateway_add_transaction(
                $user_id,
                'subscription_renewal',
                $points,
                array(
                    'meta' => array(
                        'source'       => 'subscription_auto_topup',
                        'plan_key'     => $plan_key,
                        'subscription_id' => $sub['id'],
                        'note'         => sprintf( 'Auto top-up from subscription plan: %s', $plan_key ),
                    ),
                )
            );

            if ( ! $transaction_id ) {
                $results['errors'][] = sprintf( 'Failed to credit points for subscription #%d', $sub['id'] );
                continue;
            }

            $results['credited']++;
        } else {
            $results['errors'][] = 'nehtw_gateway_add_transaction function not available';
            continue;
        }

        // Update next_renewal_at (+1 month)
        $next_ts = strtotime( $sub['next_renewal_at'] );
        if ( $next_ts && $next_ts > 0 ) {
            $next = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month', $next_ts ) );
        } else {
            // Fallback: calculate from current time
            $next = gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) );
        }

        $updated = $wpdb->update(
            $table,
            array(
                'next_renewal_at' => $next,
                'updated_at'      => $now,
            ),
            array( 'id' => $sub['id'] ),
            array( '%s', '%s' ),
            array( '%d' )
        );

        if ( false === $updated ) {
            $results['errors'][] = sprintf( 'Failed to update next_renewal_at for subscription #%d', $sub['id'] );
        }

        // Log successful renewal
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf(
                '[Nehtw Gateway] Subscription renewal: User #%d credited %.2f points (Subscription #%d, Plan: %s)',
                $user_id,
                $points,
                $sub['id'],
                $plan_key
            ) );
        }
    }

    return $results;
}

/**
 * Handle subscription renewals and reminders via cron.
 */
add_action( 'nehtw_gateway_run_subscriptions_cron', 'nehtw_gateway_handle_subscriptions_cron' );
function nehtw_gateway_handle_subscriptions_cron() {
    // 1) Handle reminders (3 days & 1 day before) - using templated email system
    if ( function_exists( 'nehtw_gateway_process_subscription_reminders' ) ) {
        nehtw_gateway_process_subscription_reminders();
    }

    // 2) Process due renewals
    $results = nehtw_gateway_process_subscription_renewals();
    
    // Log results
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ( $results['processed'] > 0 || ! empty( $results['errors'] ) ) ) {
        error_log( sprintf(
            '[Nehtw Gateway Cron] Subscription renewals: %d processed, %d credited, %d errors',
            $results['processed'],
            $results['credited'],
            count( $results['errors'] )
        ) );
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
        
        // Record transaction in balance system
        if ( class_exists( 'Nehtw_Transaction_Manager' ) ) {
            $subscription_id = 0; // Will be set after subscription is saved
            $plan_name = isset( $plan['name'] ) ? $plan['name'] : ( isset( $plan['key'] ) ? $plan['key'] : 'Subscription' );
            
            // Get subscription ID if available
            if ( function_exists( 'nehtw_gateway_get_user_subscription' ) ) {
                $user_sub = nehtw_gateway_get_user_subscription( $user_id );
                if ( $user_sub && isset( $user_sub['id'] ) ) {
                    $subscription_id = $user_sub['id'];
                }
            }
            
            Nehtw_Transaction_Manager::record_subscription_credit(
                $user_id,
                $subscription_id,
                $points,
                sprintf( 'Subscription: %s (initial payment)', $plan_name )
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
        
        // Record transaction in balance system
        if ( class_exists( 'Nehtw_Transaction_Manager' ) ) {
            $plan_name = isset( $plan['name'] ) ? $plan['name'] : ( isset( $plan['key'] ) ? $plan['key'] : 'Subscription' );
            
            // Get subscription ID from WooCommerce subscription
            $subscription_id = 0;
            if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
                $subscriptions = wcs_get_subscriptions_for_order( $order->get_id() );
                if ( ! empty( $subscriptions ) ) {
                    $subscription = reset( $subscriptions );
                    $subscription_id = $subscription->get_id();
                }
            }
            
            Nehtw_Transaction_Manager::record_subscription_credit(
                $user_id,
                $subscription_id,
                $points,
                sprintf( 'WooCommerce subscription renewal: %s', $plan_name )
            );
        }

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