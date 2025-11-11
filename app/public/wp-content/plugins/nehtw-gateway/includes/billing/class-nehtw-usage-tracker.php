<?php
/**
 * Usage Tracking System
 * 
 * Tracks user usage for usage-based billing
 * 
 * @package Nehtw_Gateway
 * @subpackage Billing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Usage_Tracker {
    
    /**
     * Track a usage event
     * 
     * @param int $user_id User ID
     * @param string $type Usage type (e.g., 'stock_download', 'ai_generation')
     * @param float $amount Usage amount
     * @param array $meta Additional metadata
     * @return int|false Record ID or false
     */
    public function track_usage( $user_id, $type, $amount, $meta = [] ) {
        global $wpdb;
        
        $subscription = nehtw_gateway_get_user_active_subscription( $user_id );
        if ( ! $subscription ) {
            return false;
        }
        
        // Get current billing period
        $period = $this->get_current_billing_period( $subscription );
        
        return $wpdb->insert(
            $wpdb->prefix . 'nehtw_usage_tracking',
            [
                'user_id' => $user_id,
                'subscription_id' => $subscription['id'],
                'usage_type' => $type,
                'amount' => $amount,
                'unit' => isset( $meta['unit'] ) ? $meta['unit'] : 'points',
                'recorded_at' => current_time( 'mysql' ),
                'billing_period_start' => $period['start'],
                'billing_period_end' => $period['end'],
                'meta' => wp_json_encode( $meta ),
            ],
            [ '%d', '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' ]
        ) ? $wpdb->insert_id : false;
    }
    
    /**
     * Get usage for current period
     * 
     * @param int $user_id User ID
     * @param string|null $period_start Period start date
     * @param string|null $period_end Period end date
     * @return array Usage records
     */
    public function get_period_usage( $user_id, $period_start = null, $period_end = null ) {
        global $wpdb;
        
        $subscription = nehtw_gateway_get_user_active_subscription( $user_id );
        if ( ! $subscription ) {
            return [];
        }
        
        if ( ! $period_start || ! $period_end ) {
            $period = $this->get_current_billing_period( $subscription );
            $period_start = $period['start'];
            $period_end = $period['end'];
        }
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nehtw_usage_tracking
                 WHERE user_id = %d
                 AND billing_period_start = %s
                 AND billing_period_end = %s
                 ORDER BY recorded_at DESC",
                $user_id,
                $period_start,
                $period_end
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get usage summary
     * 
     * @param int $user_id User ID
     * @param string|null $period_start Period start date
     * @param string|null $period_end Period end date
     * @return array|null Usage summary
     */
    public function get_usage_summary( $user_id, $period_start = null, $period_end = null ) {
        global $wpdb;
        
        $subscription = nehtw_gateway_get_user_active_subscription( $user_id );
        if ( ! $subscription ) {
            return null;
        }
        
        if ( ! $period_start || ! $period_end ) {
            $period = $this->get_current_billing_period( $subscription );
            $period_start = $period['start'];
            $period_end = $period['end'];
        }
        
        $summary = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT usage_type, SUM(amount) as total_amount, COUNT(*) as count
                 FROM {$wpdb->prefix}nehtw_usage_tracking
                 WHERE user_id = %d
                 AND billing_period_start = %s
                 AND billing_period_end = %s
                 GROUP BY usage_type",
                $user_id,
                $period_start,
                $period_end
            ),
            ARRAY_A
        );
        
        $total = array_sum( array_column( $summary, 'total_amount' ) );
        
        return [
            'total_usage' => $total,
            'by_type' => $summary,
            'period_start' => $period_start,
            'period_end' => $period_end,
        ];
    }
    
    /**
     * Calculate overage charges
     * 
     * @param int $user_id User ID
     * @param int $subscription_id Subscription ID
     * @return array Overage calculation
     */
    public function calculate_overage( $user_id, $subscription_id ) {
        $subscription = $this->get_subscription( $subscription_id );
        $limits = $this->get_usage_limits( $subscription['plan_key'] );
        
        $usage_summary = $this->get_usage_summary( $user_id );
        $total_usage = $usage_summary['total_usage'];
        
        if ( $total_usage > $limits['included'] ) {
            $overage = $total_usage - $limits['included'];
            $overage_charge = $overage * $limits['per_unit_price'];
            
            return [
                'has_overage' => true,
                'overage_amount' => $overage,
                'overage_charge' => $overage_charge,
                'included' => $limits['included'],
                'total_usage' => $total_usage,
            ];
        }
        
        return [
            'has_overage' => false,
            'overage_amount' => 0,
            'overage_charge' => 0,
            'included' => $limits['included'],
            'total_usage' => $total_usage,
        ];
    }
    
    /**
     * Get usage history for multiple periods
     * 
     * @param int $user_id User ID
     * @param int $periods Number of periods to retrieve
     * @return array Usage history
     */
    public function get_usage_history( $user_id, $periods = 6 ) {
        global $wpdb;
        
        $subscription = nehtw_gateway_get_user_active_subscription( $user_id );
        if ( ! $subscription ) {
            return [];
        }
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    billing_period_start,
                    billing_period_end,
                    usage_type,
                    SUM(amount) as total_amount,
                    COUNT(*) as count
                 FROM {$wpdb->prefix}nehtw_usage_tracking
                 WHERE user_id = %d
                 AND subscription_id = %d
                 GROUP BY billing_period_start, billing_period_end, usage_type
                 ORDER BY billing_period_start DESC
                 LIMIT %d",
                $user_id,
                $subscription['id'],
                $periods * 10 // Rough estimate
            ),
            ARRAY_A
        );
    }
    
    /**
     * Helper methods
     */
    
    private function get_current_billing_period( $subscription ) {
        $end = new DateTime( $subscription['next_renewal_at'] );
        $start = clone $end;
        $start->modify( '-1 ' . $subscription['interval'] );
        
        return [
            'start' => $start->format( 'Y-m-d H:i:s' ),
            'end' => $end->format( 'Y-m-d H:i:s' ),
        ];
    }
    
    private function get_usage_limits( $plan_key ) {
        $plans = nehtw_gateway_get_subscription_plans();
        return isset( $plans[ $plan_key ]['usage_limits'] ) 
            ? $plans[ $plan_key ]['usage_limits'] 
            : [ 'included' => 9999999, 'per_unit_price' => 0 ];
    }
    
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
}

