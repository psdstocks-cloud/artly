<?php
/**
 * Dunning Management System
 * 
 * Handles automated email sequences for failed payments
 * Implements 4-level progressive dunning strategy
 * 
 * @package Nehtw_Gateway
 * @subpackage Billing
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Dunning_Manager {
    
    /**
     * Dunning levels
     */
    const LEVEL_1 = 1; // Payment failed - immediate
    const LEVEL_2 = 2; // Still unpaid - 3 days
    const LEVEL_3 = 3; // Final warning - 7 days
    const LEVEL_4 = 4; // Subscription cancelled - 10 days
    
    /**
     * Initialize dunning system
     */
    public function __construct() {
        // Hook into payment events
        add_action( 'nehtw_payment_failed', [ $this, 'trigger_dunning_level_1' ], 10, 2 );
        add_action( 'nehtw_payment_retry_scheduled', [ $this, 'schedule_dunning_emails' ], 10, 2 );
        add_action( 'nehtw_payment_final_failure', [ $this, 'trigger_dunning_level_4' ], 10, 2 );
        add_action( 'nehtw_payment_retry_success', [ $this, 'cancel_dunning_sequence' ], 10, 1 );
        
        // Cron hook for checking dunning schedule
        add_action( 'nehtw_check_dunning_schedule', [ $this, 'process_dunning_queue' ] );
    }
    
    /**
     * Get dunning sequence configuration
     * 
     * @return array Dunning sequence
     */
    private function get_dunning_sequence() {
        return apply_filters( 'nehtw_dunning_sequence', [
            self::LEVEL_1 => [
                'trigger' => 'payment_failed',
                'delay' => '0 hours',
                'template' => 'payment-failed-immediate',
                'subject' => 'Payment Failed - Action Required',
                'priority' => 'high',
                'tone' => 'concerned',
            ],
            self::LEVEL_2 => [
                'trigger' => 'still_unpaid',
                'delay' => '+3 days',
                'template' => 'payment-failed-reminder',
                'subject' => 'Reminder: Please Update Your Payment Method',
                'priority' => 'high',
                'tone' => 'helpful',
            ],
            self::LEVEL_3 => [
                'trigger' => 'still_unpaid',
                'delay' => '+7 days',
                'template' => 'payment-failed-final-warning',
                'subject' => 'Final Notice: Subscription Will Be Cancelled',
                'priority' => 'urgent',
                'tone' => 'urgent',
            ],
            self::LEVEL_4 => [
                'trigger' => 'subscription_cancelled',
                'delay' => '+10 days',
                'template' => 'subscription-cancelled',
                'subject' => 'Your Subscription Has Been Cancelled',
                'priority' => 'normal',
                'tone' => 'regretful',
            ],
        ] );
    }
    
    /**
     * Trigger dunning level 1 (immediate payment failure)
     * 
     * @param int $invoice_id Invoice ID
     * @param array $error_data Error information
     * @return bool Success status
     */
    public function trigger_dunning_level_1( $invoice_id, $error_data = [] ) {
        return $this->send_dunning_email( $invoice_id, self::LEVEL_1 );
    }
    
    /**
     * Schedule dunning emails for retries
     * 
     * @param int $invoice_id Invoice ID
     * @param int $retry_number Retry attempt number
     * @return bool Success status
     */
    public function schedule_dunning_emails( $invoice_id, $retry_number ) {
        // Level 2: After 3 days (before retry 2)
        if ( $retry_number === 2 ) {
            $this->schedule_dunning_email( $invoice_id, self::LEVEL_2, '+3 days' );
        }
        
        // Level 3: After 7 days (before retry 3)
        if ( $retry_number === 3 ) {
            $this->schedule_dunning_email( $invoice_id, self::LEVEL_3, '+7 days' );
        }
        
        return true;
    }
    
    /**
     * Trigger dunning level 4 (final failure / cancellation)
     * 
     * @param int $invoice_id Invoice ID
     * @param array $subscription Subscription data
     * @return bool Success status
     */
    public function trigger_dunning_level_4( $invoice_id, $subscription ) {
        return $this->send_dunning_email( $invoice_id, self::LEVEL_4 );
    }
    
    /**
     * Send dunning email
     * 
     * @param int $invoice_id Invoice ID
     * @param int $level Dunning level
     * @return bool Success status
     */
    public function send_dunning_email( $invoice_id, $level ) {
        global $wpdb;
        
        // Get invoice
        $invoice = $this->get_invoice( $invoice_id );
        if ( ! $invoice ) {
            return false;
        }
        
        // Get user
        $user = get_userdata( $invoice['user_id'] );
        if ( ! $user ) {
            return false;
        }
        
        // Get subscription
        $subscription = $this->get_subscription( $invoice['subscription_id'] );
        
        // Get dunning config
        $sequence = $this->get_dunning_sequence();
        if ( ! isset( $sequence[ $level ] ) ) {
            return false;
        }
        
        $config = $sequence[ $level ];
        
        // Update subscription dunning level
        $this->update_subscription_dunning_level( $subscription['id'], $level );
        
        // Get email template
        $email_content = $this->get_email_template( $config['template'], [
            'user' => $user,
            'invoice' => $invoice,
            'subscription' => $subscription,
            'level' => $level,
        ] );
        
        // Send email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        ];
        
        if ( $config['priority'] === 'high' || $config['priority'] === 'urgent' ) {
            $headers[] = 'X-Priority: 1';
            $headers[] = 'Importance: high';
        }
        
        $sent = wp_mail(
            $user->user_email,
            $email_content['subject'],
            $email_content['body'],
            $headers
        );
        
        if ( $sent ) {
            // Record dunning email
            $this->record_dunning_email( $invoice_id, $level );
            
            // Trigger action
            do_action( 'nehtw_dunning_email_sent', $invoice_id, $level, $user->user_email );
            
            // Log
            $this->log_dunning_sent( $invoice_id, $level, $user->user_email );
        }
        
        return $sent;
    }
    
    /**
     * Schedule a dunning email
     * 
     * @param int $invoice_id Invoice ID
     * @param int $level Dunning level
     * @param string $delay Delay string (e.g., '+3 days')
     * @return bool Success status
     */
    private function schedule_dunning_email( $invoice_id, $level, $delay ) {
        $send_time = strtotime( $delay );
        
        wp_schedule_single_event(
            $send_time,
            'nehtw_send_scheduled_dunning_email',
            [ $invoice_id, $level ]
        );
        
        return true;
    }
    
    /**
     * Cancel dunning sequence for an invoice
     * 
     * @param int $invoice_id Invoice ID
     * @return bool Success status
     */
    public function cancel_dunning_sequence( $invoice_id ) {
        // Clear scheduled dunning emails
        wp_clear_scheduled_hook( 'nehtw_send_scheduled_dunning_email', [ $invoice_id ] );
        
        // Reset subscription dunning level
        $invoice = $this->get_invoice( $invoice_id );
        if ( $invoice ) {
            $this->update_subscription_dunning_level( $invoice['subscription_id'], 0 );
        }
        
        // Log cancellation
        $this->log_dunning_cancelled( $invoice_id );
        
        return true;
    }
    
    /**
     * Process dunning queue (cron job)
     * 
     * @return array Processing results
     */
    public function process_dunning_queue() {
        global $wpdb;
        
        $results = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
        ];
        
        // Get subscriptions with failed payments
        $failed_subscriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT s.*, i.id as invoice_id
                 FROM {$wpdb->prefix}nehtw_subscriptions s
                 LEFT JOIN {$wpdb->prefix}nehtw_invoices i ON i.subscription_id = s.id
                 WHERE s.failed_payment_count > 0
                 AND s.status = %s
                 AND i.status = %s
                 AND s.dunning_level < %d
                 ORDER BY s.last_payment_attempt ASC
                 LIMIT 50",
                'active',
                'pending',
                self::LEVEL_4
            ),
            ARRAY_A
        );
        
        foreach ( $failed_subscriptions as $sub ) {
            $results['processed']++;
            
            // Calculate which level to send
            $days_since_failure = $this->get_days_since_failure( $sub['last_payment_attempt'] );
            $next_level = $this->determine_dunning_level( $days_since_failure, $sub['dunning_level'] );
            
            if ( $next_level > $sub['dunning_level'] ) {
                $sent = $this->send_dunning_email( $sub['invoice_id'], $next_level );
                
                if ( $sent ) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }
            }
        }
        
        // Log results
        $this->log_queue_processing( $results );
        
        return $results;
    }
    
    /**
     * Determine appropriate dunning level based on days elapsed
     * 
     * @param int $days_since_failure Days since payment failure
     * @param int $current_level Current dunning level
     * @return int Next dunning level
     */
    private function determine_dunning_level( $days_since_failure, $current_level ) {
        if ( $days_since_failure >= 10 && $current_level < self::LEVEL_4 ) {
            return self::LEVEL_4;
        }
        
        if ( $days_since_failure >= 7 && $current_level < self::LEVEL_3 ) {
            return self::LEVEL_3;
        }
        
        if ( $days_since_failure >= 3 && $current_level < self::LEVEL_2 ) {
            return self::LEVEL_2;
        }
        
        if ( $days_since_failure >= 0 && $current_level < self::LEVEL_1 ) {
            return self::LEVEL_1;
        }
        
        return $current_level;
    }
    
    /**
     * Get email template for dunning level
     * 
     * @param string $template_name Template name
     * @param array $data Template data
     * @return array Email content (subject, body)
     */
    private function get_email_template( $template_name, $data ) {
        $user = $data['user'];
        $invoice = $data['invoice'];
        $subscription = $data['subscription'];
        $level = $data['level'];
        
        // Get update payment URL
        $update_payment_url = $this->get_update_payment_url( $subscription['id'] );
        
        // Get dashboard URL
        $dashboard_url = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
        
        // Common placeholders
        $placeholders = [
            '{user_name}' => $user->display_name,
            '{user_first_name}' => $user->first_name,
            '{invoice_number}' => $invoice['invoice_number'],
            '{amount}' => '$' . number_format( $invoice['total_amount'], 2 ),
            '{plan_name}' => $this->get_plan_name( $subscription['plan_key'] ),
            '{due_date}' => date( 'F j, Y', strtotime( $invoice['due_date'] ) ),
            '{update_payment_url}' => $update_payment_url,
            '{dashboard_url}' => $dashboard_url,
            '{support_email}' => get_option( 'admin_email' ),
            '{site_name}' => get_bloginfo( 'name' ),
        ];
        
        // Load template based on level
        $templates = $this->get_email_templates();
        
        if ( ! isset( $templates[ $template_name ] ) ) {
            $template_name = 'payment-failed-immediate'; // Fallback
        }
        
        $template = $templates[ $template_name ];
        
        // Replace placeholders
        $subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template['subject'] );
        $body = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template['body'] );
        
        return [
            'subject' => $subject,
            'body' => $body,
        ];
    }
    
    /**
     * Get email templates
     * 
     * @return array Email templates
     */
    private function get_email_templates() {
        return [
            'payment-failed-immediate' => [
                'subject' => 'Payment Failed - Action Required for {plan_name}',
                'body' => $this->get_level_1_template(),
            ],
            'payment-failed-reminder' => [
                'subject' => 'Reminder: Update Your Payment Method',
                'body' => $this->get_level_2_template(),
            ],
            'payment-failed-final-warning' => [
                'subject' => 'Final Notice: Your Subscription Will Be Cancelled',
                'body' => $this->get_level_3_template(),
            ],
            'subscription-cancelled' => [
                'subject' => 'Your {plan_name} Subscription Has Been Cancelled',
                'body' => $this->get_level_4_template(),
            ],
        ];
    }
    
    /**
     * Level 1 email template (immediate failure)
     */
    private function get_level_1_template() {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #15192a, #242c42); color: #f7f9fc; padding: 30px; border-radius: 12px 12px 0 0; text-align: center; }
        .content { background: #ffffff; padding: 30px; border-radius: 0 0 12px 12px; }
        .button { display: inline-block; background: linear-gradient(135deg, #1af2ff, #53ff9d); color: #06111e; padding: 14px 32px; text-decoration: none; border-radius: 999px; font-weight: 600; margin: 20px 0; }
        .alert { background: rgba(255, 63, 111, 0.1); border-left: 4px solid #ff3f6f; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; color: #888; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Payment Failed</h1>
        </div>
        <div class="content">
            <p>Hi {user_first_name},</p>
            
            <div class="alert">
                <strong>Your payment of {amount} for {plan_name} could not be processed.</strong>
            </div>
            
            <p>We tried to charge your payment method on file, but the payment failed. This could be due to:</p>
            <ul>
                <li>Expired credit card</li>
                <li>Insufficient funds</li>
                <li>Incorrect billing information</li>
                <li>Card issuer declined the transaction</li>
            </ul>
            
            <p><strong>What happens next?</strong></p>
            <p>We\'ll automatically retry the payment in 1 day. However, to avoid any interruption to your service, please update your payment method now.</p>
            
            <center>
                <a href="{update_payment_url}" class="button">Update Payment Method</a>
            </center>
            
            <p><strong>Invoice Details:</strong></p>
            <ul>
                <li>Invoice: {invoice_number}</li>
                <li>Amount: {amount}</li>
                <li>Due Date: {due_date}</li>
            </ul>
            
            <p>If you have any questions or need assistance, please don\'t hesitate to contact us at {support_email}.</p>
            
            <p>Thank you,<br>The {site_name} Team</p>
        </div>
        <div class="footer">
            <p>You received this email because you have an active subscription with {site_name}.</p>
            <p><a href="{dashboard_url}">Manage Subscription</a></p>
        </div>
    </div>
</body>
</html>
';
    }
    
    /**
     * Level 2 email template (3-day reminder)
     */
    private function get_level_2_template() {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #15192a, #242c42); color: #f7f9fc; padding: 30px; border-radius: 12px 12px 0 0; text-align: center; }
        .content { background: #ffffff; padding: 30px; border-radius: 0 0 12px 12px; }
        .button { display: inline-block; background: linear-gradient(135deg, #1af2ff, #53ff9d); color: #06111e; padding: 14px 32px; text-decoration: none; border-radius: 999px; font-weight: 600; margin: 20px 0; }
        .warning { background: rgba(255, 187, 51, 0.1); border-left: 4px solid #ffbb33; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; color: #888; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Payment Reminder</h1>
        </div>
        <div class="content">
            <p>Hi {user_first_name},</p>
            
            <div class="warning">
                <strong>Your payment of {amount} is still outstanding.</strong>
            </div>
            
            <p>We wanted to remind you that we haven\'t been able to process your payment for {plan_name}. We\'ll try again in a few days, but we wanted to reach out in case you can update your payment information now.</p>
            
            <p><strong>Quick Fix:</strong></p>
            <p>Most payment issues can be resolved in under 2 minutes by updating your payment method.</p>
            
            <center>
                <a href="{update_payment_url}" class="button">Update Payment Method</a>
            </center>
            
            <p><strong>Need Help?</strong></p>
            <p>If you\'re experiencing any issues or have questions about your subscription, our support team is here to help. Just reply to this email or contact us at {support_email}.</p>
            
            <p>We value your subscription and want to make sure you continue to have uninterrupted access to {plan_name}.</p>
            
            <p>Best regards,<br>The {site_name} Team</p>
        </div>
        <div class="footer">
            <p><a href="{dashboard_url}">View Your Account</a></p>
        </div>
    </div>
</body>
</html>
';
    }
    
    /**
     * Level 3 email template (7-day final warning)
     */
    private function get_level_3_template() {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #15192a, #242c42); color: #f7f9fc; padding: 30px; border-radius: 12px 12px 0 0; text-align: center; }
        .content { background: #ffffff; padding: 30px; border-radius: 0 0 12px 12px; }
        .button { display: inline-block; background: linear-gradient(135deg, #1af2ff, #53ff9d); color: #06111e; padding: 14px 32px; text-decoration: none; border-radius: 999px; font-weight: 600; margin: 20px 0; }
        .urgent { background: rgba(255, 63, 111, 0.1); border-left: 4px solid #ff3f6f; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; color: #888; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ Final Notice</h1>
        </div>
        <div class="content">
            <p>Hi {user_first_name},</p>
            
            <div class="urgent">
                <strong>URGENT: Your subscription will be cancelled in 3 days if payment is not received.</strong>
            </div>
            
            <p>We\'ve tried multiple times to process your payment of {amount} for {plan_name}, but we haven\'t been successful.</p>
            
            <p><strong>What happens if you don\'t update your payment method:</strong></p>
            <ul>
                <li>Your subscription will be cancelled</li>
                <li>You\'ll lose access to all subscription benefits</li>
                <li>Your account data will be retained for 30 days, after which it will be permanently deleted</li>
            </ul>
            
            <p><strong>Want to keep your subscription?</strong></p>
            <p>Simply update your payment method now to continue enjoying uninterrupted service:</p>
            
            <center>
                <a href="{update_payment_url}" class="button">Update Payment Method Now</a>
            </center>
            
            <p><strong>Need to Cancel?</strong></p>
            <p>If you\'d prefer to cancel your subscription, you can do so from your <a href="{dashboard_url}">account dashboard</a>.</p>
            
            <p>We\'d hate to see you go, but we understand if {plan_name} is no longer the right fit for you. If there\'s anything we can do to improve your experience, please let us know at {support_email}.</p>
            
            <p>Best regards,<br>The {site_name} Team</p>
        </div>
        <div class="footer">
            <p>This is your final notice. Action required within 3 days.</p>
        </div>
    </div>
</body>
</html>
';
    }
    
    /**
     * Level 4 email template (cancellation)
     */
    private function get_level_4_template() {
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #15192a, #242c42); color: #f7f9fc; padding: 30px; border-radius: 12px 12px 0 0; text-align: center; }
        .content { background: #ffffff; padding: 30px; border-radius: 0 0 12px 12px; }
        .button { display: inline-block; background: linear-gradient(135deg, #1af2ff, #53ff9d); color: #06111e; padding: 14px 32px; text-decoration: none; border-radius: 999px; font-weight: 600; margin: 20px 0; }
        .info { background: rgba(26, 242, 255, 0.1); border-left: 4px solid #1af2ff; padding: 15px; margin: 20px 0; border-radius: 4px; }
        .footer { text-align: center; padding: 20px; color: #888; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Subscription Cancelled</h1>
        </div>
        <div class="content">
            <p>Hi {user_first_name},</p>
            
            <p>Your {plan_name} subscription has been cancelled due to non-payment.</p>
            
            <div class="info">
                <strong>Your account data will be retained for 30 days.</strong><br>
                If you reactivate your subscription within 30 days, all your data will be restored.
            </div>
            
            <p><strong>What you\'ve lost access to:</strong></p>
            <ul>
                <li>Monthly points allocation</li>
                <li>Stock download access</li>
                <li>AI generation features</li>
                <li>Premium support</li>
            </ul>
            
            <p><strong>Want to come back?</strong></p>
            <p>We\'d love to have you back! You can reactivate your subscription anytime:</p>
            
            <center>
                <a href="{update_payment_url}" class="button">Reactivate Subscription</a>
            </center>
            
            <p><strong>Questions or Feedback?</strong></p>
            <p>We\'re sorry to see you go. If there\'s anything we could have done better, or if you have any questions about your cancelled subscription, please don\'t hesitate to reach out to us at {support_email}.</p>
            
            <p>Thank you for being a part of {site_name}. We hope to see you again soon!</p>
            
            <p>Best regards,<br>The {site_name} Team</p>
        </div>
        <div class="footer">
            <p>Your subscription was cancelled on ' . date( 'F j, Y' ) . '</p>
            <p><a href="{dashboard_url}">View Your Account</a></p>
        </div>
    </div>
</body>
</html>
';
    }
    
    /**
     * Record dunning email in database
     * 
     * @param int $invoice_id Invoice ID
     * @param int $level Dunning level
     * @return int|false Record ID or false
     */
    private function record_dunning_email( $invoice_id, $level ) {
        global $wpdb;
        
        $invoice = $this->get_invoice( $invoice_id );
        if ( ! $invoice ) {
            return false;
        }
        
        return $wpdb->insert(
            $wpdb->prefix . 'nehtw_dunning_emails',
            [
                'subscription_id' => $invoice['subscription_id'],
                'invoice_id' => $invoice_id,
                'user_id' => $invoice['user_id'],
                'dunning_level' => $level,
                'email_type' => 'payment_dunning',
                'sent_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%d', '%d', '%s', '%s' ]
        ) ? $wpdb->insert_id : false;
    }
    
    /**
     * Update subscription dunning level
     * 
     * @param int $subscription_id Subscription ID
     * @param int $level Dunning level
     * @return bool Success status
     */
    private function update_subscription_dunning_level( $subscription_id, $level ) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'nehtw_subscriptions',
            [
                'dunning_level' => $level,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $subscription_id ],
            [ '%d', '%s' ],
            [ '%d' ]
        ) !== false;
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
    
    private function get_plan_name( $plan_key ) {
        $plans = nehtw_gateway_get_subscription_plans();
        if ( isset( $plans[ $plan_key ]['name'] ) ) {
            return $plans[ $plan_key ]['name'];
        }

        if ( class_exists( 'Nehtw_Subscription_Product_Helper' ) ) {
            $product_id = Nehtw_Subscription_Product_Helper::get_product_id_from_plan_key( $plan_key );
            if ( $product_id ) {
                return Nehtw_Subscription_Product_Helper::get_product_name( $product_id );
            }
        }

        return __( 'Your Plan', 'nehtw-gateway' );
    }
    
    private function get_update_payment_url( $subscription_id ) {
        $dashboard_url = get_permalink( get_option( 'woocommerce_myaccount_page_id' ) );
        return add_query_arg( [
            'action' => 'update_payment',
            'subscription_id' => $subscription_id,
        ], $dashboard_url );
    }
    
    private function get_days_since_failure( $failure_date ) {
        $now = new DateTime();
        $failure = new DateTime( $failure_date );
        $diff = $now->diff( $failure );
        return $diff->days;
    }
    
    private function log_dunning_sent( $invoice_id, $level, $email ) {
        error_log( sprintf(
            '[Nehtw Dunning] Level %d email sent for invoice #%d to %s',
            $level,
            $invoice_id,
            $email
        ) );
    }
    
    private function log_dunning_cancelled( $invoice_id ) {
        error_log( sprintf(
            '[Nehtw Dunning] Sequence cancelled for invoice #%d',
            $invoice_id
        ) );
    }
    
    private function log_queue_processing( $results ) {
        error_log( sprintf(
            '[Nehtw Dunning] Queue processed: %d total, %d sent, %d failed',
            $results['processed'],
            $results['sent'],
            $results['failed']
        ) );
    }
}

// Initialize
new Nehtw_Dunning_Manager();

