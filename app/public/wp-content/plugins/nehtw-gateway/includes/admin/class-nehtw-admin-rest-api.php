<?php

/**
 * Nehtw Admin REST API
 * 
 * Provides REST endpoints for admin dashboard
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 * 
 * INSTALLATION:
 * 1. Copy this file to: /wp-content/plugins/nehtw-gateway/includes/admin/class-nehtw-admin-rest-api.php
 * 2. Add to your main plugin file (nehtw-gateway.php):
 *    require_once plugin_dir_path(__FILE__) . 'includes/admin/class-nehtw-admin-rest-api.php';
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Nehtw_Admin_REST_API {
    
    private $namespace = 'nehtw/v1/admin';
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    /**
     * Register all REST API routes
     */
    public function register_routes() {
        
        // Get dashboard statistics
        register_rest_route($this->namespace, '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'get_stats'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'start_date' => [
                    'required' => false,
                    'default' => date('Y-m-d', strtotime('-30 days')),
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'end_date' => [
                    'required' => false,
                    'default' => date('Y-m-d'),
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Get realtime statistics
        register_rest_route($this->namespace, '/stats/realtime', [
            'methods' => 'GET',
            'callback' => [$this, 'get_realtime_stats'],
            'permission_callback' => [$this, 'check_admin_permission']
        ]);
        
        // Get recent orders
        register_rest_route($this->namespace, '/orders/recent', [
            'methods' => 'GET',
            'callback' => [$this, 'get_recent_orders'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'limit' => [
                    'required' => false,
                    'default' => 20,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Get all orders with pagination and filtering
        register_rest_route($this->namespace, '/orders', [
            'methods' => 'GET',
            'callback' => [$this, 'get_orders'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'page' => [
                    'required' => false,
                    'default' => 1,
                    'sanitize_callback' => 'absint'
                ],
                'per_page' => [
                    'required' => false,
                    'default' => 50,
                    'sanitize_callback' => 'absint'
                ],
                'status' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'provider' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'search' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
        
        // Get alerts
        register_rest_route($this->namespace, '/alerts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_alerts'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'unread_only' => [
                    'required' => false,
                    'default' => false,
                    'sanitize_callback' => 'rest_sanitize_boolean'
                ],
                'limit' => [
                    'required' => false,
                    'default' => 50,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Mark alert as read
        register_rest_route($this->namespace, '/alerts/(?P<id>\d+)/read', [
            'methods' => 'POST',
            'callback' => [$this, 'mark_alert_read'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Resolve alert
        register_rest_route($this->namespace, '/alerts/(?P<id>\d+)/resolve', [
            'methods' => 'POST',
            'callback' => [$this, 'resolve_alert'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Get provider performance
        register_rest_route($this->namespace, '/providers/performance', [
            'methods' => 'GET',
            'callback' => [$this, 'get_provider_performance'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'days' => [
                    'required' => false,
                    'default' => 30,
                    'sanitize_callback' => 'absint'
                ]
            ]
        ]);
        
        // Export orders to CSV
        register_rest_route($this->namespace, '/orders/export', [
            'methods' => 'GET',
            'callback' => [$this, 'export_orders'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args' => [
                'start_date' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'end_date' => [
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);
    }
    
    /**
     * Check if user has admin permission
     */
    public function check_admin_permission() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get dashboard statistics
     */
    public function get_stats($request) {
        $start_date = $request->get_param('start_date');
        $end_date = $request->get_param('end_date');
        
        $stats = Nehtw_Analytics_Engine::get_dashboard_stats($start_date, $end_date);
        
        return rest_ensure_response($stats);
    }
    
    /**
     * Get realtime statistics
     */
    public function get_realtime_stats($request) {
        $stats = Nehtw_Analytics_Engine::get_realtime_stats();
        
        return rest_ensure_response($stats);
    }
    
    /**
     * Get recent orders
     */
    public function get_recent_orders($request) {
        $limit = $request->get_param('limit');
        
        $orders = Nehtw_Analytics_Engine::get_recent_orders($limit);
        
        return rest_ensure_response($orders);
    }
    
    /**
     * Get orders with pagination and filtering
     */
    public function get_orders($request) {
        global $wpdb;
        
        $page = $request->get_param('page');
        $per_page = min($request->get_param('per_page'), 100); // Max 100 per page
        $status = $request->get_param('status');
        $provider = $request->get_param('provider');
        $search = $request->get_param('search');
        
        $offset = ($page - 1) * $per_page;
        
        // Build query
        $where = ['1=1'];
        $params = [];
        
        if ($status) {
            $where[] = 'status = %s';
            $params[] = $status;
        }
        
        if ($provider) {
            $where[] = 'site = %s';
            $params[] = $provider;
        }
        
        if ($search) {
            $where[] = '(task_id LIKE %s OR title LIKE %s OR stock_id LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        $where_clause = implode(' AND ', $where);
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_orders WHERE {$where_clause}";
        if (!empty($params)) {
            $total_query = $wpdb->prepare($total_query, $params);
        }
        $total = $wpdb->get_var($total_query);
        
        // Get orders
        $params[] = $per_page;
        $params[] = $offset;
        
        $orders_query = "
            SELECT o.*, u.user_email, u.display_name
            FROM {$wpdb->prefix}nehtw_orders o
            LEFT JOIN {$wpdb->prefix}users u ON o.user_id = u.ID
            WHERE {$where_clause}
            ORDER BY o.ordered_at DESC
            LIMIT %d OFFSET %d
        ";
        
        $orders = $wpdb->get_results($wpdb->prepare($orders_query, $params));
        
        return rest_ensure_response([
            'orders' => $orders,
            'pagination' => [
                'total' => (int) $total,
                'pages' => ceil($total / $per_page),
                'current_page' => $page,
                'per_page' => $per_page
            ]
        ]);
    }
    
    /**
     * Get alerts
     */
    public function get_alerts($request) {
        global $wpdb;
        
        $unread_only = $request->get_param('unread_only');
        $limit = $request->get_param('limit');
        
        $where = $unread_only ? 'WHERE is_read = FALSE' : '';
        
        $alerts = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}nehtw_alerts
            {$where}
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit));
        
        return rest_ensure_response($alerts);
    }
    
    /**
     * Mark alert as read
     */
    public function mark_alert_read($request) {
        global $wpdb;
        
        $alert_id = $request->get_param('id');
        
        $result = $wpdb->update(
            "{$wpdb->prefix}nehtw_alerts",
            ['is_read' => true],
            ['id' => $alert_id],
            ['%d'],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to update alert', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    /**
     * Resolve alert
     */
    public function resolve_alert($request) {
        global $wpdb;
        
        $alert_id = $request->get_param('id');
        $admin_id = get_current_user_id();
        
        $result = $wpdb->update(
            "{$wpdb->prefix}nehtw_alerts",
            [
                'is_resolved' => true,
                'resolved_at' => current_time('mysql'),
                'resolved_by' => $admin_id,
                'is_read' => true
            ],
            ['id' => $alert_id],
            ['%d', '%s', '%d', '%d'],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('update_failed', 'Failed to resolve alert', ['status' => 500]);
        }
        
        return rest_ensure_response(['success' => true]);
    }
    
    /**
     * Get provider performance
     */
    public function get_provider_performance($request) {
        global $wpdb;
        
        $days = $request->get_param('days');
        $start_date = date('Y-m-d', strtotime("-{$days} days"));
        $end_date = date('Y-m-d');
        
        $providers = $wpdb->get_results($wpdb->prepare("
            SELECT 
                provider,
                SUM(total_orders) as total_orders,
                SUM(successful_orders) as successful_orders,
                SUM(failed_orders) as failed_orders,
                AVG(success_rate) as avg_success_rate,
                AVG(avg_processing_time_seconds) as avg_processing_time,
                SUM(total_revenue) as total_revenue,
                MAX(is_active) as is_active,
                AVG(current_price) as avg_price
            FROM {$wpdb->prefix}nehtw_provider_stats
            WHERE date BETWEEN %s AND %s
            GROUP BY provider
            ORDER BY total_orders DESC
        ", $start_date, $end_date));
        
        return rest_ensure_response($providers);
    }
    
    /**
     * Export orders to CSV
     */
    public function export_orders($request) {
        global $wpdb;
        
        $start_date = $request->get_param('start_date') ?: date('Y-m-d', strtotime('-30 days'));
        $end_date = $request->get_param('end_date') ?: date('Y-m-d');
        
        $start_datetime = $start_date . ' 00:00:00';
        $end_datetime = $end_date . ' 23:59:59';
        
        $orders = $wpdb->get_results($wpdb->prepare("
            SELECT 
                o.task_id,
                o.ordered_at,
                o.completed_at,
                o.status,
                o.site as provider,
                o.title,
                o.cost,
                o.processing_time_seconds,
                o.error_message,
                u.user_email,
                u.display_name
            FROM {$wpdb->prefix}nehtw_orders o
            LEFT JOIN {$wpdb->prefix}users u ON o.user_id = u.ID
            WHERE o.ordered_at BETWEEN %s AND %s
            ORDER BY o.ordered_at DESC
        ", $start_datetime, $end_datetime), ARRAY_A);
        
        // Generate CSV
        $csv = "Task ID,Ordered At,Completed At,Status,Provider,Title,Cost,Processing Time (s),User Email,User Name,Error Message\n";
        
        foreach ($orders as $order) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,\"%s\",%s,%s,%s,%s,\"%s\"\n",
                $order['task_id'],
                $order['ordered_at'],
                $order['completed_at'] ?: '',
                $order['status'],
                $order['provider'],
                str_replace('"', '""', $order['title'] ?: ''),
                $order['cost'],
                $order['processing_time_seconds'] ?: '',
                $order['user_email'],
                $order['display_name'],
                str_replace('"', '""', $order['error_message'] ?: '')
            );
        }
        
        // Return CSV response
        $filename = sprintf('nehtw-orders-%s-to-%s.csv', $start_date, $end_date);
        
        return new WP_REST_Response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }
}

// Initialize REST API
new Nehtw_Admin_REST_API();

