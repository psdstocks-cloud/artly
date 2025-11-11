<?php

/**
 * Nehtw Order Poller
 * 
 * Background job that polls pending orders and updates their status
 * Runs via WP-Cron every 2 minutes
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 * 
 * INSTALLATION:
 * 1. Copy this file to: /wp-content/plugins/nehtw-gateway/includes/cron/class-nehtw-order-poller.php
 * 2. Add to your main plugin file (nehtw-gateway.php):
 *    require_once plugin_dir_path(__FILE__) . 'includes/cron/class-nehtw-order-poller.php';
 * 3. Add activation/deactivation hooks:
 *    register_activation_hook(__FILE__, ['Nehtw_Order_Poller', 'schedule_events']);
 *    register_deactivation_hook(__FILE__, ['Nehtw_Order_Poller', 'clear_scheduled_events']);
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Nehtw_Order_Poller {
    
    private $api_key;
    private $max_orders_per_run = 50;
    private $api_delay_microseconds = 500000; // 0.5 seconds between API calls
    
    public function __construct() {
        $this->api_key = get_option('nehtw_api_key') ?: get_option('nehtw_gateway_api_key');
        
        // Register custom cron schedules
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        
        // Register cron actions
        add_action('nehtw_poll_pending_orders', [$this, 'poll_pending_orders']);
        add_action('nehtw_cleanup_old_orders', [$this, 'cleanup_old_orders']);
    }
    
    /**
     * Add custom cron intervals
     */
    public function add_cron_schedules($schedules) {
        // Every 2 minutes
        $schedules['every_2_minutes'] = [
            'interval' => 120,
            'display' => __('Every 2 Minutes', 'nehtw-gateway')
        ];
        
        // Every 5 minutes
        $schedules['every_5_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'nehtw-gateway')
        ];
        
        return $schedules;
    }
    
    /**
     * Schedule cron events (run on plugin activation)
     */
    public static function schedule_events() {
        // Schedule order polling every 2 minutes
        if (!wp_next_scheduled('nehtw_poll_pending_orders')) {
            wp_schedule_event(time(), 'every_2_minutes', 'nehtw_poll_pending_orders');
            error_log('Nehtw Gateway: Scheduled order polling (every 2 minutes)');
        }
        
        // Schedule cleanup daily at 3am
        if (!wp_next_scheduled('nehtw_cleanup_old_orders')) {
            $tomorrow_3am = strtotime('tomorrow 3:00am');
            wp_schedule_event($tomorrow_3am, 'daily', 'nehtw_cleanup_old_orders');
            error_log('Nehtw Gateway: Scheduled daily cleanup (3am)');
        }
    }
    
    /**
     * Clear scheduled events (run on plugin deactivation)
     */
    public static function clear_scheduled_events() {
        wp_clear_scheduled_hook('nehtw_poll_pending_orders');
        wp_clear_scheduled_hook('nehtw_cleanup_old_orders');
        error_log('Nehtw Gateway: Cleared all scheduled events');
    }
    
    /**
     * Poll all pending orders and update their status
     * This is the main cron job function
     */
    public function poll_pending_orders() {
        global $wpdb;
        
        $start_time = microtime(true);
        
        // Get pending/processing orders from nehtw_stock_orders (source of truth)
        // Map statuses: 'pending'/'queued' -> 'pending', 'processing' -> 'processing'
        $pending_orders = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}nehtw_stock_orders 
            WHERE status IN ('pending', 'queued', 'processing')
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
            LIMIT {$this->max_orders_per_run}
        ");
        
        if (empty($pending_orders)) {
            error_log('Nehtw Order Poller: No pending orders to check');
            return;
        }
        
        $checked_count = 0;
        $updated_count = 0;
        $failed_count = 0;
        
        foreach ($pending_orders as $order) {
            $result = $this->check_order_status($order);
            
            $checked_count++;
            
            if ($result === 'updated') {
                $updated_count++;
            } elseif ($result === 'failed') {
                $failed_count++;
            }
            
            // Sleep to avoid rate limiting
            usleep($this->api_delay_microseconds);
        }
        
        $duration = round(microtime(true) - $start_time, 2);
        
        // Log activity
        error_log(sprintf(
            'Nehtw Order Poller: Checked %d orders in %s seconds. Updated: %d, Failed: %d',
            $checked_count,
            $duration,
            $updated_count,
            $failed_count
        ));
    }
    
    /**
     * Check status of a single order via Nehtw API
     * 
     * @return string 'updated', 'failed', or 'unchanged'
     */
    private function check_order_status($order) {
        global $wpdb;
        
        if (empty($this->api_key)) {
            error_log('Nehtw Order Poller: API key not configured');
            return 'failed';
        }
        
        $task_id = $order->task_id;
        
        // Call Nehtw API
        $response = wp_remote_get(
            "https://nehtw.com/api/order/{$task_id}/status",
            [
                'headers' => [
                    'X-Api-Key' => $this->api_key
                ],
                'timeout' => 10
            ]
        );
        
        // Handle API errors
        if (is_wp_error($response)) {
            $this->log_api_error($order, $response->get_error_message());
            return 'failed';
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        // Handle non-200 responses
        if ($status_code !== 200) {
            $this->log_api_error($order, "HTTP {$status_code}: " . ($body['message'] ?? 'Unknown error'));
            return 'failed';
        }
        
        // Handle unsuccessful API responses
        if (empty($body['success'])) {
            $this->handle_order_failure($order, $body);
            return 'failed';
        }
        
        $api_status = $body['status'] ?? '';
        $current_status = $order->status;
        
        // Build update data
        $update_data = [
            'api_response_json' => json_encode($body),
            'updated_at' => current_time('mysql')
        ];
        
        // Map API status to stock_orders status format
        $mapped_status = $current_status;
        $update_fields = [];
        
        // Status: processing (order accepted and being processed)
        if ($api_status === 'processing' && in_array($current_status, ['pending', 'queued'])) {
            $mapped_status = 'processing';
            error_log("Order {$task_id}: {$current_status} → processing");
        }
        
        // Status: ready/completed (download is ready)
        if (in_array($api_status, ['ready', 'completed']) && in_array($current_status, ['pending', 'queued', 'processing'])) {
            $mapped_status = 'completed';
            
            // Store download details
            if (!empty($body['downloadLink'])) {
                $update_fields['download_link'] = $body['downloadLink'];
            }
            
            if (!empty($body['fileName'])) {
                $update_fields['file_name'] = $body['fileName'];
            }
            
            if (!empty($body['linkType'])) {
                $update_fields['link_type'] = $body['linkType'];
            }
            
            error_log("Order {$task_id}: {$current_status} → completed");
            
            // Trigger completed action hook
            do_action('nehtw_order_completed', $order, $body);
        }
        
        // Status: error
        if ($api_status === 'error' || !empty($body['error'])) {
            $this->handle_order_failure($order, $body);
            return 'failed';
        }
        
        // Update stock_orders table (source of truth)
        if ($mapped_status !== $current_status || !empty($update_fields)) {
            $update_fields['raw_response'] = $body;
            
            // Use existing update function which will sync to dashboard
            if (class_exists('Nehtw_Gateway_Stock_Orders')) {
                Nehtw_Gateway_Stock_Orders::update_status($task_id, $mapped_status, $update_fields);
                return 'updated';
            } else {
                // Fallback: direct update
                $stock_update_data = [
                    'status' => $mapped_status,
                    'updated_at' => current_time('mysql')
                ];
                
                if (!empty($update_fields['download_link'])) {
                    $stock_update_data['download_link'] = $update_fields['download_link'];
                }
                if (!empty($update_fields['file_name'])) {
                    $stock_update_data['file_name'] = $update_fields['file_name'];
                }
                if (!empty($update_fields['link_type'])) {
                    $stock_update_data['link_type'] = $update_fields['link_type'];
                }
                if (!empty($update_fields['raw_response'])) {
                    $stock_update_data['raw_response'] = maybe_serialize($update_fields['raw_response']);
                }
                
                $wpdb->update(
                    "{$wpdb->prefix}nehtw_stock_orders",
                    $stock_update_data,
                    ['task_id' => $task_id],
                    null,
                    ['%s']
                );
                
                // Sync to dashboard
                if (class_exists('Nehtw_Order_Sync')) {
                    Nehtw_Order_Sync::sync_status_update($task_id, $mapped_status, $update_fields);
                }
                
                return 'updated';
            }
        }
        
        return 'unchanged';
    }
    
    /**
     * Handle order failure
     */
    private function handle_order_failure($order, $api_response) {
        global $wpdb;
        
        $error_message = $api_response['message'] ?? 'Unknown error';
        $task_id = $order->task_id;
        
        // Update stock_orders table (source of truth)
        $update_fields = [
            'raw_response' => $api_response
        ];
        
        if (class_exists('Nehtw_Gateway_Stock_Orders')) {
            Nehtw_Gateway_Stock_Orders::update_status($task_id, 'failed', $update_fields);
        } else {
            // Fallback: direct update
            $wpdb->update(
                "{$wpdb->prefix}nehtw_stock_orders",
                [
                    'status' => 'failed',
                    'raw_response' => maybe_serialize($api_response),
                    'updated_at' => current_time('mysql')
                ],
                ['task_id' => $task_id],
                null,
                ['%s']
            );
            
            // Sync to dashboard
            if (class_exists('Nehtw_Order_Sync')) {
                Nehtw_Order_Sync::sync_status_update($task_id, 'failed', $update_fields);
            }
        }
        
        error_log("Order {$order->task_id}: FAILED - {$error_message}");
        
        // Create alert
        $this->create_alert([
            'alert_type' => 'order_failed',
            'severity' => 'error',
            'title' => sprintf('Order Failed: %s', $order->task_id),
            'message' => sprintf(
                'Order for %s from %s failed. Error: %s',
                $order->title ?: $order->stock_id,
                $order->site,
                $error_message
            ),
            'related_order_id' => $order->id,
            'related_user_id' => $order->user_id,
            'related_provider' => $order->site,
            'metadata' => json_encode([
                'api_response' => $api_response,
                'task_id' => $order->task_id,
                'cost' => $order->cost
            ])
        ]);
        
        // Consider automatic retry (max 3 attempts)
        if ($order->retry_count < 3) {
            $this->schedule_retry($order);
        }
        
        // Trigger failure action hook
        do_action('nehtw_order_failed', $order, $api_response);
    }
    
    /**
     * Log API connection error
     */
    private function log_api_error($order, $error_message) {
        global $wpdb;
        
        $task_id = $order->task_id;
        
        // Log error in stock_orders (will sync automatically via update_status)
        error_log("Order {$task_id}: API Error - {$error_message}");
        
        // Check if API is consistently failing (multiple errors in last 10 minutes)
        $recent_errors = $wpdb->get_var("
            SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_stock_orders
            WHERE status = 'failed'
            AND updated_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        
        // Create critical alert if multiple API failures
        if ($recent_errors > 5) {
            $this->create_alert([
                'alert_type' => 'api_error',
                'severity' => 'critical',
                'title' => 'Nehtw API Connection Issues',
                'message' => sprintf(
                    'Multiple orders failing with API errors (%d in last 10 minutes). Last error: %s',
                    $recent_errors,
                    $error_message
                ),
                'metadata' => json_encode([
                    'error_count' => $recent_errors,
                    'error_message' => $error_message,
                    'timestamp' => current_time('mysql')
                ])
            ]);
        }
    }
    
    /**
     * Schedule automatic retry for failed order
     */
    private function schedule_retry($order) {
        global $wpdb;
        
        $retry_count = $order->retry_count + 1;
        
        $wpdb->update(
            "{$wpdb->prefix}nehtw_orders",
            [
                'retry_count' => $retry_count,
                'last_retry_at' => current_time('mysql'),
                'status' => 'pending', // Reset to pending for retry
                'error_message' => null // Clear previous error
            ],
            ['id' => $order->id]
        );
        
        error_log("Order {$order->task_id}: Scheduled retry #{$retry_count}");
    }
    
    /**
     * Create an alert
     * Simple version - will be enhanced by Nehtw_Alert_Manager class
     */
    private function create_alert($data) {
        global $wpdb;
        
        return $wpdb->insert(
            "{$wpdb->prefix}nehtw_alerts",
            $data
        );
    }
    
    /**
     * Cleanup old completed/failed orders
     * Runs daily at 3am
     */
    public function cleanup_old_orders() {
        global $wpdb;
        
        // Delete orders older than 90 days that are completed/failed
        $deleted = $wpdb->query("
            DELETE FROM {$wpdb->prefix}nehtw_orders
            WHERE status IN ('ready', 'downloaded', 'failed', 'cancelled')
            AND (completed_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
                 OR updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY))
        ");
        
        if ($deleted > 0) {
            error_log("Nehtw Order Poller: Cleaned up {$deleted} old orders");
        }
        
        // Also cleanup old alerts (older than 30 days and resolved)
        $deleted_alerts = $wpdb->query("
            DELETE FROM {$wpdb->prefix}nehtw_alerts
            WHERE is_resolved = TRUE
            AND resolved_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        if ($deleted_alerts > 0) {
            error_log("Nehtw Order Poller: Cleaned up {$deleted_alerts} old alerts");
        }
    }
    
    /**
     * Manual trigger for testing (admin only)
     */
    public static function manual_poll() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $poller = new self();
        $poller->poll_pending_orders();
        
        wp_redirect(admin_url('admin.php?page=nehtw-dashboard&poll_triggered=1'));
        exit;
    }
}

// Initialize the poller
new Nehtw_Order_Poller();

// Add manual trigger endpoint for testing
add_action('admin_post_nehtw_manual_poll', ['Nehtw_Order_Poller', 'manual_poll']);

