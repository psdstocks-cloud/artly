<?php
/**
 * Billing Cron Jobs
 * 
 * Handles scheduled tasks for billing system
 * 
 * @package Nehtw_Gateway
 * @subpackage Billing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Billing_Cron {
    
    /**
     * Initialize billing cron
     */
    public function __construct() {
        // Register cron schedules
        add_filter( 'cron_schedules', [ $this, 'register_schedules' ] );
        
        // Register cron hooks
        add_action( 'nehtw_process_payment_retries', [ $this, 'process_retries' ] );
        add_action( 'nehtw_check_dunning_schedule', [ $this, 'check_dunning' ] );
        add_action( 'nehtw_check_expiry_warnings', [ $this, 'check_expiry_warnings' ] );
        add_action( 'nehtw_process_subscription_renewals', [ $this, 'process_renewals' ] );
        
        // Schedule events on activation
        add_action( 'nehtw_gateway_activate', [ $this, 'schedule_events' ] );
        
        // Schedule events if not already scheduled
        $this->schedule_events();
    }
    
    /**
     * Register custom cron schedules
     * 
     * @param array $schedules Existing schedules
     * @return array Modified schedules
     */
    public function register_schedules( $schedules ) {
        $schedules['every_6_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __( 'Every 6 Hours', 'nehtw-gateway' ),
        ];
        
        $schedules['every_hour'] = [
            'interval' => HOUR_IN_SECONDS,
            'display' => __( 'Every Hour', 'nehtw-gateway' ),
        ];
        
        return $schedules;
    }
    
    /**
     * Schedule all billing cron events
     */
    public function schedule_events() {
        // Payment retries - every hour
        if ( ! wp_next_scheduled( 'nehtw_process_payment_retries' ) ) {
            wp_schedule_event( time(), 'every_hour', 'nehtw_process_payment_retries' );
        }
        
        // Dunning check - daily
        if ( ! wp_next_scheduled( 'nehtw_check_dunning_schedule' ) ) {
            wp_schedule_event( time(), 'daily', 'nehtw_check_dunning_schedule' );
        }
        
        // Expiry warnings - daily
        if ( ! wp_next_scheduled( 'nehtw_check_expiry_warnings' ) ) {
            wp_schedule_event( time(), 'daily', 'nehtw_check_expiry_warnings' );
        }
        
        // Subscription renewals - every 6 hours
        if ( ! wp_next_scheduled( 'nehtw_process_subscription_renewals' ) ) {
            wp_schedule_event( time(), 'every_6_hours', 'nehtw_process_subscription_renewals' );
        }
    }
    
    /**
     * Clear all scheduled events
     */
    public function clear_events() {
        wp_clear_scheduled_hook( 'nehtw_process_payment_retries' );
        wp_clear_scheduled_hook( 'nehtw_check_dunning_schedule' );
        wp_clear_scheduled_hook( 'nehtw_check_expiry_warnings' );
        wp_clear_scheduled_hook( 'nehtw_process_subscription_renewals' );
    }
    
    /**
     * Process payment retries
     * 
     * @return array Results
     */
    public function process_retries() {
        if ( ! class_exists( 'Nehtw_Payment_Retry' ) ) {
            return [ 'error' => 'Payment Retry class not found' ];
        }
        
        $retry_manager = new Nehtw_Payment_Retry();
        $results = $retry_manager->process_retry_queue();
        
        error_log( sprintf(
            '[Nehtw Cron] Payment retries processed: %d total, %d successful, %d failed',
            $results['processed'],
            $results['successful'],
            $results['failed']
        ) );
        
        return $results;
    }
    
    /**
     * Check and process dunning queue
     * 
     * @return array Results
     */
    public function check_dunning() {
        if ( ! class_exists( 'Nehtw_Dunning_Manager' ) ) {
            return [ 'error' => 'Dunning Manager class not found' ];
        }
        
        $dunning_manager = new Nehtw_Dunning_Manager();
        $results = $dunning_manager->process_dunning_queue();
        
        error_log( sprintf(
            '[Nehtw Cron] Dunning emails processed: %d total, %d sent',
            $results['processed'],
            $results['sent']
        ) );
        
        return $results;
    }
    
    /**
     * Check for subscriptions expiring soon and send warnings
     * 
     * @return array Results
     */
    public function check_expiry_warnings() {
        global $wpdb;
        
        $results = [
            'checked' => 0,
            'warnings_sent' => 0,
        ];
        
        // Check subscriptions expiring in 7, 3, and 1 days
        $warning_days = [ 7, 3, 1 ];
        
        foreach ( $warning_days as $days ) {
            $expiry_date = date( 'Y-m-d H:i:s', strtotime( "+{$days} days" ) );
            
            $expiring_subscriptions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}nehtw_subscriptions
                     WHERE status = %s
                     AND next_renewal_at BETWEEN %s AND %s
                     AND cancelled_at IS NULL",
                    'active',
                    date( 'Y-m-d 00:00:00', strtotime( "+{$days} days" ) ),
                    date( 'Y-m-d 23:59:59', strtotime( "+{$days} days" ) )
                ),
                ARRAY_A
            );
            
            foreach ( $expiring_subscriptions as $subscription ) {
                $results['checked']++;
                
                // Check if warning already sent
                $warning_sent = get_user_meta( $subscription['user_id'], '_nehtw_expiry_warning_' . $days . '_sent', true );
                
                if ( ! $warning_sent ) {
                    // Send expiry warning email
                    $this->send_expiry_warning( $subscription, $days );
                    update_user_meta( $subscription['user_id'], '_nehtw_expiry_warning_' . $days . '_sent', true );
                    $results['warnings_sent']++;
                }
            }
        }
        
        error_log( sprintf(
            '[Nehtw Cron] Expiry warnings checked: %d subscriptions, %d warnings sent',
            $results['checked'],
            $results['warnings_sent']
        ) );
        
        return $results;
    }
    
    /**
     * Process subscription renewals
     * 
     * @return array Results
     */
    public function process_renewals() {
        global $wpdb;

        $results = [
            'processed' => 0,
            'overdue_marked' => 0,
            'errors' => [],
        ];

        $table = nehtw_gateway_get_subscriptions_table();
        if ( ! $table ) {
            return $results;
        }

        $tolerance = apply_filters( 'nehtw_subscription_overdue_tolerance', 6 * HOUR_IN_SECONDS );
        $threshold = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp', true ) - $tolerance );

        $due_subscriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 WHERE status = %s
                 AND next_renewal_at <= %s
                 AND cancelled_at IS NULL",
                'active',
                $threshold
            ),
            ARRAY_A
        );

        foreach ( (array) $due_subscriptions as $subscription ) {
            $results['processed']++;
            try {
                $latest_invoice = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT paid_at, status FROM {$wpdb->prefix}nehtw_invoices WHERE subscription_id = %d ORDER BY id DESC LIMIT 1",
                        $subscription['id']
                    ),
                    ARRAY_A
                );

                $needs_attention = true;
                if ( $latest_invoice && 'paid' === $latest_invoice['status'] && ! empty( $latest_invoice['paid_at'] ) ) {
                    $paid_ts = strtotime( $latest_invoice['paid_at'] );
                    $next_ts = strtotime( $subscription['next_renewal_at'] );
                    if ( $paid_ts && $next_ts && $paid_ts >= $next_ts ) {
                        $needs_attention = false;
                    }
                }

                if ( $needs_attention ) {
                    $wpdb->update(
                        $table,
                        [
                            'status'     => 'overdue',
                            'updated_at' => current_time( 'mysql' ),
                        ],
                        [ 'id' => $subscription['id'] ],
                        [ '%s', '%s' ],
                        [ '%d' ]
                    );
                    $results['overdue_marked']++;
                }
            } catch ( Exception $e ) {
                $results['errors'][] = [
                    'subscription_id' => $subscription['id'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        update_option( 'nehtw_billing_cron_last_run', current_time( 'mysql' ) );

        error_log( sprintf(
            '[Nehtw Cron] Renewal audit complete: %d inspected, %d marked overdue, %d errors',
            $results['processed'],
            $results['overdue_marked'],
            count( $results['errors'] )
        ) );

        return $results;
    }
    
    /**
     * Send expiry warning email
     * 
     * @param array $subscription Subscription data
     * @param int $days Days until expiry
     * @return bool Success
     */
    private function send_expiry_warning( $subscription, $days ) {
        $user = get_userdata( $subscription['user_id'] );
        if ( ! $user ) {
            return false;
        }
        
        $plans = nehtw_gateway_get_subscription_plans();
        $plan = isset( $plans[ $subscription['plan_key'] ] ) ? $plans[ $subscription['plan_key'] ] : null;
        $plan_name = isset( $plan['name'] ) ? $plan['name'] : $subscription['plan_key'];
        
        $subject = sprintf(
            'Your %s subscription expires in %d day%s',
            $plan_name,
            $days,
            $days > 1 ? 's' : ''
        );
        
        $message = sprintf(
            "Hi %s,\n\nYour %s subscription will expire in %d day%s.\n\n",
            $user->display_name,
            $plan_name,
            $days,
            $days > 1 ? 's' : ''
        );
        
        $message .= "To continue enjoying uninterrupted service, please ensure your payment method is up to date.\n\n";
        $message .= "You can manage your subscription here: " . get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) . "\n\n";
        $message .= "Best regards,\nThe " . get_bloginfo( 'name' ) . " Team";
        
        return wp_mail( $user->user_email, $subject, $message );
    }
}

// Initialize
new Nehtw_Billing_Cron();

