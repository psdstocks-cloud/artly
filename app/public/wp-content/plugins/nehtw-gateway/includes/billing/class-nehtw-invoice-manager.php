<?php
/**
 * Invoice Management System
 * 
 * Handles invoice generation, PDF creation, and invoice operations
 * 
 * @package Nehtw_Gateway
 * @subpackage Billing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Invoice_Manager {
    
    /**
     * Invoice statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_VOID = 'void';
    const STATUS_REFUNDED = 'refunded';
    
    /**
     * Create a new invoice for subscription renewal
     * 
     * @param int $subscription_id Subscription ID
     * @param array $args Additional invoice data
     * @return int|WP_Error Invoice ID or error
     */
    public function create_renewal_invoice( $subscription_id, $args = [] ) {
        global $wpdb;
        
        // Get subscription
        $subscription = $this->get_subscription( $subscription_id );
        if ( ! $subscription ) {
            return new WP_Error( 'invalid_subscription', 'Subscription not found' );
        }
        
        // Calculate billing period
        $period_start = new DateTime( $subscription['next_renewal_at'] );
        $period_start->modify( '-1 ' . $subscription['interval'] );
        
        $period_end = new DateTime( $subscription['next_renewal_at'] );
        
        // Generate invoice number
        $invoice_number = $this->generate_invoice_number();
        
        // Get plan details
        $plans = nehtw_gateway_get_subscription_plans();
        $plan = isset( $plans[ $subscription['plan_key'] ] ) 
                ? $plans[ $subscription['plan_key'] ] 
                : [];
        
        $amount = isset( $plan['price'] ) ? floatval( $plan['price'] ) : 0;
        
        // Calculate tax if needed
        $tax_amount = $this->calculate_tax( $amount, $subscription['user_id'] );
        $total_amount = $amount + $tax_amount;
        
        // Prepare invoice data
        $invoice_data = wp_parse_args( $args, [
            'subscription_id' => $subscription_id,
            'user_id' => $subscription['user_id'],
            'invoice_number' => $invoice_number,
            'amount' => $amount,
            'tax_amount' => $tax_amount,
            'total_amount' => $total_amount,
            'currency' => 'USD',
            'status' => self::STATUS_PENDING,
            'billing_period_start' => $period_start->format( 'Y-m-d H:i:s' ),
            'billing_period_end' => $period_end->format( 'Y-m-d H:i:s' ),
            'due_date' => $period_end->format( 'Y-m-d H:i:s' ),
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ] );
        
        // Insert invoice
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'nehtw_invoices',
            $invoice_data,
            [
                '%d', '%d', '%s', '%f', '%f', '%f', '%s', '%s',
                '%s', '%s', '%s', '%s', '%s'
            ]
        );
        
        if ( ! $inserted ) {
            return new WP_Error( 'invoice_creation_failed', 'Failed to create invoice' );
        }
        
        $invoice_id = $wpdb->insert_id;
        
        // Log invoice creation
        $this->log_invoice_event( $invoice_id, 'created' );
        
        // Trigger action
        do_action( 'nehtw_invoice_created', $invoice_id, $invoice_data );
        
        return $invoice_id;
    }
    
    /**
     * Generate unique invoice number
     * 
     * Format: INV-YYYYMMDD-XXXXX
     * 
     * @return string Invoice number
     */
    private function generate_invoice_number() {
        $date = date( 'Ymd' );
        $random = str_pad( mt_rand( 1, 99999 ), 5, '0', STR_PAD_LEFT );
        return 'INV-' . $date . '-' . $random;
    }
    
    /**
     * Calculate tax for invoice
     * 
     * @param float $amount Invoice amount
     * @param int $user_id User ID
     * @return float Tax amount
     */
    private function calculate_tax( $amount, $user_id ) {
        // Get user's location
        $location = $this->get_user_tax_location( $user_id );
        
        // Get tax rate for location
        $tax_rate = $this->get_tax_rate( $location );
        
        // Calculate tax
        return $amount * ( $tax_rate / 100 );
    }
    
    /**
     * Generate PDF invoice
     * 
     * @param int $invoice_id Invoice ID
     * @return string|WP_Error PDF file path or error
     */
    public function generate_invoice_pdf( $invoice_id ) {
        // Get invoice data
        $invoice = $this->get_invoice( $invoice_id );
        if ( ! $invoice ) {
            return new WP_Error( 'invalid_invoice', 'Invoice not found' );
        }
        
        // Get user data
        $user = get_userdata( $invoice['user_id'] );
        if ( ! $user ) {
            return new WP_Error( 'invalid_user', 'User not found' );
        }
        
        // Get subscription data
        $subscription = $this->get_subscription( $invoice['subscription_id'] );
        
        // Generate PDF using TCPDF or similar library
        if ( ! class_exists( 'Nehtw_PDF_Generator' ) ) {
            // For now, return a placeholder path
            // PDF generation will be implemented in Phase 6
            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/nehtw-invoices';
            if ( ! file_exists( $pdf_dir ) ) {
                wp_mkdir_p( $pdf_dir );
            }
            
            $pdf_path = $pdf_dir . '/invoice-' . $invoice['invoice_number'] . '.pdf';
            
            // Create a placeholder file for now
            file_put_contents( $pdf_path, 'PDF placeholder - to be implemented' );
            
            return $pdf_path;
        }
        
        $pdf_generator = new Nehtw_PDF_Generator();
        $pdf_path = $pdf_generator->generate_invoice_pdf( $invoice, $user, $subscription );
        
        // Update invoice with PDF path
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'nehtw_invoices',
            [ 'pdf_path' => $pdf_path ],
            [ 'id' => $invoice_id ],
            [ '%s' ],
            [ '%d' ]
        );
        
        return $pdf_path;
    }
    
    /**
     * Mark invoice as paid
     * 
     * @param int $invoice_id Invoice ID
     * @param array $payment_data Payment information
     * @return bool Success status
     */
    public function mark_invoice_paid( $invoice_id, $payment_data = [] ) {
        global $wpdb;
        
        $update_data = [
            'status' => self::STATUS_PAID,
            'paid_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ];
        
        if ( isset( $payment_data['payment_method'] ) ) {
            $update_data['payment_method'] = $payment_data['payment_method'];
        }
        
        if ( isset( $payment_data['gateway'] ) ) {
            $update_data['payment_gateway'] = $payment_data['gateway'];
        }
        
        if ( isset( $payment_data['transaction_id'] ) ) {
            $update_data['gateway_transaction_id'] = $payment_data['transaction_id'];
        }
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'nehtw_invoices',
            $update_data,
            [ 'id' => $invoice_id ],
            [ '%s', '%s', '%s', '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $updated === false ) {
            return false;
        }
        
        // Log payment
        $this->log_invoice_event( $invoice_id, 'paid', $payment_data );
        
        // Trigger action
        do_action( 'nehtw_invoice_paid', $invoice_id, $payment_data );
        
        // Send paid invoice email
        $this->send_invoice_email( $invoice_id, 'paid' );
        
        // Reset failed payment counter on subscription
        $invoice = $this->get_invoice( $invoice_id );
        if ( $invoice ) {
            $this->reset_failed_payments( $invoice['subscription_id'] );
        }
        
        return true;
    }
    
    /**
     * Mark invoice as failed
     * 
     * @param int $invoice_id Invoice ID
     * @param string $error_message Error message
     * @return bool Success status
     */
    public function mark_invoice_failed( $invoice_id, $error_message = '' ) {
        global $wpdb;
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'nehtw_invoices',
            [
                'status' => self::STATUS_FAILED,
                'updated_at' => current_time( 'mysql' ),
                'notes' => $error_message,
            ],
            [ 'id' => $invoice_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        if ( $updated === false ) {
            return false;
        }
        
        // Log failure
        $this->log_invoice_event( $invoice_id, 'failed', [ 'error' => $error_message ] );
        
        // Trigger action
        do_action( 'nehtw_invoice_failed', $invoice_id, $error_message );
        
        return true;
    }
    
    /**
     * Send invoice email
     * 
     * @param int $invoice_id Invoice ID
     * @param string $type Email type (created|paid|failed|reminder)
     * @return bool Success status
     */
    public function send_invoice_email( $invoice_id, $type = 'created' ) {
        $invoice = $this->get_invoice( $invoice_id );
        if ( ! $invoice ) {
            return false;
        }
        
        $user = get_userdata( $invoice['user_id'] );
        if ( ! $user ) {
            return false;
        }
        
        // Get email template
        $email_template = $this->get_email_template( $type );
        
        // Replace placeholders
        $placeholders = [
            '{user_name}' => $user->display_name,
            '{invoice_number}' => $invoice['invoice_number'],
            '{amount}' => '$' . number_format( $invoice['total_amount'], 2 ),
            '{due_date}' => date( 'F j, Y', strtotime( $invoice['due_date'] ) ),
            '{invoice_url}' => $this->get_invoice_url( $invoice_id ),
            '{dashboard_url}' => get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ),
        ];
        
        $subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $email_template['subject'] );
        $message = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $email_template['body'] );
        
        // Send email
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent = wp_mail( $user->user_email, $subject, $message, $headers );
        
        return $sent;
    }
    
    /**
     * Get user invoices
     * 
     * @param int $user_id User ID
     * @param array $args Query arguments
     * @return array Invoices
     */
    public function get_user_invoices( $user_id, $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'status' => null,
            'limit' => 10,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $where = [ 'user_id = %d' ];
        $where_values = [ $user_id ];
        
        if ( $args['status'] ) {
            $where[] = 'status = %s';
            $where_values[] = $args['status'];
        }
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nehtw_invoices 
             WHERE " . implode( ' AND ', $where ) . "
             ORDER BY {$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            array_merge( $where_values, [ $args['limit'], $args['offset'] ] )
        );
        
        return $wpdb->get_results( $sql, ARRAY_A );
    }
    
    /**
     * Get invoice by ID
     * 
     * @param int $invoice_id Invoice ID
     * @return array|null Invoice data or null
     */
    public function get_invoice( $invoice_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nehtw_invoices WHERE id = %d",
                $invoice_id
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get invoice by invoice number
     * 
     * @param string $invoice_number Invoice number
     * @return array|null Invoice data or null
     */
    public function get_invoice_by_number( $invoice_number ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nehtw_invoices WHERE invoice_number = %s",
                $invoice_number
            ),
            ARRAY_A
        );
    }
    
    /**
     * Download invoice PDF
     * 
     * @param int $invoice_id Invoice ID
     * @param int $user_id User ID (for security check)
     * @return void Sends file or error
     */
    public function download_invoice( $invoice_id, $user_id = null ) {
        $invoice = $this->get_invoice( $invoice_id );
        
        if ( ! $invoice ) {
            wp_die( 'Invoice not found' );
        }
        
        // Security check
        if ( $user_id && $invoice['user_id'] != $user_id ) {
            wp_die( 'Unauthorized access' );
        }
        
        // Generate PDF if not exists
        if ( ! $invoice['pdf_path'] || ! file_exists( $invoice['pdf_path'] ) ) {
            $invoice['pdf_path'] = $this->generate_invoice_pdf( $invoice_id );
            
            if ( is_wp_error( $invoice['pdf_path'] ) ) {
                wp_die( 'Failed to generate PDF' );
            }
        }
        
        // Send file
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="invoice-' . $invoice['invoice_number'] . '.pdf"' );
        header( 'Content-Length: ' . filesize( $invoice['pdf_path'] ) );
        readfile( $invoice['pdf_path'] );
        exit;
    }
    
    /**
     * Get pending invoices for a subscription
     * 
     * @param int $subscription_id Subscription ID
     * @return array Pending invoices
     */
    public function get_pending_invoices( $subscription_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nehtw_invoices 
                 WHERE subscription_id = %d AND status = %s 
                 ORDER BY due_date ASC",
                $subscription_id,
                self::STATUS_PENDING
            ),
            ARRAY_A
        );
    }
    
    /**
     * Get overdue invoices
     * 
     * @param int $days_overdue Minimum days overdue
     * @return array Overdue invoices
     */
    public function get_overdue_invoices( $days_overdue = 0 ) {
        global $wpdb;
        $date = date( 'Y-m-d H:i:s', strtotime( "-{$days_overdue} days" ) );
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}nehtw_invoices 
                 WHERE status = %s AND due_date < %s 
                 ORDER BY due_date ASC",
                self::STATUS_PENDING,
                $date
            ),
            ARRAY_A
        );
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
    
    private function log_invoice_event( $invoice_id, $event, $data = [] ) {
        // Log to subscription history or separate invoice log
        do_action( 'nehtw_invoice_event', $invoice_id, $event, $data );
    }
    
    private function get_user_tax_location( $user_id ) {
        // Get user's billing location from user meta or WooCommerce
        return [
            'country' => get_user_meta( $user_id, 'billing_country', true ),
            'state' => get_user_meta( $user_id, 'billing_state', true ),
        ];
    }
    
    private function get_tax_rate( $location ) {
        // Get tax rate based on location
        // This would integrate with a tax service or local tax table
        return 0; // Default: no tax
    }
    
    private function get_email_template( $type ) {
        // Get email template from database or default templates
        $templates = [
            'created' => [
                'subject' => 'Invoice {invoice_number} - {amount}',
                'body' => 'Your invoice is ready...',
            ],
            'paid' => [
                'subject' => 'Payment Received - Invoice {invoice_number}',
                'body' => 'Thank you for your payment...',
            ],
            'failed' => [
                'subject' => 'Payment Failed - Invoice {invoice_number}',
                'body' => 'We were unable to process your payment...',
            ],
            'reminder' => [
                'subject' => 'Payment Reminder - Invoice {invoice_number}',
                'body' => 'This is a reminder that your invoice is due...',
            ],
        ];
        
        return isset( $templates[ $type ] ) ? $templates[ $type ] : $templates['created'];
    }
    
    private function get_invoice_url( $invoice_id ) {
        return add_query_arg(
            [
                'action' => 'nehtw_download_invoice',
                'invoice_id' => $invoice_id,
                'nonce' => wp_create_nonce( 'download_invoice_' . $invoice_id ),
            ],
            admin_url( 'admin-ajax.php' )
        );
    }
    
    private function reset_failed_payments( $subscription_id ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'nehtw_subscriptions',
            [
                'failed_payment_count' => 0,
                'dunning_level' => 0,
                'last_payment_attempt' => null,
            ],
            [ 'id' => $subscription_id ],
            [ '%d', '%d', '%s' ],
            [ '%d' ]
        );
    }
}

