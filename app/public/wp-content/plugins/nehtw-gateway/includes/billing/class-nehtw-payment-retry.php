<?php
/**
 * Payment Retry Logic System
 * 
 * Handles automatic payment retries with intelligent scheduling
 * Implements 3-attempt retry strategy over 7 days
 * 
 * @package Nehtw_Gateway
 * @subpackage Billing
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Payment_Retry {
    
    /**
     * Retry statuses
     */
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Maximum retry attempts
     */
    const MAX_ATTEMPTS = 3;
    
    /**
     * Initialize the retry system
     */
    public function __construct() {
        // Schedule cron job for processing retries
        add_action( 'nehtw_process_payment_retries', [ $this, 'process_retry_queue' ] );
        
        // Hook into payment failure events
        add_action( 'nehtw_payment_failed', [ $this, 'handle_payment_failure' ], 10, 2 );
        
        // Hook into successful payment events
        add_action( 'nehtw_invoice_paid', [ $this, 'cancel_pending_retries' ], 10, 1 );
    }
    
    /**
     * Get retry schedule configuration
     * 
     * Schedule: Day 1, Day 4, Day 7 after initial failure
     * 
     * @return array Retry schedule
     */
    private function get_retry_schedule() {
        return apply_filters( 'nehtw_payment_retry_schedule', [
            1 => [
                'delay' => '+1 day',
                'label' => 'First Retry (Day 1)',
            ],
            2 => [
                'delay' => '+3 days',
                'label' => 'Second Retry (Day 4)',
            ],
            3 => [
                'delay' => '+3 days',
                'label' => 'Final Retry (Day 7)',
            ],
        ] );
    }
    
    /**
     * Handle payment failure event
     * 
     * @param int $invoice_id Invoice ID
     * @param array $error_data Error information
     * @return bool Success status
     */
    public function handle_payment_failure( $invoice_id, $error_data = [] ) {
        global $wpdb;
        
        // Get invoice
        $invoice = $this->get_invoice( $invoice_id );
        if ( ! $invoice ) {
            return false;
        }
        
        // Get subscription
        $subscription = $this->get_subscription( $invoice['subscription_id'] );
        if ( ! $subscription ) {
            return false;
        }
        
        // Record payment attempt
        $this->record_payment_attempt( $invoice_id, $error_data );
        
        // Get current attempt count
        $attempt_count = $this->get_attempt_count( $invoice_id );
        
        // Check if we should retry
        if ( $attempt_count >= self::MAX_ATTEMPTS ) {
            // Max attempts reached - trigger final failure
            $this->handle_final_failure( $invoice_id, $subscription );
            return false;
        }
        
        // Schedule next retry
        $scheduled = $this->schedule_retry( $invoice_id, $attempt_count + 1 );
        
        if ( $scheduled ) {
            // Update subscription with failed payment info
            $this->update_subscription_failed_payment( $subscription['id'], $attempt_count + 1 );
            
            // Trigger dunning email
            do_action( 'nehtw_payment_retry_scheduled', $invoice_id, $attempt_count + 1 );
            
            // Log event
            $this->log_retry_scheduled( $invoice_id, $attempt_count + 1 );
        }
        
        return $scheduled;
    }
    
    /**
     * Schedule a payment retry
     * 
     * @param int $invoice_id Invoice ID
     * @param int $attempt_number Attempt number (1-3)
     * @return bool Success status
     */
    public function schedule_retry( $invoice_id, $attempt_number ) {
        global $wpdb;
        
        if ( $attempt_number > self::MAX_ATTEMPTS ) {
            return false;
        }
        
        $schedule = $this->get_retry_schedule();
        
        if ( ! isset( $schedule[ $attempt_number ] ) ) {
            return false;
        }
        
        // Calculate retry time
        $retry_time = strtotime( $schedule[ $attempt_number ]['delay'] );
        
        // Get invoice details
        $invoice = $this->get_invoice( $invoice_id );
        
        // Store retry schedule in database
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'nehtw_payment_retries',
            [
                'invoice_id' => $invoice_id,
                'subscription_id' => $invoice['subscription_id'],
                'user_id' => $invoice['user_id'],
                'attempt_number' => $attempt_number,
                'scheduled_at' => date( 'Y-m-d H:i:s', $retry_time ),
                'status' => self::STATUS_SCHEDULED,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' ]
        );
        
        if ( $inserted ) {
            // Schedule WordPress cron event
            wp_schedule_single_event(
                $retry_time,
                'nehtw_process_single_retry',
                [ $invoice_id, $attempt_number ]
            );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Attempt payment for an invoice
     * 
     * @param int $invoice_id Invoice ID
     * @return bool|WP_Error Success status or error
     */
    public function attempt_payment( $invoice_id ) {
        global $wpdb;
        
        // Get invoice
        $invoice = $this->get_invoice( $invoice_id );
        if ( ! $invoice ) {
            return new WP_Error( 'invalid_invoice', 'Invoice not found' );
        }
        
        // Check if already paid
        if ( $invoice['status'] === 'paid' ) {
            return new WP_Error( 'already_paid', 'Invoice already paid' );
        }
        
        // Get subscription
        $subscription = $this->get_subscription( $invoice['subscription_id'] );
        if ( ! $subscription ) {
            return new WP_Error( 'invalid_subscription', 'Subscription not found' );
        }
        
        // Get payment method
        $payment_method = $this->get_payment_method( $subscription );
        if ( ! $payment_method ) {
            return new WP_Error( 'no_payment_method', 'No valid payment method found' );
        }
        
        // Update retry status to in_progress
        $this->update_retry_status( $invoice_id, self::STATUS_IN_PROGRESS );
        
        // Attempt charge through payment gateway
        $charge_result = $this->charge_payment_method( $payment_method, $invoice );
        
        if ( is_wp_error( $charge_result ) ) {
            // Payment failed
            $this->record_payment_attempt( $invoice_id, [
                'status' => 'failed',
                'error_code' => $charge_result->get_error_code(),
                'error_message' => $charge_result->get_error_message(),
            ] );
            
            $this->update_retry_status( $invoice_id, self::STATUS_FAILED );
            
            // Trigger failure handler
            do_action( 'nehtw_payment_failed', $invoice_id, [
                'error' => $charge_result,
                'attempt_type' => 'retry',
            ] );
            
            return $charge_result;
        }
        
        // Payment successful
        $this->record_payment_attempt( $invoice_id, [
            'status' => 'success',
            'transaction_id' => $charge_result['transaction_id'],
            'gateway' => $charge_result['gateway'],
        ] );
        
        $this->update_retry_status( $invoice_id, self::STATUS_SUCCESS );
        
        // Mark invoice as paid
        $invoice_manager = new Nehtw_Invoice_Manager();
        $invoice_manager->mark_invoice_paid( $invoice_id, [
            'payment_method' => $charge_result['payment_method'],
            'gateway' => $charge_result['gateway'],
            'transaction_id' => $charge_result['transaction_id'],
        ] );
        
        // Cancel any remaining retries
        $this->cancel_pending_retries( $invoice_id );
        
        // Trigger success action
        do_action( 'nehtw_payment_retry_success', $invoice_id );
        
        return true;
    }
    
    /**
     * Process the retry queue
     * 
     * Called by cron job to process all scheduled retries
     * 
     * @return array Processing results
     */
    public function process_retry_queue() {
        global $wpdb;
        
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        
        // Get retries that are due
        $due_retries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nehtw_payment_retries
                 WHERE status = %s
                 AND scheduled_at <= %s
                 ORDER BY scheduled_at ASC
                 LIMIT 50",
                self::STATUS_SCHEDULED,
                current_time( 'mysql' )
            ),
            ARRAY_A
        );
        
        if ( ! $due_retries ) {
            return $results;
        }
        
        foreach ( $due_retries as $retry ) {
            $results['processed']++;
            
            // Attempt payment
            $result = $this->attempt_payment( $retry['invoice_id'] );
            
            if ( is_wp_error( $result ) ) {
                $results['failed']++;
                $results['errors'][] = [
                    'invoice_id' => $retry['invoice_id'],
                    'error' => $result->get_error_message(),
                ];
            } else {
                $results['successful']++;
            }
            
            // Small delay to avoid overwhelming payment gateway
            sleep( 2 );
        }
        
        // Log results
        $this->log_queue_processing( $results );
        
        // Send admin notification if there were errors
        if ( $results['failed'] > 0 ) {
            $this->notify_admin_retry_failures( $results );
        }
        
        return $results;
    }
    
    /**
     * Cancel pending retries for an invoice
     * 
     * @param int $invoice_id Invoice ID
     * @return bool Success status
     */
    public function cancel_pending_retries( $invoice_id ) {
        global $wpdb;
        
        // Update database
        $updated = $wpdb->update(
            $wpdb->prefix . 'nehtw_payment_retries',
            [
                'status' => self::STATUS_CANCELLED,
                'updated_at' => current_time( 'mysql' ),
            ],
            [
                'invoice_id' => $invoice_id,
                'status' => self::STATUS_SCHEDULED,
            ],
            [ '%s', '%s' ],
            [ '%d', '%s' ]
        );
        
        // Cancel WordPress cron events
        wp_clear_scheduled_hook( 'nehtw_process_single_retry', [ $invoice_id ] );
        
        return $updated !== false;
    }
    
    /**
     * Get retry status for an invoice
     * 
     * @param int $invoice_id Invoice ID
     * @return array|null Retry information
     */
    public function get_retry_status( $invoice_id ) {
        global $wpdb;
        
        $retries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nehtw_payment_retries
                 WHERE invoice_id = %d
                 ORDER BY attempt_number ASC",
                $invoice_id
            ),
            ARRAY_A
        );
        
        if ( ! $retries ) {
            return null;
        }
        
        $attempt_count = count( $retries );
        $last_retry = end( $retries );
        $next_retry = null;
        
        // Find next scheduled retry
        foreach ( $retries as $retry ) {
            if ( $retry['status'] === self::STATUS_SCHEDULED ) {
                $next_retry = $retry;
                break;
            }
        }
        
        return [
            'total_attempts' => $attempt_count,
            'max_attempts' => self::MAX_ATTEMPTS,
            'retries_remaining' => self::MAX_ATTEMPTS - $attempt_count,
            'last_attempt' => $last_retry,
            'next_retry' => $next_retry,
            'all_retries' => $retries,
        ];
    }
    
    /**
     * Handle final payment failure
     * 
     * @param int $invoice_id Invoice ID
     * @param array $subscription Subscription data
     * @return void
     */
    private function handle_final_failure( $invoice_id, $subscription ) {
        global $wpdb;
        
        // Update invoice status to failed
        $wpdb->update(
            $wpdb->prefix . 'nehtw_invoices',
            [
                'status' => 'failed',
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $invoice_id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        // Update subscription status
        $wpdb->update(
            $wpdb->prefix . 'nehtw_subscriptions',
            [
                'status' => 'suspended',
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $subscription['id'] ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        // Log event
        $this->log_final_failure( $invoice_id, $subscription['id'] );
        
        // Trigger final failure action (dunning level 4)
        do_action( 'nehtw_payment_final_failure', $invoice_id, $subscription );
    }
    
    /**
     * Record a payment attempt
     * 
     * @param int $invoice_id Invoice ID
     * @param array $data Attempt data
     * @return int|false Attempt ID or false
     */
    private function record_payment_attempt( $invoice_id, $data = [] ) {
        global $wpdb;
        
        $invoice = $this->get_invoice( $invoice_id );
        if ( ! $invoice ) {
            return false;
        }
        
        $attempt_number = $this->get_attempt_count( $invoice_id ) + 1;
        
        $insert_data = [
            'invoice_id' => $invoice_id,
            'subscription_id' => $invoice['subscription_id'],
            'user_id' => $invoice['user_id'],
            'attempt_number' => $attempt_number,
            'amount' => $invoice['total_amount'],
            'status' => isset( $data['status'] ) ? $data['status'] : 'failed',
            'attempted_at' => current_time( 'mysql' ),
        ];
        
        if ( isset( $data['error_code'] ) ) {
            $insert_data['error_code'] = $data['error_code'];
        }
        
        if ( isset( $data['error_message'] ) ) {
            $insert_data['error_message'] = $data['error_message'];
        }
        
        if ( isset( $data['gateway_response'] ) ) {
            $insert_data['gateway_response'] = wp_json_encode( $data['gateway_response'] );
        }
        
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'nehtw_payment_attempts',
            $insert_data,
            [ '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s' ]
        );
        
        if ( $inserted ) {
            return $wpdb->insert_id;
        }
        
        return false;
    }
    
    /**
     * Get payment attempt count for an invoice
     * 
     * @param int $invoice_id Invoice ID
     * @return int Attempt count
     */
    private function get_attempt_count( $invoice_id ) {
        global $wpdb;
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_payment_attempts
                 WHERE invoice_id = %d",
                $invoice_id
            )
        );
    }
    
    /**
     * Update subscription with failed payment info
     * 
     * @param int $subscription_id Subscription ID
     * @param int $failure_count Failure count
     * @return bool Success status
     */
    private function update_subscription_failed_payment( $subscription_id, $failure_count ) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'nehtw_subscriptions',
            [
                'failed_payment_count' => $failure_count,
                'last_payment_attempt' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $subscription_id ],
            [ '%d', '%s', '%s' ],
            [ '%d' ]
        ) !== false;
    }
    
    /**
     * Update retry status
     * 
     * @param int $invoice_id Invoice ID
     * @param string $status New status
     * @return bool Success status
     */
    private function update_retry_status( $invoice_id, $status ) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'nehtw_payment_retries',
            [
                'status' => $status,
                'updated_at' => current_time( 'mysql' ),
            ],
            [
                'invoice_id' => $invoice_id,
                'status' => self::STATUS_SCHEDULED,
            ],
            [ '%s', '%s' ],
            [ '%d', '%s' ]
        ) !== false;
    }
    
    /**
     * Charge payment method through gateway
     * 
     * @param array $payment_method Payment method details
     * @param array $invoice Invoice data
     * @return array|WP_Error Charge result or error
     */
    private function charge_payment_method( $payment_method, $invoice ) {
        $gateway = isset( $payment_method['gateway'] ) ? $payment_method['gateway'] : 'stripe';
        
        // Stripe integration
        if ( $gateway === 'stripe' ) {
            return $this->charge_stripe( $payment_method, $invoice );
        }
        
        // PayPal integration
        if ( $gateway === 'paypal' ) {
            return $this->charge_paypal( $payment_method, $invoice );
        }
        
        // WooCommerce integration (if available)
        if ( $gateway === 'woocommerce' && class_exists( 'WooCommerce' ) ) {
            return $this->charge_woocommerce( $payment_method, $invoice );
        }
        
        // Default: return error
        return new WP_Error( 'gateway_not_supported', 'Payment gateway not supported' );
    }
    
    /**
     * Charge via Stripe
     * 
     * @param array $payment_method Payment method details
     * @param array $invoice Invoice data
     * @return array|WP_Error Charge result or error
     */
    private function charge_stripe( $payment_method, $invoice ) {
        $stripe_secret_key = get_option( 'nehtw_stripe_secret_key' );
        
        if ( ! $stripe_secret_key ) {
            return new WP_Error( 'stripe_not_configured', 'Stripe API key not configured' );
        }
        
        // Check if Stripe PHP library is available
        if ( ! class_exists( '\Stripe\Stripe' ) ) {
            // Try to load Stripe library
            $stripe_path = NEHTW_GATEWAY_PLUGIN_DIR . 'includes/libraries/stripe-php/init.php';
            if ( file_exists( $stripe_path ) ) {
                require_once $stripe_path;
            } else {
                return new WP_Error( 'stripe_library_missing', 'Stripe PHP library not found. Please install via Composer or download from https://github.com/stripe/stripe-php' );
            }
        }
        
        try {
            \Stripe\Stripe::setApiKey( $stripe_secret_key );
            
            // Get payment method token
            $payment_token = isset( $payment_method['token'] ) ? $payment_method['token'] : null;
            
            if ( ! $payment_token ) {
                return new WP_Error( 'no_payment_token', 'Payment method token not found' );
            }
            
            // Create payment intent or charge
            $amount_cents = (int) ( $invoice['total_amount'] * 100 ); // Convert to cents
            
            $charge_params = [
                'amount' => $amount_cents,
                'currency' => strtolower( $invoice['currency'] ),
                'description' => sprintf( 'Invoice %s - Subscription Renewal', $invoice['invoice_number'] ),
                'metadata' => [
                    'invoice_id' => $invoice['id'],
                    'subscription_id' => $invoice['subscription_id'],
                    'user_id' => $invoice['user_id'],
                ],
            ];
            
            // Use payment method if available (Stripe API v2+)
            if ( strpos( $payment_token, 'pm_' ) === 0 ) {
                $charge_params['payment_method'] = $payment_token;
                $charge_params['confirm'] = true;
                $charge_params['return_url'] = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
                
                $payment_intent = \Stripe\PaymentIntent::create( $charge_params );
                
                if ( $payment_intent->status === 'succeeded' ) {
                    return [
                        'success' => true,
                        'transaction_id' => $payment_intent->id,
                        'gateway' => 'stripe',
                        'payment_method' => $payment_method['type'],
                    ];
                } else {
                    return new WP_Error( 'payment_failed', 'Payment intent not succeeded: ' . $payment_intent->status );
                }
            } else {
                // Legacy charge API
                $charge_params['source'] = $payment_token;
                $charge = \Stripe\Charge::create( $charge_params );
                
                if ( $charge->status === 'succeeded' ) {
                    return [
                        'success' => true,
                        'transaction_id' => $charge->id,
                        'gateway' => 'stripe',
                        'payment_method' => $payment_method['type'],
                    ];
                } else {
                    return new WP_Error( 'payment_failed', 'Charge not succeeded: ' . $charge->status );
                }
            }
            
        } catch ( \Stripe\Exception\CardException $e ) {
            return new WP_Error( 'stripe_card_error', $e->getError()->message );
        } catch ( \Stripe\Exception\RateLimitException $e ) {
            return new WP_Error( 'stripe_rate_limit', 'Too many requests made to Stripe API' );
        } catch ( \Stripe\Exception\InvalidRequestException $e ) {
            return new WP_Error( 'stripe_invalid_request', $e->getError()->message );
        } catch ( \Stripe\Exception\AuthenticationException $e ) {
            return new WP_Error( 'stripe_authentication', 'Stripe authentication failed' );
        } catch ( \Stripe\Exception\ApiConnectionException $e ) {
            return new WP_Error( 'stripe_connection', 'Network communication with Stripe failed' );
        } catch ( \Stripe\Exception\ApiErrorException $e ) {
            return new WP_Error( 'stripe_api_error', $e->getError()->message );
        } catch ( Exception $e ) {
            return new WP_Error( 'payment_failed', $e->getMessage() );
        }
    }
    
    /**
     * Charge via PayPal
     * 
     * @param array $payment_method Payment method details
     * @param array $invoice Invoice data
     * @return array|WP_Error Charge result or error
     */
    private function charge_paypal( $payment_method, $invoice ) {
        $paypal_client_id = get_option( 'nehtw_paypal_client_id' );
        $paypal_secret = get_option( 'nehtw_paypal_secret' );
        $paypal_mode = get_option( 'nehtw_paypal_mode', 'sandbox' ); // 'sandbox' or 'live'
        
        if ( ! $paypal_client_id || ! $paypal_secret ) {
            return new WP_Error( 'paypal_not_configured', 'PayPal credentials not configured' );
        }
        
        $api_url = $paypal_mode === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        
        // Get access token using Basic Auth
        $auth = base64_encode( $paypal_client_id . ':' . $paypal_secret );
        
        $token_response = wp_remote_post( $api_url . '/v1/oauth2/token', [
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US',
                'Authorization' => 'Basic ' . $auth,
            ],
            'body' => [
                'grant_type' => 'client_credentials',
            ],
            'timeout' => 30,
        ] );
        
        if ( is_wp_error( $token_response ) ) {
            return new WP_Error( 'paypal_token_error', 'Failed to get PayPal access token' );
        }
        
        $token_data = json_decode( wp_remote_retrieve_body( $token_response ), true );
        
        if ( ! isset( $token_data['access_token'] ) ) {
            return new WP_Error( 'paypal_token_error', 'Invalid PayPal token response' );
        }
        
        $access_token = $token_data['access_token'];
        
        // Create payment
        $payment_data = [
            'intent' => 'sale',
            'payer' => [
                'payment_method' => 'paypal',
            ],
            'transactions' => [
                [
                    'amount' => [
                        'total' => number_format( $invoice['total_amount'], 2, '.', '' ),
                        'currency' => strtoupper( $invoice['currency'] ),
                    ],
                    'description' => sprintf( 'Invoice %s - Subscription Renewal', $invoice['invoice_number'] ),
                    'invoice_number' => $invoice['invoice_number'],
                ],
            ],
            'redirect_urls' => [
                'return_url' => add_query_arg( [
                    'action' => 'nehtw_paypal_return',
                    'invoice_id' => $invoice['id'],
                ], get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ),
                'cancel_url' => add_query_arg( [
                    'action' => 'nehtw_paypal_cancel',
                    'invoice_id' => $invoice['id'],
                ], get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ),
            ],
        ];
        
        $payment_response = wp_remote_post( $api_url . '/v1/payments/payment', [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'body' => wp_json_encode( $payment_data ),
            'timeout' => 30,
        ] );
        
        if ( is_wp_error( $payment_response ) ) {
            return new WP_Error( 'paypal_payment_error', 'Failed to create PayPal payment' );
        }
        
        $payment_result = json_decode( wp_remote_retrieve_body( $payment_response ), true );
        
        if ( ! isset( $payment_result['id'] ) ) {
            return new WP_Error( 'paypal_payment_error', 'Invalid PayPal payment response' );
        }
        
        // For automatic recurring payments, we'd need to execute the payment
        // For now, return the payment ID for manual execution
        return [
            'success' => true,
            'transaction_id' => $payment_result['id'],
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'approval_url' => isset( $payment_result['links'] ) ? $payment_result['links'][0]['href'] : null,
        ];
    }
    
    /**
     * Charge via WooCommerce
     * 
     * @param array $payment_method Payment method details
     * @param array $invoice Invoice data
     * @return array|WP_Error Charge result or error
     */
    private function charge_woocommerce( $payment_method, $invoice ) {
        // This would integrate with WooCommerce payment gateways
        // For now, return a placeholder
        return new WP_Error( 'woocommerce_not_implemented', 'WooCommerce payment integration not yet implemented' );
    }
    
    /**
     * Helper methods
     */
    
    private function get_invoice( $invoice_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nehtw_invoices WHERE id = %d",
                $invoice_id
            ),
            ARRAY_A
        );
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
    
    private function get_payment_method( $subscription ) {
        // Get saved payment method for subscription
        // Check subscription meta first, then user meta
        
        $meta = json_decode( $subscription['meta'], true ) ?: [];
        
        // Get gateway from subscription meta or default
        $gateway = isset( $meta['payment_gateway'] ) 
            ? $meta['payment_gateway'] 
            : get_option( 'nehtw_default_payment_gateway', 'stripe' );
        
        // Get payment method type
        $payment_type = ! empty( $subscription['payment_method'] ) 
            ? $subscription['payment_method'] 
            : ( isset( $meta['payment_method_type'] ) ? $meta['payment_method_type'] : 'card' );
        
        // Get payment token
        $token = isset( $meta['payment_token'] ) 
            ? $meta['payment_token'] 
            : get_user_meta( $subscription['user_id'], '_nehtw_payment_token', true );
        
        if ( ! $token ) {
            // Try WooCommerce token if available
            $token = get_user_meta( $subscription['user_id'], '_payment_token', true );
        }
        
        if ( ! $token ) {
            return null;
        }
        
        return [
            'gateway' => $gateway,
            'type' => $payment_type,
            'token' => $token,
        ];
    }
    
    private function log_retry_scheduled( $invoice_id, $attempt_number ) {
        error_log( sprintf(
            '[Nehtw Payment Retry] Scheduled retry #%d for invoice #%d',
            $attempt_number,
            $invoice_id
        ) );
    }
    
    private function log_final_failure( $invoice_id, $subscription_id ) {
        error_log( sprintf(
            '[Nehtw Payment Retry] Final failure for invoice #%d, subscription #%d suspended',
            $invoice_id,
            $subscription_id
        ) );
    }
    
    private function log_queue_processing( $results ) {
        error_log( sprintf(
            '[Nehtw Payment Retry] Queue processed: %d total, %d successful, %d failed',
            $results['processed'],
            $results['successful'],
            $results['failed']
        ) );
    }
    
    private function notify_admin_retry_failures( $results ) {
        $admin_email = get_option( 'admin_email' );
        $subject = sprintf( 
            'Payment Retry Failures: %d failed out of %d attempts',
            $results['failed'],
            $results['processed']
        );
        
        $message = "Payment retry processing completed with failures:\n\n";
        $message .= sprintf( "Total processed: %d\n", $results['processed'] );
        $message .= sprintf( "Successful: %d\n", $results['successful'] );
        $message .= sprintf( "Failed: %d\n\n", $results['failed'] );
        
        if ( ! empty( $results['errors'] ) ) {
            $message .= "Failed invoices:\n";
            foreach ( $results['errors'] as $error ) {
                $message .= sprintf( "- Invoice #%d: %s\n", $error['invoice_id'], $error['error'] );
            }
        }
        
        wp_mail( $admin_email, $subject, $message );
    }
}

// Initialize
new Nehtw_Payment_Retry();

