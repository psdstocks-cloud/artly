<?php

/**
 * Nehtw Balance System Database Schema
 * 
 * Extends the existing database with balance and transaction tables
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 * 
 * INSTALLATION:
 * 1. Add this to your existing class-nehtw-database.php file
 * 2. Or create new file: /wp-content/plugins/nehtw-gateway/includes/database/class-nehtw-balance-database.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Nehtw_Balance_Database {
    
    const DB_VERSION = '2.0';
    
    /**
     * Create balance-related tables
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        self::create_transactions_table($charset_collate);
        self::create_user_balance_table($charset_collate);
        self::create_balance_alerts_table($charset_collate);
        self::create_spending_analytics_table($charset_collate);
        
        update_option('nehtw_balance_db_version', self::DB_VERSION);
        
        error_log('Nehtw Balance System: Database tables created');
    }
    
    /**
     * Transactions Table - Complete transaction log
     */
    private static function create_transactions_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nehtw_transactions';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            
            type ENUM('debit', 'credit') NOT NULL COMMENT 'Money out or in',
            
            category ENUM(
                'order',
                'subscription',
                'refund',
                'transfer_sent',
                'transfer_received',
                'admin_adjustment',
                'bonus',
                'expiry',
                'manual_topup'
            ) NOT NULL,
            
            amount DECIMAL(10,2) NOT NULL,
            balance_before DECIMAL(10,2) NOT NULL,
            balance_after DECIMAL(10,2) NOT NULL,
            
            related_order_id BIGINT(20) NULL,
            related_subscription_id BIGINT(20) NULL,
            related_user_id BIGINT(20) NULL COMMENT 'For transfers',
            
            description TEXT NOT NULL,
            notes TEXT NULL,
            metadata JSON NULL,
            
            is_promotional BOOLEAN NOT NULL DEFAULT FALSE,
            promo_code VARCHAR(50) NULL,
            promo_expires_at DATETIME NULL,
            
            status ENUM('completed', 'pending', 'failed', 'reversed') NOT NULL DEFAULT 'completed',
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY type (type),
            KEY category (category),
            KEY created_at (created_at),
            KEY user_created (user_id, created_at),
            KEY related_order_id (related_order_id),
            KEY is_promotional (is_promotional)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * User Balance Cache Table
     */
    private static function create_user_balance_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nehtw_user_balance';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            
            current_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            promotional_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            
            nehtw_balance DECIMAL(10,2) NULL,
            last_synced_at DATETIME NULL,
            sync_discrepancy DECIMAL(10,2) NULL,
            
            lifetime_spent DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            lifetime_added DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total_orders INT NOT NULL DEFAULT 0,
            
            subscription_id BIGINT(20) NULL,
            subscription_status VARCHAR(20) NULL,
            next_billing_date DATETIME NULL,
            monthly_allowance DECIMAL(10,2) NULL,
            
            balance_expires_at DATETIME NULL,
            expiring_balance DECIMAL(10,2) NULL,
            
            low_balance_threshold DECIMAL(10,2) DEFAULT 10.00,
            alert_enabled BOOLEAN NOT NULL DEFAULT TRUE,
            last_alert_sent_at DATETIME NULL,
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id),
            KEY subscription_id (subscription_id),
            KEY balance_expires_at (balance_expires_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Balance Alerts Table
     */
    private static function create_balance_alerts_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nehtw_balance_alerts';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            
            alert_type ENUM(
                'low_balance',
                'zero_balance',
                'expiring_soon',
                'expired',
                'sync_error'
            ) NOT NULL,
            
            current_balance DECIMAL(10,2) NOT NULL,
            threshold DECIMAL(10,2) NULL,
            
            message TEXT NOT NULL,
            is_sent BOOLEAN NOT NULL DEFAULT FALSE,
            sent_at DATETIME NULL,
            
            is_acknowledged BOOLEAN NOT NULL DEFAULT FALSE,
            acknowledged_at DATETIME NULL,
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY alert_type (alert_type),
            KEY is_sent (is_sent),
            KEY created_at (created_at)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Spending Analytics Cache Table
     */
    private static function create_spending_analytics_table($charset_collate) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'nehtw_spending_analytics';
        
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            period_type ENUM('daily', 'weekly', 'monthly') NOT NULL,
            period_date DATE NOT NULL,
            
            total_spent DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_orders INT NOT NULL DEFAULT 0,
            avg_order_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            
            provider_breakdown JSON NULL,
            category_breakdown JSON NULL,
            
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            UNIQUE KEY user_period (user_id, period_type, period_date),
            KEY period_date (period_date)
        ) {$charset_collate};";
        
        dbDelta($sql);
    }
    
    /**
     * Migrate existing data (if upgrading from old system)
     */
    public static function migrate_existing_data() {
        global $wpdb;
        
        // Check if we need to migrate
        $needs_migration = get_option('nehtw_balance_needs_migration', false);
        
        if (!$needs_migration) {
            return;
        }
        
        error_log('Starting balance data migration...');
        
        // Get all users who have placed orders
        $users = $wpdb->get_results("
            SELECT DISTINCT user_id FROM {$wpdb->prefix}nehtw_orders
        ");
        
        foreach ($users as $user_data) {
            $user_id = $user_data->user_id;
            
            // Calculate their balance from order history
            $total_spent = $wpdb->get_var($wpdb->prepare("
                SELECT SUM(cost) FROM {$wpdb->prefix}nehtw_orders
                WHERE user_id = %d
                AND status IN ('ready', 'downloaded', 'pending', 'processing')
            ", $user_id));
            
            // Get their current balance from user meta (if stored there)
            $current_balance = get_user_meta($user_id, 'nehtw_balance', true);
            
            if ($current_balance === '') {
                $current_balance = 0;
            }
            
            // Create initial balance record
            $wpdb->replace(
                "{$wpdb->prefix}nehtw_user_balance",
                [
                    'user_id' => $user_id,
                    'current_balance' => $current_balance,
                    'lifetime_spent' => $total_spent ?: 0,
                    'created_at' => current_time('mysql')
                ]
            );
            
            error_log("Migrated user {$user_id}: Balance {$current_balance}, Spent {$total_spent}");
        }
        
        update_option('nehtw_balance_needs_migration', false);
        error_log('Balance data migration completed');
    }
}

// Run migration on admin init
if (is_admin()) {
    add_action('admin_init', ['Nehtw_Balance_Database', 'migrate_existing_data']);
}

