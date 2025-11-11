<?php

/**
 * Nehtw Transaction Manager
 * 
 * Core class for handling all balance transactions
 * 
 * @package Nehtw_Gateway
 * @version 2.0.0
 * 
 * File: /wp-content/plugins/nehtw-gateway/includes/balance/class-nehtw-transaction-manager.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Nehtw_Transaction_Manager {
    
    /**
     * Record a new transaction
     * 
     * @param array $data Transaction data
     * @return int|false Transaction ID or false
     */
    public static function record_transaction($data) {
        global $wpdb;
        
        $defaults = [
            'user_id' => 0,
            'type' => 'debit',
            'category' => 'order',
            'amount' => 0.00,
            'description' => '',
            'notes' => null,
            'related_order_id' => null,
            'related_subscription_id' => null,
            'related_user_id' => null,
            'is_promotional' => false,
            'promo_code' => null,
            'promo_expires_at' => null,
            'status' => 'completed',
            'metadata' => null
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate
        if (empty($data['user_id'])) {
            error_log('Transaction Manager: User ID required');
            return false;
        }
        
        // Get current balance
        $current_balance = self::get_user_balance($data['user_id']);
        
        // Calculate amount (negative for debits, positive for credits)
        $amount = (float) $data['amount'];
        if ($data['type'] === 'debit') {
            $amount = -abs($amount);
        } else {
            $amount = abs($amount);
        }
        
        $balance_before = $current_balance;
        $balance_after = $current_balance + $amount;
        
        // Check sufficient balance for debits
        if ($data['type'] === 'debit' && $balance_after < 0) {
            error_log("Transaction Manager: Insufficient balance for user {$data['user_id']}. Required: " . abs($amount) . ", Available: {$current_balance}");
            return false;
        }
        
        // Insert transaction
        $result = $wpdb->insert(
            "{$wpdb->prefix}nehtw_transactions",
            [
                'user_id' => $data['user_id'],
                'type' => $data['type'],
                'category' => $data['category'],
                'amount' => $amount,
                'balance_before' => $balance_before,
                'balance_after' => $balance_after,
                'description' => $data['description'],
                'notes' => $data['notes'],
                'related_order_id' => $data['related_order_id'],
                'related_subscription_id' => $data['related_subscription_id'],
                'related_user_id' => $data['related_user_id'],
                'is_promotional' => $data['is_promotional'],
                'promo_code' => $data['promo_code'],
                'promo_expires_at' => $data['promo_expires_at'],
                'status' => $data['status'],
                'metadata' => is_array($data['metadata']) ? json_encode($data['metadata']) : $data['metadata'],
                'created_at' => current_time('mysql')
            ]
        );
        
        if ($result === false) {
            error_log('Transaction Manager: Insert failed - ' . $wpdb->last_error);
            return false;
        }
        
        $transaction_id = $wpdb->insert_id;
        
        // Update balance cache
        self::update_user_balance_cache($data['user_id'], $balance_after);
        
        // Check for low balance
        self::check_low_balance_alert($data['user_id'], $balance_after);
        
        // Log success
        error_log(sprintf(
            'Transaction #%d: User %d, %s %s, $%s, Balance: $%s → $%s',
            $transaction_id,
            $data['user_id'],
            $data['type'],
            $data['category'],
            number_format(abs($amount), 2),
            number_format($balance_before, 2),
            number_format($balance_after, 2)
        ));
        
        // Trigger hook
        do_action('nehtw_transaction_recorded', $transaction_id, $data, $balance_after);
        
        return $transaction_id;
    }
    
    /**
     * Quick methods for common transaction types
     */
    
    public static function record_order_deduction($user_id, $order_id, $amount, $description) {
        return self::record_transaction([
            'user_id' => $user_id,
            'type' => 'debit',
            'category' => 'order',
            'amount' => $amount,
            'description' => $description,
            'related_order_id' => $order_id
        ]);
    }
    
    public static function record_subscription_credit($user_id, $subscription_id, $amount, $description) {
        return self::record_transaction([
            'user_id' => $user_id,
            'type' => 'credit',
            'category' => 'subscription',
            'amount' => $amount,
            'description' => $description,
            'related_subscription_id' => $subscription_id
        ]);
    }
    
    public static function record_refund($user_id, $order_id, $amount, $reason) {
        return self::record_transaction([
            'user_id' => $user_id,
            'type' => 'credit',
            'category' => 'refund',
            'amount' => $amount,
            'description' => "Refund: {$reason}",
            'related_order_id' => $order_id
        ]);
    }
    
    public static function record_bonus($user_id, $amount, $promo_code, $description) {
        return self::record_transaction([
            'user_id' => $user_id,
            'type' => 'credit',
            'category' => 'bonus',
            'amount' => $amount,
            'description' => $description,
            'is_promotional' => true,
            'promo_code' => $promo_code
        ]);
    }
    
    /**
     * Peer-to-peer transfer
     */
    public static function record_transfer($from_user_id, $to_user_id, $amount, $note = '') {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            // Deduct from sender
            $sender_tx = self::record_transaction([
                'user_id' => $from_user_id,
                'type' => 'debit',
                'category' => 'transfer_sent',
                'amount' => $amount,
                'description' => sprintf('Transferred to user #%d', $to_user_id),
                'notes' => $note,
                'related_user_id' => $to_user_id
            ]);
            
            if (!$sender_tx) {
                throw new Exception('Failed to deduct from sender');
            }
            
            // Credit to receiver
            $receiver_tx = self::record_transaction([
                'user_id' => $to_user_id,
                'type' => 'credit',
                'category' => 'transfer_received',
                'amount' => $amount,
                'description' => sprintf('Received from user #%d', $from_user_id),
                'notes' => $note,
                'related_user_id' => $from_user_id
            ]);
            
            if (!$receiver_tx) {
                throw new Exception('Failed to credit receiver');
            }
            
            $wpdb->query('COMMIT');
            
            // Send notification emails
            self::send_transfer_emails($from_user_id, $to_user_id, $amount);
            
            return [
                'sender_transaction' => $sender_tx,
                'receiver_transaction' => $receiver_tx
            ];
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Transfer failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's current balance
     */
    public static function get_user_balance($user_id) {
        global $wpdb;
        
        // Try cache first
        $cached = $wpdb->get_var($wpdb->prepare("
            SELECT current_balance FROM {$wpdb->prefix}nehtw_user_balance
            WHERE user_id = %d
        ", $user_id));
        
        if ($cached !== null) {
            return (float) $cached;
        }
        
        // Calculate from transactions
        $balance = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}nehtw_transactions
            WHERE user_id = %d AND status = 'completed'
        ", $user_id));
        
        return (float) $balance;
    }
    
    /**
     * Update user balance cache
     */
    private static function update_user_balance_cache($user_id, $new_balance) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare("
            INSERT INTO {$wpdb->prefix}nehtw_user_balance 
            (user_id, current_balance, updated_at)
            VALUES (%d, %f, %s)
            ON DUPLICATE KEY UPDATE 
            current_balance = %f,
            updated_at = %s
        ", $user_id, $new_balance, current_time('mysql'), $new_balance, current_time('mysql')));
        
        // Update lifetime stats
        self::update_lifetime_stats($user_id);
        
        // Update user meta for backward compatibility
        update_user_meta($user_id, 'nehtw_balance', $new_balance);
    }
    
    /**
     * Update lifetime statistics
     */
    private static function update_lifetime_stats($user_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(CASE WHEN type = 'debit' THEN ABS(amount) ELSE 0 END) as total_spent,
                SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) as total_added,
                COUNT(CASE WHEN category = 'order' THEN 1 END) as total_orders
            FROM {$wpdb->prefix}nehtw_transactions
            WHERE user_id = %d AND status = 'completed'
        ", $user_id));
        
        if ($stats) {
            $wpdb->update(
                "{$wpdb->prefix}nehtw_user_balance",
                [
                    'lifetime_spent' => $stats->total_spent ?: 0,
                    'lifetime_added' => $stats->total_added ?: 0,
                    'total_orders' => $stats->total_orders ?: 0
                ],
                ['user_id' => $user_id]
            );
        }
    }
    
    /**
     * Check low balance alert
     */
    private static function check_low_balance_alert($user_id, $current_balance) {
        global $wpdb;
        
        $settings = $wpdb->get_row($wpdb->prepare("
            SELECT low_balance_threshold, alert_enabled, last_alert_sent_at
            FROM {$wpdb->prefix}nehtw_user_balance
            WHERE user_id = %d
        ", $user_id));
        
        if (!$settings || !$settings->alert_enabled) {
            return;
        }
        
        $threshold = (float) $settings->low_balance_threshold;
        
        if ($current_balance <= $threshold) {
            // Don't spam - once per 24 hours
            if ($settings->last_alert_sent_at && strtotime($settings->last_alert_sent_at) > strtotime('-24 hours')) {
                return;
            }
            
            $alert_type = $current_balance <= 0 ? 'zero_balance' : 'low_balance';
            
            // Create alert
            $wpdb->insert(
                "{$wpdb->prefix}nehtw_balance_alerts",
                [
                    'user_id' => $user_id,
                    'alert_type' => $alert_type,
                    'current_balance' => $current_balance,
                    'threshold' => $threshold,
                    'message' => sprintf(
                        'Your balance is %s. Please top up.',
                        $current_balance <= 0 ? 'empty' : 'low ($' . number_format($current_balance, 2) . ')'
                    ),
                    'created_at' => current_time('mysql')
                ]
            );
            
            // Send email
            self::send_low_balance_email($user_id, $current_balance, $threshold);
            
            // Update last alert time
            $wpdb->update(
                "{$wpdb->prefix}nehtw_user_balance",
                ['last_alert_sent_at' => current_time('mysql')],
                ['user_id' => $user_id]
            );
        }
    }
    
    /**
     * Send low balance email
     */
    private static function send_low_balance_email($user_id, $balance, $threshold) {
        $user = get_userdata($user_id);
        if (!$user) return;
        
        $subject = $balance <= 0 ? '[Artly] Your balance is empty' : '[Artly] Low balance alert';
        
        $message = sprintf(
            "Hi %s,\n\n" .
            "Your Artly account balance is currently: $%s\n\n" .
            "%s\n\n" .
            "Top up now: %s\n\n" .
            "View transactions: %s\n\n" .
            "Thank you,\nArtly Team",
            $user->display_name,
            number_format($balance, 2),
            $balance <= 0 
                ? "You have run out of credits and cannot place new orders." 
                : "This is below your threshold of $" . number_format($threshold, 2),
            home_url('/pricing'),
            home_url('/my-account/transactions/')
        );
        
        wp_mail($user->user_email, $subject, $message);
        error_log("Low balance email sent to user {$user_id}");
    }
    
    /**
     * Send transfer notification emails
     */
    private static function send_transfer_emails($from_id, $to_id, $amount) {
        $from_user = get_userdata($from_id);
        $to_user = get_userdata($to_id);
        
        if (!$from_user || !$to_user) return;
        
        // Email to sender
        wp_mail(
            $from_user->user_email,
            '[Artly] Points Transfer Confirmation',
            sprintf(
                "Hi %s,\n\nYou have successfully transferred $%s to %s.\n\nThank you,\nArtly Team",
                $from_user->display_name,
                number_format($amount, 2),
                $to_user->display_name
            )
        );
        
        // Email to receiver
        wp_mail(
            $to_user->user_email,
            '[Artly] You Received Points!',
            sprintf(
                "Hi %s,\n\nYou have received $%s from %s.\n\nView your balance: %s\n\nThank you,\nArtly Team",
                $to_user->display_name,
                number_format($amount, 2),
                $from_user->display_name,
                home_url('/my-account/transactions/')
            )
        );
    }
    
    /**
     * Get transaction history
     */
    public static function get_user_transactions($user_id, $args = []) {
        global $wpdb;
        
        $defaults = [
            'limit' => 50,
            'offset' => 0,
            'category' => null,
            'type' => null,
            'start_date' => null,
            'end_date' => null
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ['user_id = %d'];
        $params = [$user_id];
        
        if ($args['category']) {
            $where[] = 'category = %s';
            $params[] = $args['category'];
        }
        
        if ($args['type']) {
            $where[] = 'type = %s';
            $params[] = $args['type'];
        }
        
        if ($args['start_date']) {
            $where[] = 'created_at >= %s';
            $params[] = $args['start_date'] . ' 00:00:00';
        }
        
        if ($args['end_date']) {
            $where[] = 'created_at <= %s';
            $params[] = $args['end_date'] . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where);
        $params[] = $args['limit'];
        $params[] = $args['offset'];
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}nehtw_transactions
            WHERE {$where_clause}
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d
        ", $params));
    }
    
    /**
     * Get spending analytics
     */
    public static function get_spending_analytics($user_id, $period = 'monthly', $limit = 12) {
        global $wpdb;
        
        $date_format = $period === 'daily' ? '%Y-%m-%d' 
            : ($period === 'weekly' ? '%Y-%u' : '%Y-%m');
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE_FORMAT(created_at, %s) as period,
                SUM(CASE WHEN type = 'debit' THEN ABS(amount) ELSE 0 END) as spent,
                COUNT(CASE WHEN category = 'order' THEN 1 END) as orders
            FROM {$wpdb->prefix}nehtw_transactions
            WHERE user_id = %d AND status = 'completed'
            GROUP BY period
            ORDER BY period DESC
            LIMIT %d
        ", $date_format, $user_id, $limit));
    }
    
    /**
     * Export transactions to CSV
     */
    public static function export_to_csv($user_id) {
        $transactions = self::get_user_transactions($user_id, ['limit' => 10000]);
        
        $csv = "Date,Type,Category,Description,Amount,Balance\n";
        
        foreach ($transactions as $tx) {
            $csv .= sprintf(
                "%s,%s,%s,\"%s\",%s,%s\n",
                $tx->created_at,
                $tx->type,
                $tx->category,
                str_replace('"', '""', $tx->description),
                number_format($tx->amount, 2),
                number_format($tx->balance_after, 2)
            );
        }
        
        return $csv;
    }
}

