<?php
/**
 * AI Image Generation REST controller for Artly / Nehtw gateway.
 *
 * Namespace: artly/v1
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Gateway_AI_REST {

    /**
     * Nehtw base URL.
     */
    const NEHTW_BASE = 'https://nehtw.com';

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register REST API routes.
     */
    public function register_routes() {
        register_rest_route(
            'artly/v1',
            '/ai/create',
            array(
                'methods'             => WP_REST_Server::CREATABLE, // POST
                'callback'            => array( $this, 'create_job' ),
                'permission_callback' => array( $this, 'require_logged_in' ),
                'args'                => array(
                    'prompt' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            'artly/v1',
            '/ai/status',
            array(
                'methods'             => WP_REST_Server::READABLE, // GET
                'callback'            => array( $this, 'get_status' ),
                'permission_callback' => array( $this, 'require_logged_in' ),
                'args'                => array(
                    'job_id' => array(
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                ),
            )
        );

        register_rest_route(
            'artly/v1',
            '/ai/action',
            array(
                'methods'             => WP_REST_Server::CREATABLE, // POST
                'callback'            => array( $this, 'do_action' ),
                'permission_callback' => array( $this, 'require_logged_in' ),
            )
        );
    }

    /**
     * Require logged-in user.
     */
    public function require_logged_in() {
        return is_user_logged_in()
            ? true
            : new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'nehtw-gateway' ), array( 'status' => 401 ) );
    }

    /**
     * Helper: get Nehtw API key from existing plugin function.
     * Uses the same function that stock downloads use.
     */
    protected function get_api_key() {
        // Use the plugin's centralized API key function (handles encryption/decryption)
        if ( function_exists( 'nehtw_gateway_get_api_key' ) ) {
            return nehtw_gateway_get_api_key();
        }
        // Fallback for backward compatibility
        $api_key = get_option( 'nehtw_gateway_api_key' );
        return $api_key ? trim( $api_key ) : '';
    }

    /**
     * POST /artly/v1/ai/create
     * Start a new AI generation job.
     */
    public function create_job( WP_REST_Request $request ) {
        $prompt = $request->get_param( 'prompt' );
        $prompt = trim( $prompt );

        if ( empty( $prompt ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'Prompt is required.',
                ),
                400
            );
        }

        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'Nehtw API key is not configured.',
                ),
                500
            );
        }

        // Call Nehtw /api/aig/create?prompt=...
        $url  = add_query_arg(
            array(
                'prompt' => rawurlencode( $prompt ),
            ),
            self::NEHTW_BASE . '/api/aig/create'
        );
        $args = array(
            'headers' => array(
                'X-Api-Key' => $api_key,
            ),
            'timeout' => 20,
        );

        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => $response->get_error_message(),
                ),
                500
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || ! is_array( $body ) || empty( $body['success'] ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => isset( $body['message'] ) ? $body['message'] : 'Nehtw create job failed.',
                ),
                500
            );
        }

        // Nehtw returns: { success: true, job_id: "...", get_result_url: "..." }
        $job_id = isset( $body['job_id'] ) ? $body['job_id'] : null;

        if ( empty( $job_id ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'Nehtw did not return job_id.',
                ),
                500
            );
        }

        // Get user ID and check balance before deducting points
        $user_id = get_current_user_id();
        $cost_points = 0;
        
        if ( function_exists( 'artly_ai_get_operation_cost_points' ) ) {
            $cost_points = artly_ai_get_operation_cost_points( 'generate' );
        } else {
            // Fallback to option value
            $cost_points = (int) get_option( 'artly_ai_generate_cost_points', 10 );
        }

        // Check user balance
        $user_balance = 0;
        if ( function_exists( 'nehtw_gateway_get_user_points_balance' ) ) {
            $user_balance = nehtw_gateway_get_user_points_balance( $user_id );
        } elseif ( function_exists( 'artly_get_user_points' ) ) {
            $user_balance = artly_get_user_points( $user_id );
        }

        // Check if user has enough points
        if ( $cost_points > 0 && $user_balance < $cost_points ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => sprintf( 'Not enough points. You have %d, but this operation costs %d.', $user_balance, $cost_points ),
                    'balance' => $user_balance,
                    'required' => $cost_points,
                ),
                400
            );
        }

        // Deduct points from user balance
        if ( $cost_points > 0 ) {
            // Check if deduction function exists
            if ( ! function_exists( 'artly_deduct_points' ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'AI Generate: artly_deduct_points function not available' );
                }
                
                return new WP_REST_Response(
                    array(
                        'success' => false,
                        'error'   => 'Points deduction system not available. Please contact support.',
                    ),
                    500
                );
            }

            // Check if table exists in database
            if ( function_exists( 'nehtw_gateway_get_table_name' ) ) {
                global $wpdb;
                $table = nehtw_gateway_get_table_name( 'wallet_transactions' );
                if ( ! $table ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'AI Generate: wallet_transactions table name not found' );
                    }
                    
                    return new WP_REST_Response(
                        array(
                            'success' => false,
                            'error'   => 'Wallet system not initialized. Please contact support.',
                        ),
                        500
                    );
                }
                
                // Verify table actually exists in database
                $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table;
                if ( ! $table_exists ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'AI Generate: wallet_transactions table does not exist in database: ' . $table );
                    }
                    
                    return new WP_REST_Response(
                        array(
                            'success' => false,
                            'error'   => 'Wallet system not initialized. Please contact support.',
                        ),
                        500
                    );
                }
            }

            $deduct_result = artly_deduct_points(
                $user_id,
                $cost_points,
                'ai_generate',
                array(
                    'job_id' => $job_id,
                    'prompt' => $prompt,
                )
            );

            if ( ! $deduct_result ) {
                // Log error for debugging
                global $wpdb;
                $last_error = $wpdb->last_error;
                
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'AI Generate: Failed to deduct points. User ID: ' . $user_id . ', Points: ' . $cost_points );
                    error_log( 'AI Generate: artly_deduct_points returned: ' . var_export( $deduct_result, true ) );
                    if ( ! empty( $last_error ) ) {
                        error_log( 'AI Generate: Database error: ' . $last_error );
                    }
                }
                
                return new WP_REST_Response(
                    array(
                        'success' => false,
                        'error'   => 'Deduct POINT not success, try again after a few seconds.',
                    ),
                    500
                );
            }

            // Get updated balance
            if ( function_exists( 'nehtw_gateway_get_user_points_balance' ) ) {
                $user_balance = nehtw_gateway_get_user_points_balance( $user_id );
            } elseif ( function_exists( 'artly_get_user_points' ) ) {
                $user_balance = artly_get_user_points( $user_id );
            }
        } elseif ( $cost_points > 0 ) {
            // Function doesn't exist
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'AI Generate: artly_deduct_points function not available' );
            }
            
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'Points deduction system not available. Please contact support.',
                ),
                500
            );
        }

        return new WP_REST_Response(
            array(
                'success'      => true,
                'job_id'       => $job_id,
                'prompt'       => $prompt,
                'status'       => 'pending',
                'percentage'   => 0,
                'user_balance' => $user_balance,
            ),
            200
        );
    }

    /**
     * GET /artly/v1/ai/status?job_id=...
     */
    public function get_status( WP_REST_Request $request ) {
        $job_id = $request->get_param( 'job_id' );
        $job_id = trim( $job_id );

        if ( empty( $job_id ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'job_id is required.',
                ),
                400
            );
        }

        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'Nehtw API key is not configured.',
                ),
                500
            );
        }

        // Call Nehtw /api/aig/public/{job_id}
        $url  = self::NEHTW_BASE . '/api/aig/public/' . rawurlencode( $job_id );
        $args = array(
            'headers' => array(
                'X-Api-Key' => $api_key,
            ),
            'timeout' => 20,
        );

        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => $response->get_error_message(),
                ),
                500
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        // Log the raw response for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Nehtw AI Status Response - Code: ' . $code );
            error_log( 'Nehtw AI Status Response - Body: ' . wp_json_encode( $body ) );
        }

        if ( 200 !== $code || ! is_array( $body ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'Invalid response from Nehtw.',
                ),
                500
            );
        }

        // Nehtw status object:
        // { "__id": "...", "prompt": "...", "status": "completed", "percentage_complete": 100, "files": [ { index, thumb_sm, thumb_lg, download } ] }
        // Note: Nehtw might return status as "done", "finished", "completed", or other values
        $raw_status = isset( $body['status'] ) ? $body['status'] : 'pending';
        
        // Normalize status values
        $status = $raw_status;
        if ( in_array( $raw_status, array( 'done', 'finished', 'completed' ), true ) ) {
            $status = 'completed';
        } elseif ( in_array( $raw_status, array( 'failed', 'error', 'cancelled' ), true ) ) {
            $status = 'failed';
        }
        
        // Check for percentage in multiple possible field names
        $percentage = 0;
        if ( isset( $body['percentage_complete'] ) ) {
            $percentage = (int) $body['percentage_complete'];
        } elseif ( isset( $body['percentage'] ) ) {
            $percentage = (int) $body['percentage'];
        } elseif ( isset( $body['progress'] ) ) {
            $percentage = (int) $body['progress'];
        }
        
        $files = array();

        if ( ! empty( $body['files'] ) && is_array( $body['files'] ) ) {
            foreach ( $body['files'] as $file ) {
                $files[] = array(
                    'index'        => isset( $file['index'] ) ? (int) $file['index'] : 0,
                    'thumb_sm'     => isset( $file['thumb_sm'] ) ? $file['thumb_sm'] : '',
                    'thumb_lg'     => isset( $file['thumb_lg'] ) ? $file['thumb_lg'] : '',
                    'download_url' => isset( $file['download'] ) ? $file['download'] : '',
                );
            }
        }

        // Include raw response in debug mode for troubleshooting
        $response_data = array(
            'success'    => true,
            'job_id'     => $job_id,
            'status'     => $status,
            'percentage' => $percentage,
            'files'      => $files,
        );
        
        // Add raw Nehtw response for debugging (only in debug mode)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $response_data['_debug'] = array(
                'raw_status' => $raw_status,
                'raw_body'   => $body,
            );
        }

        return new WP_REST_Response( $response_data, 200 );
    }

    /**
     * POST /artly/v1/ai/action
     * Body: { job_id, action: "vary"|"upscale", index, vary_type? }
     */
    public function do_action( WP_REST_Request $request ) {
        $params    = $request->get_json_params();
        $job_id    = isset( $params['job_id'] ) ? sanitize_text_field( $params['job_id'] ) : '';
        $action    = isset( $params['action'] ) ? sanitize_text_field( $params['action'] ) : '';
        $index     = isset( $params['index'] ) ? (int) $params['index'] : 0;
        $vary_type = isset( $params['vary_type'] ) ? sanitize_text_field( $params['vary_type'] ) : '';

        if ( empty( $job_id ) || empty( $action ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'job_id and action are required.',
                ),
                400
            );
        }

        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'Nehtw API key is not configured.',
                ),
                500
            );
        }

        // Get user ID and check balance before deducting points
        $user_id = get_current_user_id();
        $cost_points = 0;
        
        if ( function_exists( 'artly_ai_get_operation_cost_points' ) ) {
            $cost_points = artly_ai_get_operation_cost_points( $action );
        } else {
            // Fallback to option values
            if ( 'vary' === $action ) {
                $cost_points = (int) get_option( 'artly_ai_vary_cost_points', 6 );
            } elseif ( 'upscale' === $action ) {
                $cost_points = (int) get_option( 'artly_ai_upscale_cost_points', 4 );
            }
        }

        // Check user balance
        $user_balance = 0;
        if ( function_exists( 'nehtw_gateway_get_user_points_balance' ) ) {
            $user_balance = nehtw_gateway_get_user_points_balance( $user_id );
        } elseif ( function_exists( 'artly_get_user_points' ) ) {
            $user_balance = artly_get_user_points( $user_id );
        }

        // Check if user has enough points
        if ( $cost_points > 0 && $user_balance < $cost_points ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => sprintf( 'Not enough points. You have %d, but this operation costs %d.', $user_balance, $cost_points ),
                    'balance' => $user_balance,
                    'required' => $cost_points,
                ),
                400
            );
        }

        // Deduct points from user balance BEFORE calling Nehtw API
        if ( $cost_points > 0 && function_exists( 'artly_deduct_points' ) ) {
            $deduct_result = artly_deduct_points(
                $user_id,
                $cost_points,
                'ai_' . $action,
                array(
                    'job_id'    => $job_id,
                    'action'    => $action,
                    'index'     => $index,
                    'vary_type' => $vary_type,
                )
            );

            if ( ! $deduct_result ) {
                return new WP_REST_Response(
                    array(
                        'success' => false,
                        'error'   => 'Deduct POINT not success, try again after a few seconds.',
                    ),
                    500
                );
            }
        }

        $payload = array(
            'job_id' => $job_id,
            'action' => $action,
            'index'  => $index,
        );
        if ( 'vary' === $action && ! empty( $vary_type ) ) {
            $payload['vary_type'] = $vary_type; // subtle / strong
        }

        $url  = self::NEHTW_BASE . '/api/aig/actions';
        $args = array(
            'headers' => array(
                'X-Api-Key'    => $api_key,
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 20,
            'method'  => 'POST',
        );

        $response = wp_remote_post( $url, $args );
        if ( is_wp_error( $response ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => $response->get_error_message(),
                ),
                500
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || ! is_array( $body ) || empty( $body['success'] ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => isset( $body['message'] ) ? $body['message'] : 'Nehtw action failed.',
                ),
                500
            );
        }

        $new_job_id = isset( $body['job_id'] ) ? $body['job_id'] : null;

        if ( empty( $new_job_id ) ) {
            // If Nehtw API failed, we should refund the points
            // For now, we'll just return error (points already deducted)
            // TODO: Implement refund logic if needed
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => 'Nehtw did not return new job_id.',
                ),
                500
            );
        }

        // Get updated balance after deduction
        if ( function_exists( 'nehtw_gateway_get_user_points_balance' ) ) {
            $user_balance = nehtw_gateway_get_user_points_balance( $user_id );
        } elseif ( function_exists( 'artly_get_user_points' ) ) {
            $user_balance = artly_get_user_points( $user_id );
        } else {
            $user_balance = null;
        }

        return new WP_REST_Response(
            array(
                'success'      => true,
                'job_id'       => $new_job_id,
                'parent_job_id'=> $job_id,
                'action'       => $action,
                'user_balance' => $user_balance,
            ),
            200
        );
    }
}
