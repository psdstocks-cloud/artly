<?php
/**
 * Nehtw Webhook handler for Artly.
 *
 * Listens to Nehtw "download status changing" events and updates local
 * stock order records so the /stock-order/ page can poll ONLY WordPress.
 *
 * @package Nehtw_Gateway
 * @version 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Gateway_Webhooks {

    /**
     * Init: register REST routes.
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    /**
     * Register the webhook endpoint.
     *
     * Endpoint: GET /wp-json/artly/v1/nehtw-webhook
     */
    public static function register_routes() {
        register_rest_route(
            'artly/v1',
            '/nehtw-webhook',
            array(
                'methods'             => WP_REST_Server::READABLE, // GET
                'callback'            => array( __CLASS__, 'handle_webhook' ),
                'permission_callback' => '__return_true',          // Nehtw cannot auth with WP cookie
                'args'                => array(),
            )
        );
    }

    /**
     * Verify webhook signature using HMAC.
     *
     * @param string $payload The webhook payload
     * @param string $signature The signature from X-Neh-Signature header
     * @param string $secret The webhook secret
     * @return bool True if signature is valid
     */
    protected static function verify_signature( $payload, $signature, $secret ) {
        if ( empty( $secret ) || empty( $signature ) ) {
            return false;
        }

        $expected_signature = hash_hmac( 'sha256', $payload, $secret );
        return hash_equals( $expected_signature, $signature );
    }

    /**
     * Check if IP is whitelisted.
     *
     * @param string $ip The IP address to check
     * @return bool True if IP is whitelisted or whitelist is empty
     */
    protected static function is_ip_whitelisted( $ip ) {
        $whitelist = get_option( 'nehtw_webhook_ip_whitelist', '' );
        
        // If whitelist is empty, allow all IPs (backward compatibility)
        if ( empty( $whitelist ) ) {
            return true;
        }

        $allowed_ips = array_map( 'trim', explode( "\n", $whitelist ) );
        $allowed_ips = array_filter( $allowed_ips );

        // Check exact match or CIDR notation
        foreach ( $allowed_ips as $allowed_ip ) {
            if ( $ip === $allowed_ip ) {
                return true;
            }
            
            // Check CIDR notation (e.g., 192.168.1.0/24)
            if ( strpos( $allowed_ip, '/' ) !== false ) {
                list( $subnet, $mask ) = explode( '/', $allowed_ip, 2 );
                $ip_long = ip2long( $ip );
                $subnet_long = ip2long( $subnet );
                $mask_long = -1 << ( 32 - (int) $mask );
                
                if ( ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Handle Nehtw webhook request.
     *
     * Nehtw will send something like:
     *  GET /wp-json/artly/v1/nehtw-webhook
     *  x-neh-event_name: download.status_changed
     *  x-neh-status: ready|completed|failed|error
     *  x-neh-task_id: 12345
     *  x-neh-signature: <hmac-sha256-signature>
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_webhook( WP_REST_Request $request ) {
        // Get client IP
        $client_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        
        // Check IP whitelist if configured
        if ( ! self::is_ip_whitelisted( $client_ip ) ) {
            error_log( sprintf( 'Nehtw Webhook: IP %s not whitelisted', $client_ip ) );
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Forbidden',
                ),
                403
            );
        }

        // Normalize header names
        $headers = array_change_key_case( $request->get_headers(), CASE_LOWER );

        // Verify HMAC signature
        $webhook_secret_encrypted = get_option( 'nehtw_webhook_secret', '' );
        $webhook_secret = ! empty( $webhook_secret_encrypted ) && function_exists( 'nehtw_gateway_decrypt_option' ) 
            ? nehtw_gateway_decrypt_option( $webhook_secret_encrypted ) 
            : $webhook_secret_encrypted;
        if ( ! empty( $webhook_secret ) ) {
            $signature = isset( $headers['x-neh-signature'][0] ) ? sanitize_text_field( $headers['x-neh-signature'][0] ) : '';
            
            // Build payload from headers and query params
            $payload_parts = array();
            if ( isset( $headers['x-neh-event_name'][0] ) ) {
                $payload_parts[] = 'event_name:' . $headers['x-neh-event_name'][0];
            }
            if ( isset( $headers['x-neh-status'][0] ) ) {
                $payload_parts[] = 'status:' . $headers['x-neh-status'][0];
            }
            if ( isset( $headers['x-neh-task_id'][0] ) ) {
                $payload_parts[] = 'task_id:' . $headers['x-neh-task_id'][0];
            }
            $payload = implode( '|', $payload_parts );
            
            // If no signature in headers, try query param (for backward compatibility during migration)
            if ( empty( $signature ) ) {
                $signature = sanitize_text_field( $request->get_param( 'signature' ) );
            }
            
            if ( ! empty( $signature ) && ! self::verify_signature( $payload, $signature, $webhook_secret ) ) {
                error_log( sprintf( 'Nehtw Webhook: Invalid signature from IP %s', $client_ip ) );
                return new WP_REST_Response(
                    array(
                        'success' => false,
                        'message' => 'Unauthorized',
                    ),
                    401
                );
            }
        }

        $event_name = isset( $headers['x-neh-event_name'][0] ) ? sanitize_text_field( $headers['x-neh-event_name'][0] ) : '';
        $status     = isset( $headers['x-neh-status'][0] ) ? sanitize_text_field( $headers['x-neh-status'][0] ) : '';
        $task_id    = isset( $headers['x-neh-task_id'][0] ) ? sanitize_text_field( $headers['x-neh-task_id'][0] ) : '';

        // Fallback to query params if headers are missing (for testing via browser)
        if ( ! $task_id ) {
            $task_id = sanitize_text_field( $request->get_param( 'task_id' ) );
        }
        if ( ! $status ) {
            $status = sanitize_text_field( $request->get_param( 'status' ) );
        }
        if ( ! $event_name ) {
            $event_name = sanitize_text_field( $request->get_param( 'event_name' ) );
        }

        if ( empty( $task_id ) || empty( $status ) ) {
            error_log( 'Nehtw Webhook: Missing task_id or status. Headers: ' . print_r( $headers, true ) );
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Missing task_id or status in webhook',
                ),
                400
            );
        }

        // Only care about download-status events.
        if ( $event_name && false === strpos( $event_name, 'download' ) ) {
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Ignored non-download webhook event',
                ),
                200
            );
        }

        // Normalize remote status to our internal statuses.
        $normalized_status = self::normalize_status( $status );

        // Update the order using existing helper if available.
        $updated = false;
        if ( class_exists( 'Nehtw_Gateway_Stock_Orders' ) && method_exists( 'Nehtw_Gateway_Stock_Orders', 'update_status_by_task_id' ) ) {
            // If you have a helper like this in your codebase, use it.
            $updated = Nehtw_Gateway_Stock_Orders::update_status_by_task_id(
                $task_id,
                $normalized_status,
                array(
                    'webhook_received_at' => current_time( 'mysql' ),
                )
            );
        } else {
            // Fallback: do a direct DB update.
            global $wpdb;
            $table = $wpdb->prefix . 'nehtw_stock_orders';

            $result = $wpdb->update(
                $table,
                array(
                    'status'     => $normalized_status,
                    'updated_at' => current_time( 'mysql' ),
                ),
                array(
                    'task_id' => $task_id,
                ),
                array(
                    '%s',
                    '%s',
                ),
                array(
                    '%s',
                )
            );

            $updated = ( $result !== false );
        }

        if ( $updated ) {
            error_log( sprintf( 'Nehtw Webhook: Updated order %s to status %s', $task_id, $normalized_status ) );
        } else {
            error_log( sprintf( 'Nehtw Webhook: Failed to update order %s (may not exist in DB)', $task_id ) );
        }

        /**
         * Let other code (if any) react to webhook updates.
         *
         * @param string $task_id
         * @param string $normalized_status
         * @param string $raw_status
         */
        do_action( 'nehtw_webhook_status_update', $task_id, $normalized_status, $status );

        return new WP_REST_Response(
            array(
                'success'          => true,
                'task_id'          => $task_id,
                'status'           => $normalized_status,
                'original_status'  => $status,
                'event_name'       => $event_name,
                'updated'          => $updated,
            ),
            200
        );
    }

    /**
     * Map Nehtw status strings to our internal ones used on /stock-order/.
     *
     * @param string $status
     * @return string
     */
    protected static function normalize_status( $status ) {
        $status = strtolower( (string) $status );

        switch ( $status ) {
            case 'queued':
            case 'pending':
                return 'queued';

            case 'processing':
            case 'inprogress':
            case 'in_progress':
                return 'processing';

            case 'ready':
            case 'completed':
            case 'complete':
            case 'success':
                return 'completed';

            case 'failed':
            case 'error':
                return 'failed';

            default:
                return $status;
        }
    }
}

Nehtw_Gateway_Webhooks::init();

