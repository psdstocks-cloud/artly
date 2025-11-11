<?php
/**
 * Subscription REST API Endpoints
 * 
 * Provides REST API endpoints for subscription management
 * 
 * @package Nehtw_Gateway
 * @subpackage Billing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Subscription_REST_API {
    
    /**
     * Initialize REST API
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }
    
    /**
     * Register REST routes
     */
    public function register_routes() {
        $namespace = 'nehtw/v1';
        
        // Get subscription details
        register_rest_route( $namespace, '/subscription', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_subscription' ],
            'permission_callback' => [ $this, 'check_auth' ],
        ] );
        
        // Change plan
        register_rest_route( $namespace, '/subscription/change-plan', [
            'methods' => 'POST',
            'callback' => [ $this, 'change_plan' ],
            'permission_callback' => [ $this, 'check_auth' ],
            'args' => [
                'new_plan_key' => [
                    'required' => true,
                    'type' => 'string',
                    'validate_callback' => function( $param ) {
                        return ! empty( $param );
                    },
                ],
                'apply_immediately' => [
                    'default' => true,
                    'type' => 'boolean',
                ],
            ],
        ] );
        
        // Pause subscription
        register_rest_route( $namespace, '/subscription/pause', [
            'methods' => 'POST',
            'callback' => [ $this, 'pause_subscription' ],
            'permission_callback' => [ $this, 'check_auth' ],
        ] );
        
        // Resume subscription
        register_rest_route( $namespace, '/subscription/resume', [
            'methods' => 'POST',
            'callback' => [ $this, 'resume_subscription' ],
            'permission_callback' => [ $this, 'check_auth' ],
        ] );
        
        // Cancel subscription
        register_rest_route( $namespace, '/subscription/cancel', [
            'methods' => 'POST',
            'callback' => [ $this, 'cancel_subscription' ],
            'permission_callback' => [ $this, 'check_auth' ],
            'args' => [
                'reason' => [
                    'default' => null,
                    'type' => 'string',
                ],
                'cancel_immediately' => [
                    'default' => false,
                    'type' => 'boolean',
                ],
            ],
        ] );
        
        // Get billing history
        register_rest_route( $namespace, '/invoices', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_invoices' ],
            'permission_callback' => [ $this, 'check_auth' ],
            'args' => [
                'limit' => [
                    'default' => 10,
                    'type' => 'integer',
                ],
                'offset' => [
                    'default' => 0,
                    'type' => 'integer',
                ],
                'status' => [
                    'default' => null,
                    'type' => 'string',
                ],
            ],
        ] );
        
        // Download invoice
        register_rest_route( $namespace, '/invoices/(?P<id>\d+)/download', [
            'methods' => 'GET',
            'callback' => [ $this, 'download_invoice' ],
            'permission_callback' => [ $this, 'check_auth' ],
        ] );
        
        // Get usage data
        register_rest_route( $namespace, '/usage', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_usage' ],
            'permission_callback' => [ $this, 'check_auth' ],
        ] );
        
        // Get available plans
        register_rest_route( $namespace, '/plans', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_plans' ],
            'permission_callback' => '__return_true',
        ] );
    }
    
    /**
     * Check authentication
     * 
     * @return bool
     */
    public function check_auth() {
        return is_user_logged_in();
    }
    
    /**
     * Get subscription details
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function get_subscription( $request ) {
        $user_id = get_current_user_id();
        $subscription = nehtw_gateway_get_user_active_subscription( $user_id );
        
        if ( ! $subscription ) {
            return new WP_Error( 'no_subscription', 'No active subscription found', [ 'status' => 404 ] );
        }
        
        // Get additional data
        $plans = nehtw_gateway_get_subscription_plans();
        $plan = isset( $plans[ $subscription['plan_key'] ] ) ? $plans[ $subscription['plan_key'] ] : null;
        
        // Get usage
        $usage_tracker = new Nehtw_Usage_Tracker();
        $usage = $usage_tracker->get_usage_summary( $user_id );
        
        return rest_ensure_response( [
            'subscription' => $subscription,
            'plan' => $plan,
            'usage' => $usage,
        ] );
    }
    
    /**
     * Change subscription plan
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function change_plan( $request ) {
        $user_id = get_current_user_id();
        $subscription = nehtw_gateway_get_user_active_subscription( $user_id );
        
        if ( ! $subscription ) {
            return new WP_Error( 'no_subscription', 'No active subscription found', [ 'status' => 404 ] );
        }
        
        $manager = new Nehtw_Subscription_Manager();
        $result = $manager->change_plan(
            $subscription['id'],
            $request['new_plan_key'],
            [
                'apply_immediately' => $request['apply_immediately'],
            ]
        );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( $result );
    }
    
    /**
     * Pause subscription
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function pause_subscription( $request ) {
        $user_id = get_current_user_id();
        $subscription = nehtw_gateway_get_user_active_subscription( $user_id );
        
        if ( ! $subscription ) {
            return new WP_Error( 'no_subscription', 'No active subscription found', [ 'status' => 404 ] );
        }
        
        $manager = new Nehtw_Subscription_Manager();
        $result = $manager->pause_subscription( $subscription['id'] );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( [ 'success' => true ] );
    }
    
    /**
     * Resume subscription
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function resume_subscription( $request ) {
        $user_id = get_current_user_id();
        $subscription = nehtw_gateway_get_user_active_subscription( $user_id );
        
        if ( ! $subscription ) {
            return new WP_Error( 'no_subscription', 'No active subscription found', [ 'status' => 404 ] );
        }
        
        $manager = new Nehtw_Subscription_Manager();
        $result = $manager->resume_subscription( $subscription['id'] );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( [ 'success' => true ] );
    }
    
    /**
     * Cancel subscription
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function cancel_subscription( $request ) {
        $user_id = get_current_user_id();
        $subscription = nehtw_gateway_get_user_active_subscription( $user_id );
        
        if ( ! $subscription ) {
            return new WP_Error( 'no_subscription', 'No active subscription found', [ 'status' => 404 ] );
        }
        
        $manager = new Nehtw_Subscription_Manager();
        $result = $manager->cancel_subscription(
            $subscription['id'],
            [
                'reason' => $request['reason'],
                'cancel_immediately' => $request['cancel_immediately'],
            ]
        );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        return rest_ensure_response( [ 'success' => true ] );
    }
    
    /**
     * Get user invoices
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_invoices( $request ) {
        $user_id = get_current_user_id();
        
        $invoice_manager = new Nehtw_Invoice_Manager();
        $invoices = $invoice_manager->get_user_invoices( $user_id, [
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'status' => $request['status'],
        ] );
        
        return rest_ensure_response( $invoices );
    }
    
    /**
     * Download invoice PDF
     * 
     * @param WP_REST_Request $request
     * @return void
     */
    public function download_invoice( $request ) {
        $invoice_id = $request['id'];
        $user_id = get_current_user_id();
        
        $invoice_manager = new Nehtw_Invoice_Manager();
        $invoice_manager->download_invoice( $invoice_id, $user_id );
        
        exit;
    }
    
    /**
     * Get usage data
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_usage( $request ) {
        $user_id = get_current_user_id();
        
        $usage_tracker = new Nehtw_Usage_Tracker();
        $usage = $usage_tracker->get_usage_summary( $user_id );
        $usage_details = $usage_tracker->get_period_usage( $user_id );
        
        return rest_ensure_response( [
            'summary' => $usage,
            'details' => $usage_details,
        ] );
    }
    
    /**
     * Get available plans
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function get_plans( $request ) {
        $plans = nehtw_gateway_get_subscription_plans();
        return rest_ensure_response( $plans );
    }
}

// Initialize
new Nehtw_Subscription_REST_API();

