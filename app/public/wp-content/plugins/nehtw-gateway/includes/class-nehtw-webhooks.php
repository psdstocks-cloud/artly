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
     * Handle Nehtw webhook request.
     *
     * Nehtw will send something like:
     *  GET /wp-json/artly/v1/nehtw-webhook
     *  x-neh-event_name: download.status_changed
     *  x-neh-status: ready|completed|failed|error
     *  x-neh-task_id: 12345
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function handle_webhook( WP_REST_Request $request ) {
        // Normalize header names
        $headers = array_change_key_case( $request->get_headers(), CASE_LOWER );

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

