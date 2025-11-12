<?php
/**
 * Nehtw Gateway Analytics
 * 
 * Analytics and reporting system for stock orders and user activity
 * 
 * @package Nehtw_Gateway
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Gateway_Analytics {
    
    /**
     * Initialize analytics system
     */
    public static function init() {
        // Register menu with priority 20 to ensure parent menu exists
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    }
    
    /**
     * Register admin menu
     */
    public static function register_menu() {
        add_submenu_page(
            'nehtw-gateway',
            __( 'Analytics', 'nehtw-gateway' ),
            __( 'Analytics', 'nehtw-gateway' ),
            'manage_options',
            'nehtw-gateway-analytics',
            array( __CLASS__, 'render_admin_page' )
        );
    }
    
    /**
     * Enqueue assets for analytics page
     */
    public static function enqueue_assets( $hook_suffix ) {
        // Only load on our analytics page
        // Hook suffix format: {parent-slug}_page_{submenu-slug}
        // For submenu under 'nehtw-gateway', it should be 'nehtw-gateway_page_nehtw-gateway-analytics'
        // Use strpos for more flexible matching
        if ( strpos( $hook_suffix, 'nehtw-gateway-analytics' ) === false ) {
            return;
        }
        
        // Enqueue Chart.js
        wp_enqueue_script(
            'nehtw-chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );
        
        // Enqueue analytics admin JS
        wp_enqueue_script(
            'nehtw-analytics-admin',
            NEHTW_GATEWAY_PLUGIN_URL . 'assets/js/nehtw-analytics-admin.js',
            array( 'nehtw-chartjs', 'wp-i18n' ),
            NEHTW_GATEWAY_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script(
            'nehtw-analytics-admin',
            'nehtwAnalyticsSettings',
            array(
                'restUrl' => esc_url_raw( rest_url( 'nehtw-gateway/v1/' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            )
        );
    }
    
    /**
     * Register REST API routes
     */
    public static function register_rest_routes() {
        register_rest_route(
            'nehtw-gateway/v1',
            '/analytics/summary',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'rest_summary' ),
                'permission_callback' => array( __CLASS__, 'admin_permissions' ),
            )
        );
        
        register_rest_route(
            'nehtw-gateway/v1',
            '/analytics/timeseries',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'rest_timeseries' ),
                'permission_callback' => array( __CLASS__, 'admin_permissions' ),
                'args'                => array(
                    'range' => array(
                        'type'              => 'string',
                        'default'           => '30d',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
        
        register_rest_route(
            'nehtw-gateway/v1',
            '/analytics/providers',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'rest_providers' ),
                'permission_callback' => array( __CLASS__, 'admin_permissions' ),
            )
        );
        
        register_rest_route(
            'nehtw-gateway/v1',
            '/analytics/top-users',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'rest_top_users' ),
                'permission_callback' => array( __CLASS__, 'admin_permissions' ),
            )
        );
        
        // User-facing analytics endpoints (for logged-in users to see their own stats)
        register_rest_route(
            'nehtw-gateway/v1',
            '/analytics/user',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'rest_user_analytics' ),
                'permission_callback' => array( __CLASS__, 'user_permissions' ),
            )
        );
        
        register_rest_route(
            'nehtw-gateway/v1',
            '/analytics/user/timeseries',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'rest_user_timeseries' ),
                'permission_callback' => array( __CLASS__, 'user_permissions' ),
                'args'                => array(
                    'range' => array(
                        'type'              => 'string',
                        'default'           => '30d',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );
    }
    
    /**
     * Check admin permissions
     */
    public static function admin_permissions() {
        return current_user_can( 'manage_options' );
    }
    
    /**
     * Check user permissions (logged in users can see their own analytics)
     */
    public static function user_permissions() {
        return is_user_logged_in();
    }
    
    /**
     * Render admin analytics page
     */
    public static function render_admin_page() {
        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
        }
        
        // Debug: Check if we're on the right page
        $current_page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
        
        ?>
        <div class="wrap nehtw-analytics">
            <h1><?php esc_html_e( 'Nehtw Gateway Analytics', 'nehtw-gateway' ); ?></h1>
            
            <div id="nehtw-analytics-kpis" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="postbox" style="padding: 15px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e( 'Total Downloads', 'nehtw-gateway' ); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;" id="kpi-total-downloads">-</p>
                </div>
                <div class="postbox" style="padding: 15px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e( 'Total Points', 'nehtw-gateway' ); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;" id="kpi-total-points">-</p>
                </div>
                <div class="postbox" style="padding: 15px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e( 'Success Rate', 'nehtw-gateway' ); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;" id="kpi-success-rate">-</p>
                </div>
                <div class="postbox" style="padding: 15px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e( 'Avg Processing Time', 'nehtw-gateway' ); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;" id="kpi-avg-processing">-</p>
                </div>
                <div class="postbox" style="padding: 15px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e( 'Last 30 Days', 'nehtw-gateway' ); ?></h3>
                    <p style="font-size: 24px; font-weight: bold; margin: 0;" id="kpi-last-30-days">-</p>
                </div>
            </div>
            
            <div class="postbox" style="padding: 20px; margin: 20px 0;">
                <h2><?php esc_html_e( 'Downloads & Points Over Time', 'nehtw-gateway' ); ?></h2>
                <div style="margin: 15px 0;">
                    <label>
                        <?php esc_html_e( 'Range:', 'nehtw-gateway' ); ?>
                        <select id="timeseries-range" style="margin-left: 10px;">
                            <option value="7d"><?php esc_html_e( 'Last 7 Days', 'nehtw-gateway' ); ?></option>
                            <option value="30d" selected><?php esc_html_e( 'Last 30 Days', 'nehtw-gateway' ); ?></option>
                            <option value="90d"><?php esc_html_e( 'Last 90 Days', 'nehtw-gateway' ); ?></option>
                        </select>
                    </label>
                </div>
                <canvas id="nehtw-downloads-chart" height="80"></canvas>
            </div>
            
            <div class="postbox" style="padding: 20px; margin: 20px 0;">
                <h2><?php esc_html_e( 'Provider Breakdown', 'nehtw-gateway' ); ?></h2>
                <canvas id="nehtw-provider-chart" height="80"></canvas>
            </div>
            
            <div class="postbox" style="padding: 20px; margin: 20px 0;">
                <h2><?php esc_html_e( 'Top Users', 'nehtw-gateway' ); ?></h2>
                <table class="widefat" id="nehtw-top-users-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'User', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Downloads', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Points Spent', 'nehtw-gateway' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 20px;">
                                <?php esc_html_e( 'Loading...', 'nehtw-gateway' ); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * REST endpoint: Summary statistics
     */
    public static function rest_summary( $request ) {
        global $wpdb;
        
        // Check cache first
        $cache_key = 'nehtw_analytics_summary';
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }
        
        $table = $wpdb->prefix . 'nehtw_stock_orders';
        
        // Get summary stats
        $results = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(CASE WHEN status IN ('completed', 'ready') THEN 1 END) AS total_downloads,
                    SUM(CASE WHEN status IN ('completed', 'ready') THEN cost_points ELSE 0 END) AS total_points,
                    COUNT(CASE WHEN status IN ('completed', 'ready') THEN 1 END) AS completed_count,
                    COUNT(CASE WHEN status IN ('failed', 'error') THEN 1 END) AS failed_count,
                    AVG(CASE WHEN status IN ('completed', 'ready') THEN TIMESTAMPDIFF(SECOND, created_at, COALESCE(updated_at, created_at)) END) AS avg_processing,
                    COUNT(CASE WHEN status IN ('completed', 'ready') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) AS last_30_days
                FROM {$table}"
            ),
            ARRAY_A
        );
        
        $total_downloads = (int) ( $results['total_downloads'] ?? 0 );
        $total_points = (float) ( $results['total_points'] ?? 0 );
        $completed = (int) ( $results['completed_count'] ?? 0 );
        $failed = (int) ( $results['failed_count'] ?? 0 );
        $total_attempts = $completed + $failed;
        $success_rate = $total_attempts > 0 ? ( $completed / $total_attempts ) * 100 : 0;
        $avg_processing = (float) ( $results['avg_processing'] ?? 0 );
        $last_30_days = (int) ( $results['last_30_days'] ?? 0 );
        
        $response = array(
            'total_downloads'        => $total_downloads,
            'total_points'           => $total_points,
            'success_rate'           => round( $success_rate, 2 ),
            'avg_processing_seconds' => round( $avg_processing, 2 ),
            'last_30_days_downloads' => $last_30_days,
        );
        
        // Cache for 5 minutes
        set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );
        
        return new WP_REST_Response( $response, 200 );
    }
    
    /**
     * REST endpoint: Time series data
     */
    public static function rest_timeseries( $request ) {
        global $wpdb;
        
        $range = $request->get_param( 'range' );
        $days = 30;
        
        if ( $range === '7d' ) {
            $days = 7;
        } elseif ( $range === '90d' ) {
            $days = 90;
        }
        
        $cache_key = 'nehtw_analytics_timeseries_' . $range;
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }
        
        $table = $wpdb->prefix . 'nehtw_stock_orders';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    DATE(created_at) AS day,
                    COUNT(*) AS downloads,
                    SUM(cost_points) AS points
                FROM {$table}
                WHERE status IN ('completed', 'ready')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY DATE(created_at)
                ORDER BY day ASC",
                $days
            ),
            ARRAY_A
        );
        
        $labels = array();
        $downloads = array();
        $points = array();
        
        foreach ( $results as $row ) {
            $labels[] = $row['day'];
            $downloads[] = (int) $row['downloads'];
            $points[] = (float) $row['points'];
        }
        
        $response = array(
            'labels'    => $labels,
            'downloads' => $downloads,
            'points'    => $points,
        );
        
        // Cache for 5 minutes
        set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );
        
        return new WP_REST_Response( $response, 200 );
    }
    
    /**
     * REST endpoint: Provider statistics
     */
    public static function rest_providers( $request ) {
        global $wpdb;
        
        $cache_key = 'nehtw_analytics_providers';
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }
        
        $table = $wpdb->prefix . 'nehtw_stock_orders';
        
        $results = $wpdb->get_results(
            "SELECT 
                site AS provider,
                COUNT(*) AS total,
                SUM(cost_points) AS points,
                SUM(CASE WHEN status IN ('completed', 'ready') THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN status IN ('failed', 'error') THEN 1 ELSE 0 END) AS failed
            FROM {$table}
            GROUP BY site
            ORDER BY total DESC",
            ARRAY_A
        );
        
        $providers = array();
        
        foreach ( $results as $row ) {
            $completed = (int) $row['completed'];
            $failed = (int) $row['failed'];
            $total_attempts = $completed + $failed;
            $failure_rate = $total_attempts > 0 ? ( $failed / $total_attempts ) * 100 : 0;
            
            $providers[] = array(
                'provider'     => $row['provider'] ?: 'unknown',
                'downloads'   => (int) $row['total'],
                'points'      => (float) $row['points'],
                'completed'   => $completed,
                'failed'      => $failed,
                'failure_rate' => round( $failure_rate, 2 ),
            );
        }
        
        // Cache for 5 minutes
        set_transient( $cache_key, $providers, 5 * MINUTE_IN_SECONDS );
        
        return new WP_REST_Response( $providers, 200 );
    }
    
    /**
     * REST endpoint: Top users
     */
    public static function rest_top_users( $request ) {
        global $wpdb;
        
        $cache_key = 'nehtw_analytics_top_users';
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }
        
        $table = $wpdb->prefix . 'nehtw_stock_orders';
        
        $results = $wpdb->get_results(
            "SELECT 
                user_id,
                COUNT(*) AS downloads,
                SUM(cost_points) AS points
            FROM {$table}
            WHERE status IN ('completed', 'ready')
            GROUP BY user_id
            ORDER BY downloads DESC
            LIMIT 20",
            ARRAY_A
        );
        
        $top_users = array();
        
        foreach ( $results as $row ) {
            $user = get_userdata( $row['user_id'] );
            $top_users[] = array(
                'user_id'   => (int) $row['user_id'],
                'name'      => $user ? $user->display_name : __( 'Unknown User', 'nehtw-gateway' ),
                'downloads' => (int) $row['downloads'],
                'points'    => (float) $row['points'],
            );
        }
        
        // Cache for 5 minutes
        set_transient( $cache_key, $top_users, 5 * MINUTE_IN_SECONDS );
        
        return new WP_REST_Response( $top_users, 200 );
    }
    
    /**
     * REST endpoint: User analytics (for logged-in users)
     */
    public static function rest_user_analytics( $request ) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'unauthorized', 'User must be logged in', array( 'status' => 401 ) );
        }
        
        // Check cache first (user-specific cache)
        $cache_key = 'nehtw_analytics_user_' . $user_id;
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }
        
        $table = $wpdb->prefix . 'nehtw_stock_orders';
        
        // Get user-specific stats
        $results = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(CASE WHEN status IN ('completed', 'ready') THEN 1 END) AS total_downloads,
                    SUM(CASE WHEN status IN ('completed', 'ready') THEN cost_points ELSE 0 END) AS total_points,
                    COUNT(CASE WHEN status IN ('completed', 'ready') THEN 1 END) AS completed_count,
                    COUNT(CASE WHEN status IN ('failed', 'error') THEN 1 END) AS failed_count,
                    COUNT(CASE WHEN status IN ('completed', 'ready') AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) AS last_30_days,
                    COUNT(CASE WHEN status IN ('completed', 'ready') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) AS last_7_days,
                    COUNT(CASE WHEN status IN ('completed', 'ready') AND DATE(created_at) = CURDATE() THEN 1 END) AS today
                FROM {$table}
                WHERE user_id = %d",
                $user_id
            ),
            ARRAY_A
        );
        
        // Get top provider for user
        $top_provider = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT site AS provider, COUNT(*) AS count
                FROM {$table}
                WHERE user_id = %d AND status IN ('completed', 'ready')
                GROUP BY site
                ORDER BY count DESC
                LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );
        
        $total_downloads = (int) ( $results['total_downloads'] ?? 0 );
        $total_points = (float) ( $results['total_points'] ?? 0 );
        $completed = (int) ( $results['completed_count'] ?? 0 );
        $failed = (int) ( $results['failed_count'] ?? 0 );
        $total_attempts = $completed + $failed;
        $success_rate = $total_attempts > 0 ? ( $completed / $total_attempts ) * 100 : 0;
        
        $response = array(
            'total_downloads'        => $total_downloads,
            'total_points'           => $total_points,
            'success_rate'           => round( $success_rate, 2 ),
            'last_30_days_downloads' => (int) ( $results['last_30_days'] ?? 0 ),
            'last_7_days_downloads'  => (int) ( $results['last_7_days'] ?? 0 ),
            'today_downloads'        => (int) ( $results['today'] ?? 0 ),
            'top_provider'           => $top_provider ? $top_provider['provider'] : null,
            'top_provider_count'     => $top_provider ? (int) $top_provider['count'] : 0,
        );
        
        // Cache for 5 minutes
        set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );
        
        return new WP_REST_Response( $response, 200 );
    }
    
    /**
     * REST endpoint: User time series data
     */
    public static function rest_user_timeseries( $request ) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return new WP_Error( 'unauthorized', 'User must be logged in', array( 'status' => 401 ) );
        }
        
        $range = $request->get_param( 'range' );
        $days = 30;
        
        if ( $range === '7d' ) {
            $days = 7;
        } elseif ( $range === '90d' ) {
            $days = 90;
        }
        
        $cache_key = 'nehtw_analytics_user_timeseries_' . $user_id . '_' . $range;
        $cached = get_transient( $cache_key );
        if ( $cached !== false ) {
            return new WP_REST_Response( $cached, 200 );
        }
        
        $table = $wpdb->prefix . 'nehtw_stock_orders';
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    DATE(created_at) AS day,
                    COUNT(*) AS downloads,
                    SUM(cost_points) AS points
                FROM {$table}
                WHERE user_id = %d
                  AND status IN ('completed', 'ready')
                  AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
                GROUP BY DATE(created_at)
                ORDER BY day ASC",
                $user_id,
                $days
            ),
            ARRAY_A
        );
        
        $labels = array();
        $downloads = array();
        $points = array();
        
        foreach ( $results as $row ) {
            $labels[] = $row['day'];
            $downloads[] = (int) $row['downloads'];
            $points[] = (float) $row['points'];
        }
        
        $response = array(
            'labels'    => $labels,
            'downloads' => $downloads,
            'points'    => $points,
        );
        
        // Cache for 5 minutes
        set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );
        
        return new WP_REST_Response( $response, 200 );
    }
}

// Initialize analytics
Nehtw_Gateway_Analytics::init();

