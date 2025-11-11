<?php

/**
 * Nehtw Balance REST API & AJAX Handlers
 * 
 * Provides endpoints for frontend balance functionality
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 * 
 * File: /wp-content/plugins/nehtw-gateway/includes/balance/class-nehtw-balance-api.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Nehtw_Balance_API {
    
    public function __construct() {
        // Register REST API routes
        add_action('rest_api_init', [$this, 'register_routes']);
        
        // Register AJAX handlers (for logged-in users)
        add_action('wp_ajax_get_user_balance', [$this, 'ajax_get_balance']);
        add_action('wp_ajax_get_transactions', [$this, 'ajax_get_transactions']);
        add_action('wp_ajax_get_spending_analytics', [$this, 'ajax_get_spending_analytics']);
        add_action('wp_ajax_export_transactions', [$this, 'ajax_export_transactions']);
        add_action('wp_ajax_transfer_points', [$this, 'ajax_transfer_points']);
        add_action('wp_ajax_update_alert_settings', [$this, 'ajax_update_alert_settings']);
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        $namespace = 'nehtw/v1/balance';
        
        // Get current user balance
        register_rest_route($namespace, '/current', [
            'methods' => 'GET',
            'callback' => [$this, 'get_current_balance'],
            'permission_callback' => [$this, 'check_user_permission']
        ]);
        
        // Get transaction history
        register_rest_route($namespace, '/transactions', [
            'methods' => 'GET',
            'callback' => [$this, 'get_transactions'],
            'permission_callback' => [$this, 'check_user_permission']
        ]);
        
        // Get spending analytics
        register_rest_route($namespace, '/analytics', [
            'methods' => 'GET',
            'callback' => [$this, 'get_analytics'],
            'permission_callback' => [$this, 'check_user_permission']
        ]);
        
        // Transfer points
        register_rest_route($namespace, '/transfer', [
            'methods' => 'POST',
            'callback' => [$this, 'transfer_points'],
            'permission_callback' => [$this, 'check_user_permission']
        ]);
    }
    
    /**
     * Check if user is logged in
     */
    public function check_user_permission() {
        return is_user_logged_in();
    }
    
    /**
     * Get current balance (REST API)
     */
    public function get_current_balance($request) {
        $user_id = get_current_user_id();
        
        global $wpdb;
        $balance_data = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}nehtw_user_balance WHERE user_id = %d
        ", $user_id));
        
        if (!$balance_data) {
            // Initialize if not exists
            $balance = Nehtw_Transaction_Manager::get_user_balance($user_id);
            
            return rest_ensure_response([
                'current_balance' => $balance,
                'promotional_balance' => 0,
                'lifetime_spent' => 0,
                'lifetime_added' => 0,
                'total_orders' => 0
            ]);
        }
        
        return rest_ensure_response([
            'current_balance' => (float) $balance_data->current_balance,
            'promotional_balance' => (float) $balance_data->promotional_balance,
            'total_balance' => (float) $balance_data->current_balance + (float) $balance_data->promotional_balance,
            'lifetime_spent' => (float) $balance_data->lifetime_spent,
            'lifetime_added' => (float) $balance_data->lifetime_added,
            'total_orders' => (int) $balance_data->total_orders,
            'subscription_status' => $balance_data->subscription_status,
            'next_billing_date' => $balance_data->next_billing_date,
            'low_balance_threshold' => (float) $balance_data->low_balance_threshold,
            'alert_enabled' => (bool) $balance_data->alert_enabled
        ]);
    }
    
    /**
     * Get transactions (REST API)
     */
    public function get_transactions($request) {
        $user_id = get_current_user_id();
        $page = $request->get_param('page') ?: 1;
        $per_page = min($request->get_param('per_page') ?: 20, 100);
        
        $offset = ($page - 1) * $per_page;
        
        $transactions = Nehtw_Transaction_Manager::get_user_transactions($user_id, [
            'limit' => $per_page,
            'offset' => $offset,
            'category' => $request->get_param('category'),
            'type' => $request->get_param('type'),
            'start_date' => $request->get_param('start_date'),
            'end_date' => $request->get_param('end_date')
        ]);
        
        // Get total count
        global $wpdb;
        $total = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_transactions WHERE user_id = %d
        ", $user_id));
        
        return rest_ensure_response([
            'transactions' => $transactions,
            'pagination' => [
                'total' => (int) $total,
                'pages' => ceil($total / $per_page),
                'current_page' => $page,
                'per_page' => $per_page
            ]
        ]);
    }
    
    /**
     * Get analytics (REST API)
     */
    public function get_analytics($request) {
        $user_id = get_current_user_id();
        $period = $request->get_param('period') ?: 'monthly';
        $limit = min($request->get_param('limit') ?: 12, 24);
        
        $analytics = Nehtw_Transaction_Manager::get_spending_analytics($user_id, $period, $limit);
        
        return rest_ensure_response($analytics);
    }
    
    /**
     * Transfer points (REST API)
     */
    public function transfer_points($request) {
        $from_user_id = get_current_user_id();
        $to_username = $request->get_param('to_username');
        $amount = (float) $request->get_param('amount');
        $note = $request->get_param('note') ?: '';
        
        // Validate amount
        if ($amount <= 0) {
            return new WP_Error('invalid_amount', 'Amount must be positive', ['status' => 400]);
        }
        
        // Get recipient user
        $to_user = get_user_by('login', $to_username);
        if (!$to_user) {
            return new WP_Error('user_not_found', 'Recipient user not found', ['status' => 404]);
        }
        
        // Check sender balance
        $balance = Nehtw_Transaction_Manager::get_user_balance($from_user_id);
        if ($balance < $amount) {
            return new WP_Error('insufficient_balance', 'Insufficient balance', ['status' => 400]);
        }
        
        // Process transfer
        $result = Nehtw_Transaction_Manager::record_transfer(
            $from_user_id,
            $to_user->ID,
            $amount,
            $note
        );
        
        if (!$result) {
            return new WP_Error('transfer_failed', 'Transfer failed', ['status' => 500]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => sprintf('Successfully transferred $%s to %s', number_format($amount, 2), $to_username),
            'new_balance' => Nehtw_Transaction_Manager::get_user_balance($from_user_id)
        ]);
    }
    
    /**
     * AJAX: Get user balance
     */
    public function ajax_get_balance() {
        check_ajax_referer('nehtw_balance_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $balance = Nehtw_Transaction_Manager::get_user_balance($user_id);
        
        wp_send_json_success([
            'balance' => $balance,
            'formatted' => '$' . number_format($balance, 2)
        ]);
    }
    
    /**
     * AJAX: Get transactions
     */
    public function ajax_get_transactions() {
        check_ajax_referer('nehtw_balance_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 20;
        
        $offset = ($page - 1) * $per_page;
        
        $transactions = Nehtw_Transaction_Manager::get_user_transactions($user_id, [
            'limit' => $per_page,
            'offset' => $offset
        ]);
        
        wp_send_json_success($transactions);
    }
    
    /**
     * AJAX: Get spending analytics
     */
    public function ajax_get_spending_analytics() {
        check_ajax_referer('nehtw_balance_nonce', 'nonce');
        
        $user_id = get_current_user_id();
        $period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : 'monthly';
        
        $analytics = Nehtw_Transaction_Manager::get_spending_analytics($user_id, $period, 12);
        
        wp_send_json_success($analytics);
    }
    
    /**
     * AJAX: Export transactions to CSV
     */
    public function ajax_export_transactions() {
        check_ajax_referer('export_tx', '_wpnonce');
        
        $user_id = get_current_user_id();
        $csv = Nehtw_Transaction_Manager::export_to_csv($user_id);
        
        $filename = sprintf('artly-transactions-%s-%s.csv', $user_id, date('Y-m-d'));
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $csv;
        exit;
    }
    
    /**
     * AJAX: Transfer points
     */
    public function ajax_transfer_points() {
        check_ajax_referer('nehtw_balance_nonce', 'nonce');
        
        $from_user_id = get_current_user_id();
        $to_username = sanitize_text_field($_POST['to_username']);
        $amount = (float) $_POST['amount'];
        $note = sanitize_textarea_field($_POST['note'] ?? '');
        
        // Validate
        if ($amount <= 0) {
            wp_send_json_error(['message' => 'Invalid amount']);
        }
        
        // Get recipient
        $to_user = get_user_by('login', $to_username);
        if (!$to_user) {
            wp_send_json_error(['message' => 'User not found']);
        }
        
        // Check balance
        $balance = Nehtw_Transaction_Manager::get_user_balance($from_user_id);
        if ($balance < $amount) {
            wp_send_json_error(['message' => 'Insufficient balance']);
        }
        
        // Process transfer
        $result = Nehtw_Transaction_Manager::record_transfer(
            $from_user_id,
            $to_user->ID,
            $amount,
            $note
        );
        
        if ($result) {
            wp_send_json_success([
                'message' => sprintf('Successfully transferred $%s to %s', number_format($amount, 2), $to_username),
                'new_balance' => Nehtw_Transaction_Manager::get_user_balance($from_user_id)
            ]);
        } else {
            wp_send_json_error(['message' => 'Transfer failed']);
        }
    }
    
    /**
     * AJAX: Update alert settings
     */
    public function ajax_update_alert_settings() {
        check_ajax_referer('nehtw_balance_nonce', 'nonce');
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        $threshold = (float) $_POST['threshold'];
        $enabled = (bool) $_POST['enabled'];
        
        $result = $wpdb->update(
            "{$wpdb->prefix}nehtw_user_balance",
            [
                'low_balance_threshold' => $threshold,
                'alert_enabled' => $enabled
            ],
            ['user_id' => $user_id]
        );
        
        if ($result !== false) {
            wp_send_json_success(['message' => 'Settings updated']);
        } else {
            wp_send_json_error(['message' => 'Update failed']);
        }
    }
}

// Initialize
new Nehtw_Balance_API();

/**
 * Enqueue scripts for balance pages
 */
function nehtw_balance_enqueue_scripts() {
    if (is_page('transactions') || is_page('my-account')) {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);
        
        wp_localize_script('jquery', 'nehtwBalance', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nehtw_balance_nonce'),
            'userId' => get_current_user_id(),
            'restUrl' => rest_url('nehtw/v1/balance/')
        ]);
    }
}
add_action('wp_enqueue_scripts', 'nehtw_balance_enqueue_scripts');

