<?php

/**
 * Nehtw Order Sync Helper
 * 
 * Syncs orders between nehtw_stock_orders (existing) and nehtw_orders (dashboard)
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Nehtw_Order_Sync {
    
    /**
     * Map status from stock_orders format to dashboard format
     */
    private static function map_status($status) {
        $status_map = [
            'pending' => 'pending',
            'queued' => 'pending',
            'processing' => 'processing',
            'ready' => 'ready',
            'completed' => 'ready',
            'already_downloaded' => 'downloaded',
            'downloaded' => 'downloaded',
            'failed' => 'failed',
            'error' => 'failed',
            'cancelled' => 'cancelled',
            'refunded' => 'cancelled'
        ];
        
        $status_lower = strtolower($status);
        return isset($status_map[$status_lower]) ? $status_map[$status_lower] : 'pending';
    }
    
    /**
     * Sync a single order from nehtw_stock_orders to nehtw_orders
     * 
     * @param array|object $stock_order Order from nehtw_stock_orders table
     * @return int|false Dashboard order ID or false on failure
     */
    public static function sync_order_to_dashboard($stock_order) {
        global $wpdb;
        
        // Convert to array if object
        if (is_object($stock_order)) {
            $stock_order = (array) $stock_order;
        }
        
        if (empty($stock_order['task_id'])) {
            return false;
        }
        
        $dashboard_table = $wpdb->prefix . 'nehtw_orders';
        
        // Check if dashboard order already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$dashboard_table} WHERE task_id = %s",
            $stock_order['task_id']
        ));
        
        // Prepare data for dashboard table
        $dashboard_data = [
            'task_id' => $stock_order['task_id'],
            'user_id' => (int) $stock_order['user_id'],
            'site' => sanitize_key($stock_order['site']),
            'stock_id' => !empty($stock_order['stock_id']) ? sanitize_text_field($stock_order['stock_id']) : '',
            'stock_url' => !empty($stock_order['source_url']) ? esc_url_raw($stock_order['source_url']) : null,
            'cost' => !empty($stock_order['cost_points']) ? floatval($stock_order['cost_points']) : 0.00,
            'status' => self::map_status($stock_order['status'] ?? 'pending'),
            'ordered_at' => !empty($stock_order['created_at']) ? $stock_order['created_at'] : current_time('mysql'),
            'download_link' => !empty($stock_order['download_link']) ? esc_url_raw($stock_order['download_link']) : null,
            'file_name' => !empty($stock_order['file_name']) ? sanitize_text_field($stock_order['file_name']) : null,
            'link_type' => !empty($stock_order['link_type']) ? sanitize_text_field($stock_order['link_type']) : null,
            'thumbnail_url' => !empty($stock_order['preview_thumb']) ? esc_url_raw($stock_order['preview_thumb']) : null,
            'updated_at' => !empty($stock_order['updated_at']) ? $stock_order['updated_at'] : current_time('mysql')
        ];
        
        // Parse raw_response for additional data
        if (!empty($stock_order['raw_response'])) {
            $raw_response = maybe_unserialize($stock_order['raw_response']);
            if (is_array($raw_response)) {
                $dashboard_data['api_response_json'] = json_encode($raw_response);
                
                // Extract title if available
                if (empty($dashboard_data['title']) && !empty($raw_response['title'])) {
                    $dashboard_data['title'] = sanitize_text_field($raw_response['title']);
                }
            }
        }
        
        // Set completion times based on status
        if (in_array($dashboard_data['status'], ['ready', 'downloaded'])) {
            // Use updated_at as completed_at if status is ready/downloaded
            if (empty($dashboard_data['completed_at']) && !empty($stock_order['updated_at'])) {
                $dashboard_data['completed_at'] = $stock_order['updated_at'];
            }
            
            // Calculate processing time
            $ordered_time = strtotime($dashboard_data['ordered_at']);
            $completed_time = !empty($dashboard_data['completed_at']) ? strtotime($dashboard_data['completed_at']) : time();
            if ($ordered_time && $completed_time && $completed_time > $ordered_time) {
                $dashboard_data['processing_time_seconds'] = $completed_time - $ordered_time;
            }
        }
        
        // Set processing started time
        if ($dashboard_data['status'] === 'processing' && !empty($stock_order['updated_at'])) {
            $dashboard_data['processing_started_at'] = $stock_order['updated_at'];
        }
        
        // Handle error messages
        if (in_array($dashboard_data['status'], ['failed']) && !empty($stock_order['raw_response'])) {
            $raw_response = maybe_unserialize($stock_order['raw_response']);
            if (is_array($raw_response) && !empty($raw_response['message'])) {
                $dashboard_data['error_message'] = sanitize_text_field($raw_response['message']);
            }
        }
        
        if ($existing) {
            // Update existing dashboard order
            $result = $wpdb->update(
                $dashboard_table,
                $dashboard_data,
                ['id' => $existing->id],
                null,
                ['%d']
            );
            
            return $result !== false ? (int) $existing->id : false;
        } else {
            // Insert new dashboard order
            $result = $wpdb->insert(
                $dashboard_table,
                $dashboard_data
            );
            
            return $result !== false ? (int) $wpdb->insert_id : false;
        }
    }
    
    /**
     * Sync order status update to dashboard
     * 
     * @param string $task_id Task ID
     * @param string $status New status
     * @param array $update_data Additional data to update
     * @return bool Success
     */
    public static function sync_status_update($task_id, $status, $update_data = []) {
        global $wpdb;
        
        $dashboard_table = $wpdb->prefix . 'nehtw_orders';
        $stock_table = $wpdb->prefix . 'nehtw_stock_orders';
        
        // Get full order from stock_orders
        $stock_order = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$stock_table} WHERE task_id = %s",
            $task_id
        ), ARRAY_A);
        
        if (!$stock_order) {
            return false;
        }
        
        // Merge update_data into stock_order for sync
        $stock_order = array_merge($stock_order, $update_data);
        $stock_order['status'] = $status;
        
        // Sync to dashboard
        return self::sync_order_to_dashboard($stock_order) !== false;
    }
    
    /**
     * Backfill all existing orders from nehtw_stock_orders to nehtw_orders
     * 
     * @param int $limit Number of orders to process per batch
     * @param int $offset Offset for pagination
     * @return array Stats about the sync
     */
    public static function backfill_orders($limit = 100, $offset = 0) {
        global $wpdb;
        
        $stock_table = $wpdb->prefix . 'nehtw_stock_orders';
        $dashboard_table = $wpdb->prefix . 'nehtw_orders';
        
        // Get orders from stock_orders
        $orders = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$stock_table} ORDER BY id ASC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);
        
        $synced = 0;
        $skipped = 0;
        $errors = 0;
        
        foreach ($orders as $order) {
            $result = self::sync_order_to_dashboard($order);
            if ($result !== false) {
                $synced++;
            } elseif ($result === false && $wpdb->last_error) {
                $errors++;
                error_log('Nehtw Order Sync: Failed to sync order ' . $order['task_id'] . ' - ' . $wpdb->last_error);
            } else {
                $skipped++;
            }
        }
        
        return [
            'synced' => $synced,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_processed' => count($orders)
        ];
    }
    
    /**
     * Get sync statistics
     */
    public static function get_sync_stats() {
        global $wpdb;
        
        $stock_table = $wpdb->prefix . 'nehtw_stock_orders';
        $dashboard_table = $wpdb->prefix . 'nehtw_orders';
        
        $total_stock_orders = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$stock_table}");
        $total_dashboard_orders = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$dashboard_table}");
        
        // Count orders in stock_orders that have matching task_id in dashboard
        $synced_count = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT s.task_id)
            FROM {$stock_table} s
            INNER JOIN {$dashboard_table} d ON s.task_id = d.task_id
        ");
        
        return [
            'total_stock_orders' => $total_stock_orders,
            'total_dashboard_orders' => $total_dashboard_orders,
            'synced_orders' => $synced_count,
            'unsynced_orders' => $total_stock_orders - $synced_count
        ];
    }
}

