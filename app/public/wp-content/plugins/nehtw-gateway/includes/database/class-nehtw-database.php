<?php

/**
 * Nehtw Gateway Database Schema
 * 
 * Creates and manages database tables for admin dashboard functionality
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 * 
 * INSTALLATION:
 * 1. Copy this file to: /wp-content/plugins/nehtw-gateway/includes/database/class-nehtw-database.php
 * 2. Add to your main plugin file (nehtw-gateway.php):
 *    require_once plugin_dir_path(__FILE__) . 'includes/database/class-nehtw-database.php';
 * 3. Add activation hook:
 *    register_activation_hook(__FILE__, ['Nehtw_Database', 'create_tables']);
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Nehtw_Database {
    
    const DB_VERSION = '2.0';
    
    /**
     * Create all database tables
     * Run this on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Store current version
        $installed_version = get_option('nehtw_db_version', '0');
        
        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            self::create_orders_table($charset_collate);
            self::create_analytics_table($charset_collate);
            self::create_provider_stats_table($charset_collate);
            self::create_alerts_table($charset_collate);
            self::create_activity_log_table($charset_collate);
            
            update_option('nehtw_db_version', self::DB_VERSION);
            
            error_log('Nehtw Gateway: Database tables created/updated to version ' . self::DB_VERSION);
        }
    }
    
    /**
     * Enhanced Orders Table
     */
    private static function create_orders_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nehtw_orders';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            task_id VARCHAR(100) NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            
            -- Order Details
            site VARCHAR(50) NOT NULL COMMENT 'Provider name (shutterstock, adobestock, etc.)',
            stock_id VARCHAR(100) NOT NULL,
            stock_url TEXT NULL,
            
            -- Pricing
            cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            
            -- Status Tracking
            status ENUM('pending', 'processing', 'ready', 'downloaded', 'failed', 'cancelled') 
                NOT NULL DEFAULT 'pending',
            
            -- Timing Metrics
            ordered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            processing_started_at DATETIME NULL,
            completed_at DATETIME NULL,
            downloaded_at DATETIME NULL,
            processing_time_seconds INT NULL COMMENT 'Time from order to ready',
            
            -- Download Details
            download_link TEXT NULL,
            download_link_expires_at DATETIME NULL,
            file_name VARCHAR(255) NULL,
            link_type VARCHAR(20) NULL COMMENT 'any, gdrive, mydrivelink, asia',
            
            -- Metadata
            thumbnail_url TEXT NULL,
            title VARCHAR(500) NULL,
            file_size BIGINT NULL,
            file_extension VARCHAR(10) NULL,
            
            -- Error Handling
            error_message TEXT NULL,
            retry_count INT NOT NULL DEFAULT 0,
            last_retry_at DATETIME NULL,
            
            -- API Response (for debugging)
            api_response_json TEXT NULL,
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY task_id (task_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY site (site),
            KEY ordered_at (ordered_at),
            KEY completed_at (completed_at),
            KEY status_site (status, site)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Daily Analytics Aggregation Table
     */
    private static function create_analytics_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nehtw_analytics_daily';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            date DATE NOT NULL,
            
            -- Order Metrics
            total_orders INT NOT NULL DEFAULT 0,
            successful_orders INT NOT NULL DEFAULT 0,
            failed_orders INT NOT NULL DEFAULT 0,
            cancelled_orders INT NOT NULL DEFAULT 0,
            
            -- Revenue
            total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            
            -- Performance
            avg_processing_time_seconds INT NULL,
            median_processing_time_seconds INT NULL,
            
            -- User Metrics
            unique_users INT NOT NULL DEFAULT 0,
            new_users INT NOT NULL DEFAULT 0,
            
            -- Provider Breakdown (JSON)
            provider_breakdown JSON NULL,
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY date (date),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Provider Performance Stats Table
     */
    private static function create_provider_stats_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nehtw_provider_stats';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            provider VARCHAR(50) NOT NULL,
            date DATE NOT NULL,
            
            -- Volume
            total_orders INT NOT NULL DEFAULT 0,
            successful_orders INT NOT NULL DEFAULT 0,
            failed_orders INT NOT NULL DEFAULT 0,
            
            -- Success Rate
            success_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Percentage (0-100)',
            
            -- Performance
            avg_processing_time_seconds INT NULL,
            min_processing_time_seconds INT NULL,
            max_processing_time_seconds INT NULL,
            
            -- Revenue
            total_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            
            -- API Status
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            current_price DECIMAL(10,2) NULL,
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY provider_date (provider, date),
            KEY provider (provider),
            KEY date (date),
            KEY success_rate (success_rate)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * System Alerts Table
     */
    private static function create_alerts_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nehtw_alerts';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            
            alert_type ENUM('order_failed', 'api_error', 'provider_down', 'high_failure_rate', 
                'slow_processing', 'balance_low', 'system_error') NOT NULL,
            
            severity ENUM('info', 'warning', 'error', 'critical') NOT NULL DEFAULT 'info',
            
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            
            -- Context
            related_order_id BIGINT(20) NULL,
            related_user_id BIGINT(20) NULL,
            related_provider VARCHAR(50) NULL,
            
            -- Metadata (JSON)
            metadata JSON NULL,
            
            -- Status
            is_read BOOLEAN NOT NULL DEFAULT FALSE,
            is_resolved BOOLEAN NOT NULL DEFAULT FALSE,
            resolved_at DATETIME NULL,
            resolved_by BIGINT(20) NULL COMMENT 'Admin user ID',
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY alert_type (alert_type),
            KEY severity (severity),
            KEY is_read (is_read),
            KEY is_resolved (is_resolved),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * User Activity Log Table
     */
    private static function create_activity_log_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nehtw_activity_log';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            
            user_id BIGINT(20) UNSIGNED NOT NULL,
            action VARCHAR(50) NOT NULL COMMENT 'order_placed, download_completed, balance_added, etc.',
            
            description TEXT NOT NULL,
            
            -- Context
            related_order_id BIGINT(20) NULL,
            related_entity_type VARCHAR(50) NULL,
            related_entity_id BIGINT(20) NULL,
            
            -- Request Details
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            
            -- Metadata (JSON)
            metadata JSON NULL,
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Check if tables need updating
     * Run this on admin_init
     */
    public static function maybe_upgrade() {
        $current_version = get_option('nehtw_db_version', '0');
        
        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::create_tables();
        }
    }
    
    /**
     * Drop all tables (use with caution!)
     * Only for development/uninstall
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            $wpdb->prefix . 'nehtw_orders',
            $wpdb->prefix . 'nehtw_analytics_daily',
            $wpdb->prefix . 'nehtw_provider_stats',
            $wpdb->prefix . 'nehtw_alerts',
            $wpdb->prefix . 'nehtw_activity_log'
        ];
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
        
        delete_option('nehtw_db_version');
        
        error_log('Nehtw Gateway: All database tables dropped');
    }
    
    /**
     * Get table statistics
     * Useful for debugging
     */
    public static function get_table_stats() {
        global $wpdb;
        
        $stats = [];
        
        $tables = [
            'orders' => $wpdb->prefix . 'nehtw_orders',
            'analytics' => $wpdb->prefix . 'nehtw_analytics_daily',
            'provider_stats' => $wpdb->prefix . 'nehtw_provider_stats',
            'alerts' => $wpdb->prefix . 'nehtw_alerts',
            'activity_log' => $wpdb->prefix . 'nehtw_activity_log'
        ];
        
        foreach ($tables as $key => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $stats[$key] = [
                'table' => $table,
                'rows' => $count,
                'exists' => $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table
            ];
        }
        
        return $stats;
    }
}

/**
 * Initialize database on admin pages
 */
if (is_admin()) {
    add_action('admin_init', ['Nehtw_Database', 'maybe_upgrade']);
}

