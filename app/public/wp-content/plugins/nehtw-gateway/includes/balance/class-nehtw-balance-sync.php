<?php

/**
 * Nehtw Balance Sync Engine
 * 
 * Syncs local balance with Nehtw API
 * Runs hourly via WP-Cron
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 * 
 * File: /wp-content/plugins/nehtw-gateway/includes/balance/class-nehtw-balance-sync.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Nehtw_Balance_Sync {
    
    private $api_key;
    
    public function __construct() {
        // Use the plugin's API key constant
        if (defined('NEHTW_GATEWAY_OPTION_API_KEY')) {
            $this->api_key = get_option(NEHTW_GATEWAY_OPTION_API_KEY, '');
        } else {
            $this->api_key = get_option('nehtw_gateway_api_key', '');
        }
        
        // Schedule hourly sync
        add_action('nehtw_sync_user_balances', [$this, 'sync_all_users']);
    }
    
    /**
     * Schedule sync events
     */
    public static function schedule_events() {
        if (!wp_next_scheduled('nehtw_sync_user_balances')) {
            wp_schedule_event(time(), 'hourly', 'nehtw_sync_user_balances');
            error_log('Balance Sync: Scheduled hourly sync');
        }
    }
    
    /**
     * Clear sync events
     */
    public static function clear_events() {
        wp_clear_scheduled_hook('nehtw_sync_user_balances');
    }
    
    /**
     * Sync balance for all users with recent activity
     */
    public function sync_all_users() {
        global $wpdb;
        
        // Get users with orders in last 7 days or pending orders
        $users = $wpdb->get_col("
            SELECT DISTINCT user_id FROM {$wpdb->prefix}nehtw_orders
            WHERE ordered_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
            OR status IN ('pending', 'processing')
        ");
        
        $synced = 0;
        $errors = 0;
        
        foreach ($users as $user_id) {
            $result = $this->sync_user_balance($user_id);
            if ($result) {
                $synced++;
            } else {
                $errors++;
            }
            
            // Sleep to avoid rate limiting
            usleep(100000); // 0.1 seconds
        }
        
        error_log("Balance Sync: Synced {$synced} users, {$errors} errors");
    }
    
    /**
     * Sync balance for single user
     */
    public function sync_user_balance($user_id) {
        global $wpdb;
        
        if (empty($this->api_key)) {
            return false;
        }
        
        // Get user's Nehtw API username (stored in user meta)
        $nehtw_username = get_user_meta($user_id, 'nehtw_username', true);
        if (empty($nehtw_username)) {
            return false;
        }
        
        // Call Nehtw API - use the plugin's API helper if available
        if (function_exists('nehtw_gateway_api_me')) {
            $api_response = nehtw_gateway_api_me();
            if (is_wp_error($api_response) || empty($api_response['success'])) {
                error_log("Balance Sync Error for user {$user_id}: API call failed");
                return false;
            }
            $nehtw_balance = (float) ($api_response['balance'] ?? 0);
        } else {
            // Fallback: direct API call
            $response = wp_remote_get(
                'https://nehtw.com/api/me',
                [
                    'headers' => ['X-Api-Key' => $this->api_key],
                    'timeout' => 10
                ]
            );
            
            if (is_wp_error($response)) {
                error_log("Balance Sync Error for user {$user_id}: " . $response->get_error_message());
                return false;
            }
            
            $body = json_decode(wp_remote_retrieve_body($response), true);
            
            if (empty($body['success'])) {
                return false;
            }
            
            $nehtw_balance = (float) ($body['balance'] ?? 0);
        }
        $local_balance = Nehtw_Transaction_Manager::get_user_balance($user_id);
        
        // Calculate discrepancy
        $discrepancy = $nehtw_balance - $local_balance;
        
        // Update cache
        $wpdb->update(
            "{$wpdb->prefix}nehtw_user_balance",
            [
                'nehtw_balance' => $nehtw_balance,
                'sync_discrepancy' => $discrepancy,
                'last_synced_at' => current_time('mysql')
            ],
            ['user_id' => $user_id]
        );
        
        // Alert if significant discrepancy (more than $1)
        if (abs($discrepancy) > 1.00) {
            $this->create_sync_alert($user_id, $local_balance, $nehtw_balance, $discrepancy);
        }
        
        return true;
    }
    
    /**
     * Create sync discrepancy alert
     */
    private function create_sync_alert($user_id, $local, $nehtw, $discrepancy) {
        global $wpdb;
        
        $wpdb->insert(
            "{$wpdb->prefix}nehtw_balance_alerts",
            [
                'user_id' => $user_id,
                'alert_type' => 'sync_error',
                'current_balance' => $local,
                'message' => sprintf(
                    'Balance sync discrepancy detected. Local: $%s, Nehtw: $%s, Difference: $%s',
                    number_format($local, 2),
                    number_format($nehtw, 2),
                    number_format($discrepancy, 2)
                ),
                'created_at' => current_time('mysql')
            ]
        );
        
        error_log("Sync discrepancy for user {$user_id}: Local ${local}, Nehtw ${nehtw}");
    }
}

// Initialize
new Nehtw_Balance_Sync();

