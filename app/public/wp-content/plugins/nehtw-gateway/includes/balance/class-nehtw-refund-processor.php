<?php

/**
 * Automatic Refund Processor
 * 
 * Monitors failed orders and automatically refunds users
 * Integrates with order poller
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 * 
 * File: /wp-content/plugins/nehtw-gateway/includes/balance/class-nehtw-refund-processor.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Nehtw_Refund_Processor {
    
    public function __construct() {
        // Hook into order failure
        add_action('nehtw_order_failed', [$this, 'process_refund'], 10, 2);
    }
    
    /**
     * Process refund when order fails
     */
    public function process_refund($order, $api_response) {
        // Handle both object and array formats
        $order_id = is_object($order) ? $order->id : (isset($order['id']) ? $order['id'] : 0);
        $task_id = is_object($order) ? $order->task_id : (isset($order['task_id']) ? $order['task_id'] : '');
        $user_id = is_object($order) ? $order->user_id : (isset($order['user_id']) ? $order['user_id'] : 0);
        $cost = is_object($order) ? $order->cost : (isset($order['cost']) ? $order['cost'] : (isset($order['cost_points']) ? $order['cost_points'] : 0));
        $error_message = is_object($order) ? ($order->error_message ?? '') : (isset($order['error_message']) ? $order['error_message'] : '');
        
        if (!$order_id || !$user_id || $cost <= 0) {
            error_log("Refund Processor: Invalid order data for refund");
            return;
        }
        
        // Check if already refunded
        $existing_refund = $this->check_existing_refund($order_id);
        if ($existing_refund) {
            error_log("Order {$task_id} already refunded");
            return;
        }
        
        // Record refund transaction
        $transaction_id = Nehtw_Transaction_Manager::record_refund(
            $user_id,
            $order_id,
            $cost,
            $error_message ?: 'Order failed'
        );
        
        if ($transaction_id) {
            // Send refund notification email
            $this->send_refund_notification($order, $transaction_id);
            
            error_log("Refund processed for order {$task_id}: ${cost} to user {$user_id}");
        }
    }
    
    /**
     * Check if order already has refund
     */
    private function check_existing_refund($order_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}nehtw_transactions
            WHERE related_order_id = %d 
            AND category = 'refund'
            AND status = 'completed'
        ", $order_id));
    }
    
    /**
     * Send refund notification email
     */
    private function send_refund_notification($order, $transaction_id) {
        // Handle both object and array formats
        $user_id = is_object($order) ? $order->user_id : (isset($order['user_id']) ? $order['user_id'] : 0);
        $task_id = is_object($order) ? $order->task_id : (isset($order['task_id']) ? $order['task_id'] : '');
        $cost = is_object($order) ? $order->cost : (isset($order['cost']) ? $order['cost'] : (isset($order['cost_points']) ? $order['cost_points'] : 0));
        $error_message = is_object($order) ? ($order->error_message ?? '') : (isset($order['error_message']) ? $order['error_message'] : '');
        
        $user = get_userdata($user_id);
        if (!$user) {
            return;
        }
        
        $subject = '[Artly] Refund Processed';
        
        $message = sprintf(
            "Hi %s,\n\n" .
            "We've processed a refund for your failed order.\n\n" .
            "Order ID: %s\n" .
            "Refund Amount: $%s\n" .
            "Reason: %s\n\n" .
            "The amount has been credited back to your account balance.\n\n" .
            "View your transactions: %s\n\n" .
            "Thank you for your understanding,\n" .
            "Artly Team",
            $user->display_name,
            $task_id,
            number_format($cost, 2),
            $error_message ?: 'Order processing failed',
            home_url('/my-account/transactions/')
        );
        
        wp_mail($user->user_email, $subject, $message);
    }
}

// Initialize
new Nehtw_Refund_Processor();

