<?php
/**
 * Subscription Manager
 * 
 * Handles subscription plan changes, pausing, cancellation, and proration
 * 
 * @package Nehtw_Gateway
 * @subpackage Billing
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Subscription_Manager {
    
    /**
     * Change types
     */
    const CHANGE_UPGRADE = 'upgrade';
    const CHANGE_DOWNGRADE = 'downgrade';
    const CHANGE_SAME_TIER = 'same_tier';
    
    /**
     * Initialize subscription manager
     */
    public function __construct() {
        // Register actions
        add_action( 'nehtw_subscription_plan_changed', [ $this, 'handle_plan_change' ], 10, 3 );
        add_action( 'nehtw_subscription_paused', [ $this, 'handle_subscription_paused' ], 10, 2 );
        add_action( 'nehtw_subscription_resumed', [ $this, 'handle_subscription_resumed' ], 10, 2 );
        add_action( 'nehtw_subscription_cancelled', [ $this, 'handle_subscription_cancelled' ], 10, 2 );
    }
    
    /**
     * Change subscription plan
     * 
     * @param int $subscription_id Subscription ID
     * @param string $new_plan_key New plan key
     * @param array $options Change options
     * @return array|WP_Error Change result or error
     */
    public function change_plan( $subscription_id, $new_plan_key, $options = [] ) {
        global $wpdb;
        
        // Get subscription
        $subscription = $this->get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return new WP_Error( 'invalid_subscription', 'Subscription not found' );
        }
        
        // Check if subscription can be changed
        if ( $subscription['status'] !== 'active' ) {
            return new WP_Error( 'invalid_status', 'Subscription must be active to change plans' );
        }
        
        // Get plans
        $plans = nehtw_gateway_get_subscription_plans();
        
        if ( ! isset( $plans[ $new_plan_key ] ) ) {
            return new WP_Error( 'invalid_plan', 'New plan not found' );
        }
        
        $old_plan = isset( $plans[ $subscription['plan_key'] ] ) ? $plans[ $subscription['plan_key'] ] : null;
        $new_plan = $plans[ $new_plan_key ];
        
        // Determine change type
        $change_type = $this->determine_change_type( $old_plan, $new_plan );
        
        // Default options
        $defaults = [
            'apply_immediately' => true,
            'prorate' => true,
            'send_email' => true,
        ];
        
        $options = wp_parse_args( $options, $defaults );
        
        // Calculate proration if applicable
        $proration = null;
        if ( $options['prorate'] && $old_plan ) {
            $proration = $this->calculate_proration( $subscription, $old_plan, $new_plan );
        }
        
        // Apply change immediately or at next renewal
        if ( $options['apply_immediately'] ) {
            $result = $this->apply_plan_change_immediately( $subscription, $new_plan_key, $proration );
        } else {
            $result = $this->schedule_plan_change( $subscription, $new_plan_key );
        }
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // Record history
        $this->record_plan_change_history( $subscription_id, [
            'old_plan_key' => $subscription['plan_key'],
            'new_plan_key' => $new_plan_key,
            'change_type' => $change_type,
            'proration' => $proration,
            'applied_immediately' => $options['apply_immediately'],
        ] );
        
        // Send notification email
        if ( $options['send_email'] ) {
            $this->send_plan_change_email( $subscription_id, $change_type, $old_plan, $new_plan, $proration );
        }
        
        // Trigger action
        do_action( 'nehtw_subscription_plan_changed', $subscription_id, $new_plan_key, $change_type );
        
        return [
            'success' => true,
            'subscription_id' => $subscription_id,
            'old_plan' => $subscription['plan_key'],
            'new_plan' => $new_plan_key,
            'change_type' => $change_type,
            'proration' => $proration,
            'effective_date' => $result['effective_date'],
        ];
    }
    
    /**
     * Calculate proration for plan change
     * 
     * @param array $subscription Subscription data
     * @param array $old_plan Old plan data
     * @param array $new_plan New plan data
     * @return array Proration details
     */
    public function calculate_proration( $subscription, $old_plan, $new_plan ) {
        // Get days remaining in current billing period
        $today = new DateTime();
        $renewal_date = new DateTime( $subscription['next_renewal_at'] );
        $days_remaining = $today->diff( $renewal_date )->days;
        
        // Get billing interval in days
        $interval_days = $subscription['interval'] === 'year' ? 365 : 30;
        
        // Calculate daily rates
        $old_daily_rate = isset( $old_plan['price'] ) ? floatval( $old_plan['price'] ) / $interval_days : 0;
        $new_daily_rate = isset( $new_plan['price'] ) ? floatval( $new_plan['price'] ) / $interval_days : 0;
        
        // Calculate unused amount from old plan
        $unused_amount = $old_daily_rate * $days_remaining;
        
        // Calculate amount for new plan for remaining days
        $new_amount = $new_daily_rate * $days_remaining;
        
        // Calculate difference (positive = charge, negative = credit)
        $difference = $new_amount - $unused_amount;
        
        return [
            'days_remaining' => $days_remaining,
            'interval_days' => $interval_days,
            'old_plan_price' => isset( $old_plan['price'] ) ? floatval( $old_plan['price'] ) : 0,
            'new_plan_price' => isset( $new_plan['price'] ) ? floatval( $new_plan['price'] ) : 0,
            'old_daily_rate' => $old_daily_rate,
            'new_daily_rate' => $new_daily_rate,
            'unused_credit' => $unused_amount,
            'new_plan_charge' => $new_amount,
            'proration_amount' => $difference,
            'proration_type' => $difference > 0 ? 'charge' : 'credit',
        ];
    }
    
    /**
     * Pause subscription
     * 
     * @param int $subscription_id Subscription ID
     * @param array $options Pause options
     * @return bool|WP_Error Success or error
     */
    public function pause_subscription( $subscription_id, $options = [] ) {
        global $wpdb;
        
        // Get subscription
        $subscription = $this->get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return new WP_Error( 'invalid_subscription', 'Subscription not found' );
        }
        
        // Check if subscription can be paused
        if ( $subscription['status'] !== 'active' ) {
            return new WP_Error( 'invalid_status', 'Only active subscriptions can be paused' );
        }
        
        // Default options
        $defaults = [
            'pause_duration' => null, // null = indefinite, or number of days
            'send_email' => true,
        ];
        
        $options = wp_parse_args( $options, $defaults );
        
        // Calculate resume date if duration specified
        $resume_at = null;
        if ( $options['pause_duration'] ) {
            $resume_at = date( 'Y-m-d H:i:s', strtotime( '+' . $options['pause_duration'] . ' days' ) );
        }
        
        // Update subscription
        $updated = $wpdb->update(
            $wpdb->prefix . 'nehtw_subscriptions',
            [
                'status' => 'paused',
                'paused_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
                'meta' => wp_json_encode( array_merge(
                    json_decode( $subscription['meta'], true ) ?: [],
                    [ 'resume_at' => $resume_at ]
                ) ),
            ],
            [ 'id' => $subscription_id ],
            [ '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $updated === false ) {
            return new WP_Error( 'update_failed', 'Failed to pause subscription' );
        }
        
        // Record history
        $this->record_subscription_history( $subscription_id, 'paused', [
            'pause_duration' => $options['pause_duration'],
            'resume_at' => $resume_at,
        ] );
        
        // Schedule auto-resume if duration specified
        if ( $resume_at ) {
            wp_schedule_single_event(
                strtotime( $resume_at ),
                'nehtw_auto_resume_subscription',
                [ $subscription_id ]
            );
        }
        
        // Send email
        if ( $options['send_email'] ) {
            $this->send_pause_email( $subscription_id, $resume_at );
        }
        
        // Trigger action
        do_action( 'nehtw_subscription_paused', $subscription_id, $options );
        
        return true;
    }
    
    /**
     * Resume paused subscription
     * 
     * @param int $subscription_id Subscription ID
     * @param array $options Resume options
     * @return bool|WP_Error Success or error
     */
    public function resume_subscription( $subscription_id, $options = [] ) {
        global $wpdb;
        
        // Get subscription
        $subscription = $this->get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return new WP_Error( 'invalid_subscription', 'Subscription not found' );
        }
        
        // Check if subscription is paused
        if ( $subscription['status'] !== 'paused' ) {
            return new WP_Error( 'invalid_status', 'Subscription is not paused' );
        }
        
        // Default options
        $defaults = [
            'send_email' => true,
        ];
        
        $options = wp_parse_args( $options, $defaults );
        
        // Calculate new renewal date (extend by pause duration)
        $paused_at = new DateTime( $subscription['paused_at'] );
        $now = new DateTime();
        $pause_duration = $now->diff( $paused_at )->days;
        
        $old_renewal = new DateTime( $subscription['next_renewal_at'] );
        $new_renewal = $old_renewal->modify( '+' . $pause_duration . ' days' );
        
        // Update subscription
        $updated = $wpdb->update(
            $wpdb->prefix . 'nehtw_subscriptions',
            [
                'status' => 'active',
                'next_renewal_at' => $new_renewal->format( 'Y-m-d H:i:s' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $subscription_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $updated === false ) {
            return new WP_Error( 'update_failed', 'Failed to resume subscription' );
        }
        
        // Record history
        $this->record_subscription_history( $subscription_id, 'resumed', [
            'pause_duration_days' => $pause_duration,
            'new_renewal_date' => $new_renewal->format( 'Y-m-d H:i:s' ),
        ] );
        
        // Cancel scheduled auto-resume
        wp_clear_scheduled_hook( 'nehtw_auto_resume_subscription', [ $subscription_id ] );
        
        // Send email
        if ( $options['send_email'] ) {
            $this->send_resume_email( $subscription_id );
        }
        
        // Trigger action
        do_action( 'nehtw_subscription_resumed', $subscription_id, $options );
        
        return true;
    }
    
    /**
     * Cancel subscription
     * 
     * @param int $subscription_id Subscription ID
     * @param array $options Cancellation options
     * @return bool|WP_Error Success or error
     */
    public function cancel_subscription( $subscription_id, $options = [] ) {
        global $wpdb;
        
        // Get subscription
        $subscription = $this->get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return new WP_Error( 'invalid_subscription', 'Subscription not found' );
        }
        
        // Check if subscription can be cancelled
        if ( in_array( $subscription['status'], [ 'cancelled', 'expired' ] ) ) {
            return new WP_Error( 'already_cancelled', 'Subscription is already cancelled' );
        }
        
        // Default options
        $defaults = [
            'cancel_immediately' => false,
            'reason' => null,
            'send_email' => true,
        ];
        
        $options = wp_parse_args( $options, $defaults );
        
        // Determine cancellation date
        $cancelled_at = current_time( 'mysql' );
        $expires_at = $options['cancel_immediately'] 
            ? $cancelled_at 
            : $subscription['next_renewal_at'];
        
        // Update subscription
        $updated = $wpdb->update(
            $wpdb->prefix . 'nehtw_subscriptions',
            [
                'status' => $options['cancel_immediately'] ? 'cancelled' : 'cancelling',
                'cancelled_at' => $cancelled_at,
                'updated_at' => current_time( 'mysql' ),
                'meta' => wp_json_encode( array_merge(
                    json_decode( $subscription['meta'], true ) ?: [],
                    [
                        'cancellation_reason' => $options['reason'],
                        'expires_at' => $expires_at,
                    ]
                ) ),
            ],
            [ 'id' => $subscription_id ],
            [ '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $updated === false ) {
            return new WP_Error( 'update_failed', 'Failed to cancel subscription' );
        }
        
        // Record history
        $this->record_subscription_history( $subscription_id, 'cancelled', [
            'reason' => $options['reason'],
            'cancel_immediately' => $options['cancel_immediately'],
            'expires_at' => $expires_at,
        ] );
        
        // Schedule final expiration if not immediate
        if ( ! $options['cancel_immediately'] ) {
            wp_schedule_single_event(
                strtotime( $expires_at ),
                'nehtw_finalize_subscription_cancellation',
                [ $subscription_id ]
            );
        }
        
        // Cancel any pending retries
        $retry_manager = new Nehtw_Payment_Retry();
        $retry_manager->cancel_pending_retries( $subscription_id );
        
        // Send email
        if ( $options['send_email'] ) {
            $this->send_cancellation_email( $subscription_id, $options['cancel_immediately'] );
        }
        
        // Trigger action
        do_action( 'nehtw_subscription_cancelled', $subscription_id, $options );
        
        return true;
    }
    
    /**
     * Reactivate cancelled subscription
     * 
     * @param int $subscription_id Subscription ID
     * @param array $options Reactivation options
     * @return bool|WP_Error Success or error
     */
    public function reactivate_subscription( $subscription_id, $options = [] ) {
        global $wpdb;
        
        // Get subscription
        $subscription = $this->get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return new WP_Error( 'invalid_subscription', 'Subscription not found' );
        }
        
        // Check if subscription can be reactivated
        if ( ! in_array( $subscription['status'], [ 'cancelled', 'suspended', 'cancelling' ] ) ) {
            return new WP_Error( 'invalid_status', 'Subscription cannot be reactivated from current status' );
        }
        
        // Default options
        $defaults = [
            'send_email' => true,
        ];
        
        $options = wp_parse_args( $options, $defaults );
        
        // Calculate new renewal date
        $new_renewal = date( 'Y-m-d H:i:s', strtotime( '+1 ' . $subscription['interval'] ) );
        
        // Update subscription
        $updated = $wpdb->update(
            $wpdb->prefix . 'nehtw_subscriptions',
            [
                'status' => 'active',
                'next_renewal_at' => $new_renewal,
                'cancelled_at' => null,
                'failed_payment_count' => 0,
                'dunning_level' => 0,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $subscription_id ],
            [ '%s', '%s', '%s', '%d', '%d', '%s' ],
            [ '%d' ]
        );
        
        if ( $updated === false ) {
            return new WP_Error( 'update_failed', 'Failed to reactivate subscription' );
        }
        
        // Record history
        $this->record_subscription_history( $subscription_id, 'reactivated', [
            'new_renewal_date' => $new_renewal,
        ] );
        
        // Cancel scheduled expiration
        wp_clear_scheduled_hook( 'nehtw_finalize_subscription_cancellation', [ $subscription_id ] );
        
        // Send email
        if ( $options['send_email'] ) {
            $this->send_reactivation_email( $subscription_id );
        }
        
        // Trigger action
        do_action( 'nehtw_subscription_reactivated', $subscription_id, $options );
        
        return true;
    }
    
    /**
     * Apply plan change immediately
     * 
     * @param array $subscription Subscription data
     * @param string $new_plan_key New plan key
     * @param array|null $proration Proration data
     * @return array|WP_Error Result or error
     */
    private function apply_plan_change_immediately( $subscription, $new_plan_key, $proration = null ) {
        global $wpdb;
        
        // Get new plan
        $plans = nehtw_gateway_get_subscription_plans();
        $new_plan = $plans[ $new_plan_key ];
        
        // Process proration charge/credit if applicable
        if ( $proration && abs( $proration['proration_amount'] ) > 0.01 ) {
            $proration_result = $this->process_proration( $subscription, $proration );
            
            if ( is_wp_error( $proration_result ) ) {
                return $proration_result;
            }
        }
        
        // Update subscription
        $updated = $wpdb->update(
            $wpdb->prefix . 'nehtw_subscriptions',
            [
                'plan_key' => $new_plan_key,
                'points_per_interval' => isset( $new_plan['points'] ) ? floatval( $new_plan['points'] ) : 0,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $subscription['id'] ],
            [ '%s', '%f', '%s' ],
            [ '%d' ]
        );
        
        if ( $updated === false ) {
            return new WP_Error( 'update_failed', 'Failed to update subscription' );
        }
        
        return [
            'success' => true,
            'effective_date' => current_time( 'mysql' ),
        ];
    }
    
    /**
     * Schedule plan change for next renewal
     * 
     * @param array $subscription Subscription data
     * @param string $new_plan_key New plan key
     * @return array Result
     */
    private function schedule_plan_change( $subscription, $new_plan_key ) {
        // Store scheduled change in meta
        $meta = json_decode( $subscription['meta'], true ) ?: [];
        $meta['scheduled_plan_change'] = [
            'new_plan_key' => $new_plan_key,
            'scheduled_at' => current_time( 'mysql' ),
            'effective_date' => $subscription['next_renewal_at'],
        ];
        
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'nehtw_subscriptions',
            [
                'meta' => wp_json_encode( $meta ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $subscription['id'] ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        // Schedule event
        wp_schedule_single_event(
            strtotime( $subscription['next_renewal_at'] ),
            'nehtw_apply_scheduled_plan_change',
            [ $subscription['id'] ]
        );
        
        return [
            'success' => true,
            'effective_date' => $subscription['next_renewal_at'],
        ];
    }
    
    /**
     * Process proration charge or credit
     * 
     * @param array $subscription Subscription data
     * @param array $proration Proration data
     * @return bool|WP_Error Success or error
     */
    private function process_proration( $subscription, $proration ) {
        if ( $proration['proration_type'] === 'charge' ) {
            // Charge the difference
            return $this->charge_proration( $subscription, $proration['proration_amount'] );
        } else {
            // Credit the account
            return $this->credit_proration( $subscription, abs( $proration['proration_amount'] ) );
        }
    }
    
    /**
     * Charge proration amount
     * 
     * @param array $subscription Subscription data
     * @param float $amount Amount to charge
     * @return bool|WP_Error Success or error
     */
    private function charge_proration( $subscription, $amount ) {
        // Get payment method
        $payment_method = $this->get_payment_method( $subscription );
        
        if ( ! $payment_method ) {
            return new WP_Error( 'no_payment_method', 'No payment method available for proration charge' );
        }
        
        // Charge through gateway
        // This would integrate with actual payment gateway
        // For now, return success
        
        return true;
    }
    
    /**
     * Credit proration amount to user's account
     * 
     * @param array $subscription Subscription data
     * @param float $amount Amount to credit
     * @return bool Success
     */
    private function credit_proration( $subscription, $amount ) {
        // Add credit to wallet
        if ( function_exists( 'nehtw_gateway_add_transaction' ) ) {
            nehtw_gateway_add_transaction(
                $subscription['user_id'],
                'proration_credit',
                $amount,
                [
                    'subscription_id' => $subscription['id'],
                    'description' => 'Plan downgrade proration credit',
                ]
            );
        }
        
        return true;
    }
    
    /**
     * Determine change type (upgrade/downgrade/same)
     * 
     * @param array|null $old_plan Old plan
     * @param array $new_plan New plan
     * @return string Change type
     */
    private function determine_change_type( $old_plan, $new_plan ) {
        if ( ! $old_plan ) {
            return self::CHANGE_UPGRADE;
        }
        
        $old_price = isset( $old_plan['price'] ) ? floatval( $old_plan['price'] ) : 0;
        $new_price = isset( $new_plan['price'] ) ? floatval( $new_plan['price'] ) : 0;
        
        if ( $new_price > $old_price ) {
            return self::CHANGE_UPGRADE;
        } elseif ( $new_price < $old_price ) {
            return self::CHANGE_DOWNGRADE;
        } else {
            return self::CHANGE_SAME_TIER;
        }
    }
    
    /**
     * Record subscription history
     * 
     * @param int $subscription_id Subscription ID
     * @param string $action Action type
     * @param array $data Additional data
     * @return int|false History ID or false
     */
    private function record_subscription_history( $subscription_id, $action, $data = [] ) {
        global $wpdb;
        
        $subscription = $this->get_subscription( $subscription_id );
        
        return $wpdb->insert(
            $wpdb->prefix . 'nehtw_subscription_history',
            [
                'subscription_id' => $subscription_id,
                'user_id' => $subscription['user_id'],
                'action' => $action,
                'note' => isset( $data['note'] ) ? $data['note'] : null,
                'meta' => wp_json_encode( $data ),
                'created_at' => current_time( 'mysql' ),
                'created_by' => get_current_user_id(),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%d' ]
        ) ? $wpdb->insert_id : false;
    }
    
    /**
     * Record plan change history
     * 
     * @param int $subscription_id Subscription ID
     * @param array $data Change data
     * @return int|false History ID or false
     */
    private function record_plan_change_history( $subscription_id, $data ) {
        global $wpdb;
        
        $subscription = $this->get_subscription( $subscription_id );
        
        return $wpdb->insert(
            $wpdb->prefix . 'nehtw_subscription_history',
            [
                'subscription_id' => $subscription_id,
                'user_id' => $subscription['user_id'],
                'action' => 'plan_changed',
                'old_plan_key' => $data['old_plan_key'],
                'new_plan_key' => $data['new_plan_key'],
                'note' => sprintf( 'Plan %s from %s to %s', $data['change_type'], $data['old_plan_key'], $data['new_plan_key'] ),
                'meta' => wp_json_encode( $data ),
                'created_at' => current_time( 'mysql' ),
                'created_by' => get_current_user_id(),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
        ) ? $wpdb->insert_id : false;
    }
    
    /**
     * Helper methods
     */
    
    private function get_subscription( $subscription_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nehtw_subscriptions WHERE id = %d",
                $subscription_id
            ),
            ARRAY_A
        );
    }
    
    private function get_payment_method( $subscription ) {
        // Get saved payment method
        if ( ! empty( $subscription['payment_method'] ) ) {
            return [
                'type' => $subscription['payment_method'],
                'token' => get_user_meta( $subscription['user_id'], '_payment_token', true ),
            ];
        }
        return null;
    }
    
    /**
     * Email methods
     */
    
    private function send_plan_change_email( $subscription_id, $change_type, $old_plan, $new_plan, $proration ) {
        // Implementation would use email templates
    }
    
    private function send_pause_email( $subscription_id, $resume_at ) {
        // Implementation
    }
    
    private function send_resume_email( $subscription_id ) {
        // Implementation
    }
    
    private function send_cancellation_email( $subscription_id, $immediate ) {
        // Implementation
    }
    
    private function send_reactivation_email( $subscription_id ) {
        // Implementation
    }
    
    /**
     * Action handlers
     */
    
    public function handle_plan_change( $subscription_id, $new_plan_key, $change_type ) {
        // Post-change processing
    }
    
    public function handle_subscription_paused( $subscription_id, $options ) {
        // Post-pause processing
    }
    
    public function handle_subscription_resumed( $subscription_id, $options ) {
        // Post-resume processing
    }
    
    public function handle_subscription_cancelled( $subscription_id, $options ) {
        // Post-cancellation processing
    }
}

// Initialize
new Nehtw_Subscription_Manager();
