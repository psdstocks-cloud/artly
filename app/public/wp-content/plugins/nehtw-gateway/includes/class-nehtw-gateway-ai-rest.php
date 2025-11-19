<?php
/**
 * AI Image Generation REST controller for Artly / Nehtw gateway.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Gateway_AI_REST {

    const ROUTE_NAMESPACE = 'artly/v1';

    /**
     * Bootstrap by registering REST routes.
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    /**
     * Register all REST routes related to AI generation.
     */
    public function register_routes() {
        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/ai/create',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'create_job' ),
                'permission_callback' => array( $this, 'require_logged_in' ),
                'args'                => array(
                    'prompt' => array(
                        'required'          => true,
                        'sanitize_callback' => array( $this, 'sanitize_prompt_param' ),
                    ),
                ),
            )
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/ai/status',
            array(
                'methods'             => WP_REST_Server::READABLE,
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
            self::ROUTE_NAMESPACE,
            '/ai/action',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'do_action' ),
                'permission_callback' => array( $this, 'require_logged_in' ),
            )
        );

        register_rest_route(
            self::ROUTE_NAMESPACE,
            '/ai/history',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'get_history' ),
                'permission_callback' => array( $this, 'require_logged_in' ),
                'args'                => array(
                    'page' => array(
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ),
                    'per_page' => array(
                        'default'           => 20,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );
    }

    /**
     * Permission callback: require a logged-in user.
     */
    public function require_logged_in() {
        return is_user_logged_in()
            ? true
            : new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'nehtw-gateway' ), array( 'status' => 401 ) );
    }

    /**
     * Sanitize prompt parameter (trim + limit length).
     */
    public function sanitize_prompt_param( $value ) {
        $value = sanitize_textarea_field( (string) $value );
        return mb_substr( $value, 0, 500 );
    }

    /**
     * POST /artly/v1/ai/create – Start a new AI generation job.
     */
    public function create_job( WP_REST_Request $request ) {
        $prompt = $this->sanitize_prompt_param( $request->get_param( 'prompt' ) );
        if ( '' === $prompt ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => __( 'Please provide a prompt.', 'nehtw-gateway' ),
                ),
                400
            );
        }

        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return $this->missing_api_key_response();
        }

        $user_id = get_current_user_id();
        $cost    = $this->get_operation_cost( 'generate' );
        $wallet  = $this->check_user_balance( $user_id, $cost );

        if ( ! $wallet['can_afford'] ) {
            return $this->insufficient_points_response( $wallet['required'], $wallet['balance'] );
        }

        $api_response = nehtw_gateway_api_get(
            '/api/aig/create',
            array( 'prompt' => $prompt )
        );

        if ( is_wp_error( $api_response ) ) {
            return $this->response_from_wp_error( $api_response );
        }

        if ( empty( $api_response['success'] ) || empty( $api_response['job_id'] ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => __( 'Nehtw did not return a job ID.', 'nehtw-gateway' ),
                ),
                500
            );
        }

        $job_id = sanitize_text_field( $api_response['job_id'] );
        $this->insert_job_record(
            $user_id,
            $job_id,
            $prompt,
            'imagine',
            $cost,
            null,
            'pending'
        );

        $deduction = $this->deduct_points( $user_id, 'generate', $cost, $job_id, $prompt );
        if ( is_wp_error( $deduction ) ) {
            return $this->response_from_wp_error( $deduction );
        }

        $balance = $this->get_user_balance( $user_id );

        return new WP_REST_Response(
            array(
                'success'      => true,
                'job_id'       => $job_id,
                'prompt'       => $prompt,
                'status'       => 'pending',
                'percentage'   => 0,
                'cost_points'  => $cost,
                'user_balance' => $balance,
            ),
            200
        );
    }

    /**
     * GET /artly/v1/ai/status?job_id=...
     */
    public function get_status( WP_REST_Request $request ) {
        $job_id = sanitize_text_field( $request->get_param( 'job_id' ) );
        if ( '' === $job_id ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => __( 'job_id is required.', 'nehtw-gateway' ),
                ),
                400
            );
        }

        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return $this->missing_api_key_response();
        }

        $api_response = nehtw_gateway_api_get( '/api/aig/public/' . rawurlencode( $job_id ) );
        if ( is_wp_error( $api_response ) ) {
            return $this->response_from_wp_error( $api_response );
        }

        $status     = $this->normalize_status( isset( $api_response['status'] ) ? $api_response['status'] : '' );
        $percentage = $this->extract_percentage( $api_response );
        $files      = $this->normalize_files( isset( $api_response['files'] ) ? $api_response['files'] : array() );

        $this->update_job_record(
            $job_id,
            array(
                'status'               => $status,
                'percentage_complete'  => $percentage,
                'files'                => $files,
            )
        );

        return new WP_REST_Response(
            array(
                'success'    => true,
                'job_id'     => $job_id,
                'status'     => $status,
                'percentage' => $percentage,
                'files'      => $files,
            ),
            200
        );
    }

    /**
     * POST /artly/v1/ai/action – vary/upscale.
     */
    public function do_action( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( ! is_array( $params ) ) {
            $params = array();
        }

        $job_id    = isset( $params['job_id'] ) ? sanitize_text_field( $params['job_id'] ) : '';
        $action    = isset( $params['action'] ) ? sanitize_key( $params['action'] ) : '';
        $index     = isset( $params['index'] ) ? absint( $params['index'] ) : 0;
        $vary_type = isset( $params['vary_type'] ) ? sanitize_key( $params['vary_type'] ) : '';

        if ( '' === $job_id || '' === $action || ! in_array( $action, array( 'vary', 'upscale' ), true ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => __( 'job_id and valid action are required.', 'nehtw-gateway' ),
                ),
                400
            );
        }

        $api_key = $this->get_api_key();
        if ( empty( $api_key ) ) {
            return $this->missing_api_key_response();
        }

        $user_id = get_current_user_id();
        $cost    = $this->get_operation_cost( $action );
        $wallet  = $this->check_user_balance( $user_id, $cost );

        if ( ! $wallet['can_afford'] ) {
            return $this->insufficient_points_response( $wallet['required'], $wallet['balance'] );
        }

        $payload = array(
            'job_id' => $job_id,
            'action' => $action,
            'index'  => max( 0, min( 3, $index ) ),
        );

        if ( 'vary' === $action && ! empty( $vary_type ) ) {
            $payload['vary_type'] = $vary_type;
        }

        $api_response = nehtw_gateway_api_post_json( '/api/aig/actions', $payload );
        if ( is_wp_error( $api_response ) ) {
            return $this->response_from_wp_error( $api_response );
        }

        if ( empty( $api_response['success'] ) || empty( $api_response['job_id'] ) ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'error'   => __( 'Nehtw action failed to return a job ID.', 'nehtw-gateway' ),
                ),
                500
            );
        }

        $new_job_id = sanitize_text_field( $api_response['job_id'] );
        $parent     = $this->get_job_record( $job_id );
        $prompt     = $parent ? $parent['prompt'] : '';

        $this->insert_job_record(
            $user_id,
            $new_job_id,
            $prompt,
            $action,
            $cost,
            $job_id,
            'pending'
        );

        $deduction = $this->deduct_points( $user_id, $action, $cost, $new_job_id, $prompt, array( 'parent_job_id' => $job_id ) );
        if ( is_wp_error( $deduction ) ) {
            return $this->response_from_wp_error( $deduction );
        }

        $balance = $this->get_user_balance( $user_id );

        return new WP_REST_Response(
            array(
                'success'       => true,
                'job_id'        => $new_job_id,
                'parent_job_id' => $job_id,
                'action'        => $action,
                'cost_points'   => $cost,
                'user_balance'  => $balance,
            ),
            200
        );
    }

    /**
     * GET /artly/v1/ai/history – paginated job list for current user.
     */
    public function get_history( WP_REST_Request $request ) {
        $user_id  = get_current_user_id();
        $page     = max( 1, (int) $request->get_param( 'page' ) );
        $per_page = (int) $request->get_param( 'per_page' );
        $per_page = min( 50, max( 1, $per_page ) );

        $table = $this->get_jobs_table();
        if ( ! $table ) {
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'jobs'    => array(),
                    'pagination' => array(
                        'current_page' => $page,
                        'per_page'     => $per_page,
                        'total_pages'  => 0,
                        'total'        => 0,
                    ),
                ),
                200
            );
        }

        global $wpdb;

        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );
        $offset = ( $page - 1 ) * $per_page;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
                $user_id,
                $per_page,
                $offset
            ),
            ARRAY_A
        );

        $jobs = array();
        if ( $rows ) {
            foreach ( $rows as $row ) {
                $jobs[] = $this->format_history_row( $row );
            }
        }

        $total_pages = $total > 0 ? (int) max( 1, ceil( $total / $per_page ) ) : 0;

        return new WP_REST_Response(
            array(
                'success'    => true,
                'jobs'       => $jobs,
                'pagination' => array(
                    'current_page' => $page,
                    'per_page'     => $per_page,
                    'total_pages'  => $total_pages,
                    'total'        => $total,
                ),
            ),
            200
        );
    }

    /**
     * Retrieve and cache the Nehtw API key.
     */
    protected function get_api_key() {
        if ( function_exists( 'nehtw_gateway_get_api_key' ) ) {
            return nehtw_gateway_get_api_key();
        }

        $settings = get_option( 'nehtw_gateway_settings', array() );
        return isset( $settings['api_key'] ) ? $settings['api_key'] : '';
    }

    /**
     * Get the ai_jobs table name.
     */
    protected function get_jobs_table() {
        if ( function_exists( 'nehtw_gateway_get_table_name' ) ) {
            return nehtw_gateway_get_table_name( 'ai_jobs' );
        }
        global $wpdb;
        return $wpdb->prefix . 'nehtw_ai_jobs';
    }

    /**
     * Get configured cost for an operation.
     */
    protected function get_operation_cost( $operation ) {
        if ( function_exists( 'artly_ai_get_operation_cost_points' ) ) {
            return artly_ai_get_operation_cost_points( $operation );
        }

        $map = array(
            'generate' => 'artly_ai_generate_cost_points',
            'vary'     => 'artly_ai_vary_cost_points',
            'upscale'  => 'artly_ai_upscale_cost_points',
        );

        $defaults = array(
            'generate' => 10,
            'vary'     => 6,
            'upscale'  => 4,
        );

        $key = isset( $map[ $operation ] ) ? $map[ $operation ] : 'artly_ai_generate_cost_points';
        $fallback = isset( $defaults[ $operation ] ) ? $defaults[ $operation ] : $defaults['generate'];

        return (int) get_option( $key, $fallback );
    }

    /**
     * Check if user can afford a cost.
     */
    protected function check_user_balance( $user_id, $cost ) {
        $balance = $this->get_user_balance( $user_id );
        $cost    = max( 0, (int) $cost );

        return array(
            'can_afford' => $cost <= 0 || $balance >= $cost,
            'balance'    => (int) floor( $balance ),
            'required'   => $cost,
        );
    }

    /**
     * Retrieve user wallet balance.
     */
    protected function get_user_balance( $user_id ) {
        if ( function_exists( 'artly_get_user_points' ) ) {
            return (float) artly_get_user_points( $user_id );
        }
        if ( function_exists( 'nehtw_gateway_get_user_points_balance' ) ) {
            return (float) nehtw_gateway_get_user_points_balance( $user_id );
        }
        return 0.0;
    }

    /**
     * Insert a job record locally.
     */
    protected function insert_job_record( $user_id, $job_id, $prompt, $type, $cost, $parent_job_id = null, $status = 'pending' ) {
        $table = $this->get_jobs_table();
        if ( ! $table ) {
            return;
        }

        global $wpdb;
        $now = current_time( 'mysql' );

        $wpdb->insert(
            $table,
            array(
                'user_id'            => (int) $user_id,
                'job_id'             => sanitize_text_field( $job_id ),
                'parent_job_id'      => $parent_job_id ? sanitize_text_field( $parent_job_id ) : null,
                'prompt'             => wp_strip_all_tags( $prompt ),
                'type'               => sanitize_key( $type ),
                'status'             => sanitize_key( $status ),
                'cost_points'        => floatval( $cost ),
                'percentage_complete'=> 0,
                'created_at'         => $now,
                'updated_at'         => $now,
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s' )
        );
    }

    /**
     * Update a job record.
     */
    protected function update_job_record( $job_id, $fields ) {
        $table = $this->get_jobs_table();
        if ( ! $table ) {
            return false;
        }

        global $wpdb;

        $data   = array( 'updated_at' => current_time( 'mysql' ) );
        $format = array( '%s' );

        if ( isset( $fields['status'] ) ) {
            $data['status'] = sanitize_key( $fields['status'] );
            $format[]       = '%s';
        }

        if ( isset( $fields['percentage_complete'] ) ) {
            $data['percentage_complete'] = (int) $fields['percentage_complete'];
            $format[]                    = '%d';
        }

        if ( isset( $fields['files'] ) ) {
            $data['files'] = maybe_serialize( $fields['files'] );
            $format[]      = '%s';
        }

        if ( isset( $fields['prompt'] ) ) {
            $data['prompt'] = wp_strip_all_tags( $fields['prompt'] );
            $format[]       = '%s';
        }

        return $wpdb->update( $table, $data, array( 'job_id' => sanitize_text_field( $job_id ) ), $format, array( '%s' ) );
    }

    /**
     * Fetch a job row by job_id.
     */
    protected function get_job_record( $job_id ) {
        $table = $this->get_jobs_table();
        if ( ! $table ) {
            return null;
        }

        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE job_id = %s LIMIT 1", sanitize_text_field( $job_id ) ),
            ARRAY_A
        );
    }

    /**
     * Deduct points using shared wallet helpers.
     */
    protected function deduct_points( $user_id, $operation, $cost, $job_id, $prompt = '', $extra_meta = array() ) {
        $cost = (float) $cost;
        if ( $cost <= 0 ) {
            return true;
        }

        $meta = array_merge(
            array(
                'job_id'   => $job_id,
                'prompt'   => mb_substr( $prompt, 0, 120 ),
                'source'   => 'ai_generator',
                'operation'=> $operation,
            ),
            $extra_meta
        );

        if ( function_exists( 'artly_deduct_points' ) ) {
            $result = artly_deduct_points( $user_id, $cost, 'ai_' . $operation, $meta );
        } elseif ( function_exists( 'nehtw_gateway_add_transaction' ) ) {
            $result = nehtw_gateway_add_transaction(
                $user_id,
                'ai_' . $operation,
                -abs( $cost ),
                array( 'meta' => $meta )
            );
        } else {
            return new WP_Error( 'artly_wallet_missing', __( 'Points system not available.', 'nehtw-gateway' ) );
        }

        if ( false === $result ) {
            return new WP_Error( 'artly_wallet_failed', __( 'Failed to deduct points.', 'nehtw-gateway' ) );
        }

        return true;
    }

    /**
     * Normalize remote status values.
     */
    protected function normalize_status( $status ) {
        $status = strtolower( (string) $status );
        if ( in_array( $status, array( 'completed', 'done', 'finished' ), true ) ) {
            return 'completed';
        }
        if ( in_array( $status, array( 'failed', 'error', 'cancelled' ), true ) ) {
            return 'failed';
        }
        if ( in_array( $status, array( 'processing', 'running', 'queued' ), true ) ) {
            return 'processing';
        }
        return 'pending';
    }

    /**
     * Extract percentage from multiple possible fields.
     */
    protected function extract_percentage( $payload ) {
        foreach ( array( 'percentage_complete', 'percentage', 'progress' ) as $field ) {
            if ( isset( $payload[ $field ] ) && is_numeric( $payload[ $field ] ) ) {
                return (int) $payload[ $field ];
            }
        }
        return 0;
    }

    /**
     * Normalize files array.
     */
    protected function normalize_files( $files ) {
        if ( empty( $files ) || ! is_array( $files ) ) {
            return array();
        }

        $normalized = array();
        foreach ( $files as $file ) {
            if ( ! is_array( $file ) ) {
                continue;
            }
            $normalized[] = array(
                'index'        => isset( $file['index'] ) ? (int) $file['index'] : count( $normalized ),
                'thumb_sm'     => isset( $file['thumb_sm'] ) ? esc_url_raw( $file['thumb_sm'] ) : '',
                'thumb_lg'     => isset( $file['thumb_lg'] ) ? esc_url_raw( $file['thumb_lg'] ) : '',
                'download_url' => isset( $file['download'] ) ? esc_url_raw( $file['download'] ) : '',
            );
        }

        return $normalized;
    }

    /**
     * Format job row for history response.
     */
    protected function format_history_row( $row ) {
        $files = array();
        if ( isset( $row['files'] ) ) {
            $files = maybe_unserialize( $row['files'] );
            if ( ! is_array( $files ) ) {
                $files = array();
            }
        }

        $preview = '';
        if ( ! empty( $files ) ) {
            if ( function_exists( 'nehtw_gateway_history_extract_ai_thumbnail' ) ) {
                $preview = nehtw_gateway_history_extract_ai_thumbnail( $files );
            } else {
                $preview = isset( $files[0]['thumb_sm'] ) ? $files[0]['thumb_sm'] : '';
            }
        }

        return array(
            'job_id'       => isset( $row['job_id'] ) ? (string) $row['job_id'] : '',
            'prompt'       => isset( $row['prompt'] ) ? (string) $row['prompt'] : '',
            'type'         => isset( $row['type'] ) ? (string) $row['type'] : 'imagine',
            'status'       => $this->normalize_status( isset( $row['status'] ) ? $row['status'] : '' ),
            'percentage'   => isset( $row['percentage_complete'] ) ? (int) $row['percentage_complete'] : 0,
            'cost_points'  => isset( $row['cost_points'] ) ? (float) $row['cost_points'] : 0,
            'created_at'   => isset( $row['created_at'] ) ? $row['created_at'] : '',
            'preview_thumb'=> $preview,
        );
    }

    /**
     * Response helper for missing API key.
     */
    protected function missing_api_key_response() {
        return new WP_REST_Response(
            array(
                'success' => false,
                'error'   => __( 'Nehtw API key is not configured.', 'nehtw-gateway' ),
            ),
            500
        );
    }

    /**
     * Build a standardized response for insufficient points.
     */
    protected function insufficient_points_response( $cost, $balance ) {
        return new WP_REST_Response(
            array(
                'success'      => false,
                'code'         => 'insufficient_points',
                'error'        => __( 'You do not have enough points.', 'nehtw-gateway' ),
                'cost_points'  => (int) $cost,
                'user_balance' => (int) $balance,
            ),
            402
        );
    }

    /**
     * Convert WP_Error into REST response.
     */
    protected function response_from_wp_error( WP_Error $error ) {
        $status = $error->get_error_data();
        if ( is_array( $status ) && isset( $status['status'] ) ) {
            $status = (int) $status['status'];
        } elseif ( is_numeric( $status ) ) {
            $status = (int) $status;
        } else {
            $status = 500;
        }

        return new WP_REST_Response(
            array(
                'success' => false,
                'error'   => $error->get_error_message(),
            ),
            $status
        );
    }
}
