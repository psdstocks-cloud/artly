<?php

/**
 * Nehtw Analytics Engine
 * 
 * Aggregates and calculates metrics for admin dashboard
 * Runs daily aggregation via WP-Cron
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 * 
 * INSTALLATION:
 * 1. Copy this file to: /wp-content/plugins/nehtw-gateway/includes/admin/class-nehtw-analytics-engine.php
 * 2. Add to your main plugin file (nehtw-gateway.php):
 *    require_once plugin_dir_path(__FILE__) . 'includes/admin/class-nehtw-analytics-engine.php';
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Nehtw_Analytics_Engine {
    
    /**
     * Initialize and schedule daily aggregation
     */
    public static function init() {
        // Schedule daily aggregation at 1am
        add_action('nehtw_daily_aggregation', [__CLASS__, 'aggregate_daily_stats']);
        
        if (!wp_next_scheduled('nehtw_daily_aggregation')) {
            $tomorrow_1am = strtotime('tomorrow 1:00am');
            wp_schedule_event($tomorrow_1am, 'daily', 'nehtw_daily_aggregation');
            error_log('Nehtw Analytics: Scheduled daily aggregation (1am)');
        }
    }
    
    /**
     * Aggregate statistics for a specific date
     * Run via cron daily, or manually for backfilling data
     * 
     * @param string|null $date Date in Y-m-d format (default: yesterday)
     */
    public static function aggregate_daily_stats($date = null) {
        global $wpdb;
        
        if (!$date) {
            $date = date('Y-m-d', strtotime('-1 day')); // Yesterday
        }
        
        $start_datetime = $date . ' 00:00:00';
        $end_datetime = $date . ' 23:59:59';
        
        error_log("Nehtw Analytics: Aggregating stats for {$date}");
        
        // Get order statistics
        $order_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status IN ('ready', 'downloaded') THEN 1 ELSE 0 END) as successful_orders,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(cost) as total_revenue,
                AVG(processing_time_seconds) as avg_processing_time,
                COUNT(DISTINCT user_id) as unique_users
            FROM {$wpdb->prefix}nehtw_orders
            WHERE ordered_at BETWEEN %s AND %s
        ", $start_datetime, $end_datetime));
        
        // Calculate median processing time
        $median_time = self::calculate_median_processing_time($start_datetime, $end_datetime);
        
        // Count new users (users making their first order on this date)
        $new_users = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->prefix}nehtw_orders o1
            WHERE ordered_at BETWEEN %s AND %s
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->prefix}nehtw_orders o2
                WHERE o2.user_id = o1.user_id
                AND o2.ordered_at < %s
            )
        ", $start_datetime, $end_datetime, $start_datetime));
        
        // Get provider breakdown
        $provider_stats = $wpdb->get_results($wpdb->prepare("
            SELECT 
                site as provider,
                COUNT(*) as orders,
                SUM(cost) as revenue,
                SUM(CASE WHEN status IN ('ready', 'downloaded') THEN 1 ELSE 0 END) as successful
            FROM {$wpdb->prefix}nehtw_orders
            WHERE ordered_at BETWEEN %s AND %s
            GROUP BY site
            ORDER BY orders DESC
        ", $start_datetime, $end_datetime), ARRAY_A);
        
        // Insert or update analytics record
        $wpdb->replace(
            "{$wpdb->prefix}nehtw_analytics_daily",
            [
                'date' => $date,
                'total_orders' => (int) $order_stats->total_orders,
                'successful_orders' => (int) $order_stats->successful_orders,
                'failed_orders' => (int) $order_stats->failed_orders,
                'cancelled_orders' => (int) $order_stats->cancelled_orders,
                'total_revenue' => (float) $order_stats->total_revenue,
                'avg_processing_time_seconds' => (int) round($order_stats->avg_processing_time),
                'median_processing_time_seconds' => (int) $median_time,
                'unique_users' => (int) $order_stats->unique_users,
                'new_users' => (int) $new_users,
                'provider_breakdown' => json_encode($provider_stats),
                'updated_at' => current_time('mysql')
            ]
        );
        
        error_log(sprintf(
            'Nehtw Analytics: Aggregated %d orders, %s revenue for %s',
            $order_stats->total_orders,
            $order_stats->total_revenue,
            $date
        ));
        
        // Also aggregate provider-specific stats
        self::aggregate_provider_stats($date, $provider_stats);
        
        return true;
    }
    
    /**
     * Calculate median processing time
     */
    private static function calculate_median_processing_time($start_datetime, $end_datetime) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare("
            SELECT processing_time_seconds
            FROM {$wpdb->prefix}nehtw_orders
            WHERE ordered_at BETWEEN %s AND %s
            AND processing_time_seconds IS NOT NULL
            ORDER BY processing_time_seconds
            LIMIT 1 OFFSET (
                SELECT FLOOR(COUNT(*) / 2) 
                FROM {$wpdb->prefix}nehtw_orders
                WHERE ordered_at BETWEEN %s AND %s
                AND processing_time_seconds IS NOT NULL
            )
        ", $start_datetime, $end_datetime, $start_datetime, $end_datetime));
    }
    
    /**
     * Aggregate provider-specific statistics
     */
    private static function aggregate_provider_stats($date, $provider_stats = null) {
        global $wpdb;
        
        $start_datetime = $date . ' 00:00:00';
        $end_datetime = $date . ' 23:59:59';
        
        // If provider stats not provided, fetch them
        if ($provider_stats === null) {
            $provider_stats = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT site as provider FROM {$wpdb->prefix}nehtw_orders
                WHERE ordered_at BETWEEN %s AND %s
            ", $start_datetime, $end_datetime), ARRAY_A);
        }
        
        foreach ($provider_stats as $provider_data) {
            $provider = $provider_data['provider'];
            
            // Get detailed stats for this provider
            $stats = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status IN ('ready', 'downloaded') THEN 1 ELSE 0 END) as successful_orders,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_orders,
                    SUM(cost) as total_revenue,
                    AVG(processing_time_seconds) as avg_processing_time,
                    MIN(processing_time_seconds) as min_processing_time,
                    MAX(processing_time_seconds) as max_processing_time
                FROM {$wpdb->prefix}nehtw_orders
                WHERE site = %s
                AND ordered_at BETWEEN %s AND %s
            ", $provider, $start_datetime, $end_datetime));
            
            // Calculate success rate
            $success_rate = $stats->total_orders > 0 
                ? ($stats->successful_orders / $stats->total_orders) * 100 
                : 0;
            
            // Get current provider info from API
            $provider_info = self::get_provider_info($provider);
            
            // Insert/update provider stats
            $wpdb->replace(
                "{$wpdb->prefix}nehtw_provider_stats",
                [
                    'provider' => $provider,
                    'date' => $date,
                    'total_orders' => (int) $stats->total_orders,
                    'successful_orders' => (int) $stats->successful_orders,
                    'failed_orders' => (int) $stats->failed_orders,
                    'success_rate' => (float) round($success_rate, 2),
                    'total_revenue' => (float) $stats->total_revenue,
                    'avg_processing_time_seconds' => (int) round($stats->avg_processing_time),
                    'min_processing_time_seconds' => (int) $stats->min_processing_time,
                    'max_processing_time_seconds' => (int) $stats->max_processing_time,
                    'is_active' => (bool) ($provider_info['active'] ?? true),
                    'current_price' => (float) ($provider_info['price'] ?? null),
                    'updated_at' => current_time('mysql')
                ]
            );
            
            // Create alert if success rate is low (and enough orders to be significant)
            if ($success_rate < 80 && $stats->total_orders >= 10) {
                self::create_low_success_alert($provider, $success_rate, $stats, $date);
            }
        }
    }
    
    /**
     * Get provider info from Nehtw API (with caching)
     */
    private static function get_provider_info($provider) {
        static $provider_data = null;
        
        // Cache API response for this request
        if ($provider_data === null) {
            $api_key = get_option('nehtw_api_key') ?: get_option('nehtw_gateway_api_key');
            
            if (empty($api_key)) {
                return [];
            }
            
            $response = wp_remote_get('https://nehtw.com/api/stocksites', [
                'headers' => ['X-Api-Key' => $api_key],
                'timeout' => 10
            ]);
            
            if (!is_wp_error($response)) {
                $provider_data = json_decode(wp_remote_retrieve_body($response), true);
            } else {
                $provider_data = [];
            }
        }
        
        return $provider_data[$provider] ?? [];
    }
    
    /**
     * Create alert for low provider success rate
     */
    private static function create_low_success_alert($provider, $success_rate, $stats, $date) {
        global $wpdb;
        
        $wpdb->insert(
            "{$wpdb->prefix}nehtw_alerts",
            [
                'alert_type' => 'high_failure_rate',
                'severity' => 'warning',
                'title' => sprintf('Low Success Rate: %s', ucfirst($provider)),
                'message' => sprintf(
                    '%s has a success rate of %.1f%% on %s (%d successful out of %d orders)',
                    ucfirst($provider),
                    $success_rate,
                    $date,
                    $stats->successful_orders,
                    $stats->total_orders
                ),
                'related_provider' => $provider,
                'metadata' => json_encode([
                    'success_rate' => $success_rate,
                    'total_orders' => $stats->total_orders,
                    'failed_orders' => $stats->failed_orders,
                    'date' => $date
                ]),
                'created_at' => current_time('mysql')
            ]
        );
    }
    
    /**
     * Get dashboard stats for a date range
     * Used by admin dashboard to display metrics
     */
    public static function get_dashboard_stats($start_date, $end_date) {
        global $wpdb;
        
        // Get aggregated stats from analytics table (fast)
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(total_orders) as total_orders,
                SUM(successful_orders) as successful_orders,
                SUM(failed_orders) as failed_orders,
                SUM(total_revenue) as total_revenue,
                AVG(avg_processing_time_seconds) as avg_processing_time,
                SUM(unique_users) as total_unique_users,
                SUM(new_users) as total_new_users
            FROM {$wpdb->prefix}nehtw_analytics_daily
            WHERE date BETWEEN %s AND %s
        ", $start_date, $end_date));
        
        // Calculate success rate
        $success_rate = $stats->total_orders > 0 
            ? ($stats->successful_orders / $stats->total_orders) * 100 
            : 0;
        
        // Get top providers for this period
        $top_providers = $wpdb->get_results($wpdb->prepare("
            SELECT 
                provider,
                SUM(total_orders) as orders,
                SUM(total_revenue) as revenue,
                AVG(success_rate) as avg_success_rate,
                AVG(avg_processing_time_seconds) as avg_time
            FROM {$wpdb->prefix}nehtw_provider_stats
            WHERE date BETWEEN %s AND %s
            GROUP BY provider
            ORDER BY orders DESC
            LIMIT 10
        ", $start_date, $end_date));
        
        // Get daily trend
        $daily_trend = $wpdb->get_results($wpdb->prepare("
            SELECT 
                date,
                total_orders,
                successful_orders,
                failed_orders,
                total_revenue
            FROM {$wpdb->prefix}nehtw_analytics_daily
            WHERE date BETWEEN %s AND %s
            ORDER BY date ASC
        ", $start_date, $end_date));
        
        return [
            'summary' => [
                'total_orders' => (int) $stats->total_orders,
                'successful_orders' => (int) $stats->successful_orders,
                'failed_orders' => (int) $stats->failed_orders,
                'total_revenue' => (float) $stats->total_revenue,
                'success_rate' => (float) round($success_rate, 2),
                'avg_processing_time' => (int) round($stats->avg_processing_time),
                'unique_users' => (int) $stats->total_unique_users,
                'new_users' => (int) $stats->total_new_users
            ],
            'top_providers' => $top_providers,
            'daily_trend' => $daily_trend
        ];
    }
    
    /**
     * Get real-time statistics (not aggregated, live from orders table)
     * Used for dashboard widgets showing current status
     */
    public static function get_realtime_stats() {
        global $wpdb;
        
        return [
            'pending_orders' => (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_orders 
                WHERE status = 'pending'
            "),
            'processing_orders' => (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_orders 
                WHERE status = 'processing'
            "),
            'orders_today' => (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_orders 
                WHERE DATE(ordered_at) = CURDATE()
            "),
            'revenue_today' => (float) $wpdb->get_var("
                SELECT COALESCE(SUM(cost), 0) FROM {$wpdb->prefix}nehtw_orders 
                WHERE DATE(ordered_at) = CURDATE()
            "),
            'successful_today' => (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_orders 
                WHERE DATE(ordered_at) = CURDATE()
                AND status IN ('ready', 'downloaded')
            "),
            'failed_today' => (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_orders 
                WHERE DATE(ordered_at) = CURDATE()
                AND status = 'failed'
            "),
            'unread_alerts' => (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_alerts 
                WHERE is_read = FALSE
            "),
            'critical_alerts' => (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_alerts 
                WHERE is_read = FALSE AND severity = 'critical'
            "),
            'active_users_today' => (int) $wpdb->get_var("
                SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}nehtw_orders 
                WHERE DATE(ordered_at) = CURDATE()
            ")
        ];
    }
    
    /**
     * Get recent orders (for live order feed)
     */
    public static function get_recent_orders($limit = 20) {
        global $wpdb;
        
        $orders = $wpdb->get_results($wpdb->prepare("
            SELECT 
                o.*,
                u.user_email,
                u.display_name
            FROM {$wpdb->prefix}nehtw_orders o
            LEFT JOIN {$wpdb->prefix}users u ON o.user_id = u.ID
            ORDER BY o.ordered_at DESC
            LIMIT %d
        ", $limit));
        
        return $orders;
    }
    
    /**
     * Backfill analytics for missing dates
     * Useful if you're installing this on existing data
     */
    public static function backfill_analytics($days = 30) {
        $dates_processed = 0;
        
        for ($i = 1; $i <= $days; $i++) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            
            self::aggregate_daily_stats($date);
            $dates_processed++;
            
            // Sleep to avoid overwhelming the server
            usleep(100000); // 0.1 seconds
        }
        
        error_log("Nehtw Analytics: Backfilled {$dates_processed} days of analytics");
        
        return $dates_processed;
    }
}

// Initialize analytics engine
Nehtw_Analytics_Engine::init();

// Add admin action to manually trigger aggregation
add_action('admin_post_nehtw_manual_aggregate', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $date = $_GET['date'] ?? date('Y-m-d', strtotime('-1 day'));
    Nehtw_Analytics_Engine::aggregate_daily_stats($date);
    
    wp_redirect(admin_url('admin.php?page=nehtw-dashboard&aggregated=1'));
    exit;
});

// Add admin action to backfill analytics
add_action('admin_post_nehtw_backfill_analytics', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    $days = (int) ($_GET['days'] ?? 30);
    Nehtw_Analytics_Engine::backfill_analytics($days);
    
    wp_redirect(admin_url('admin.php?page=nehtw-dashboard&backfilled=' . $days));
    exit;
});

