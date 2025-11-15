<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', function() {
    register_rest_route( 'nehtw/v1', '/sites/resolve', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback'            => function( WP_REST_Request $request ) {
            $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
            $key = 'nehtw_resolve_' . md5( $ip );
            $count = (int) get_transient( $key );
            if ( $count > 30 ) {
                return new WP_Error( 'nehtw_rate_limited', __( 'Too many requests. Please slow down.', 'nehtw-gateway' ), array( 'status' => 429 ) );
            }
            set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

            $url = esc_url_raw( (string) $request->get_param( 'url' ) );
            if ( empty( $url ) ) {
                return new WP_Error( 'nehtw_bad_url', __( 'Missing url parameter.', 'nehtw-gateway' ), array( 'status' => 400 ) );
            }

            $row = Nehtw_Sites::match_from_url( $url );
            if ( ! $row ) {
                return array( 'found' => false );
            }

            return array(
                'found'           => true,
                'site_key'        => $row->site_key,
                'label'           => $row->label,
                'status'          => $row->status,
                'points_per_file' => (int) $row->points_per_file,
            );
        },
    ) );

    register_rest_route( 'nehtw/v1', '/sites', array(
        'methods'             => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback'            => function() {
            $out = array();
            foreach ( Nehtw_Sites::all() as $row ) {
                $out[] = array(
                    'site_key'        => $row->site_key,
                    'label'           => $row->label,
                    'status'          => $row->status,
                    'points_per_file' => (int) $row->points_per_file,
                );
            }
            return $out;
        },
    ) );

    register_rest_route( 'nehtw/v1', '/sites/notify', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'permission_callback' => '__return_true',
        'callback'            => function( WP_REST_Request $request ) {
            if ( ! nehtw_gateway_is_control_enabled( 'enable_notify_back_online' ) ) {
                return new WP_Error( 'nehtw_notify_disabled', __( 'Notifications are disabled.', 'nehtw-gateway' ), array( 'status' => 400 ) );
            }

            $site_key = sanitize_key( $request->get_param( 'site_key' ) );
            $email    = sanitize_email( $request->get_param( 'email' ) );
            $user_id  = get_current_user_id();

            $result = Nehtw_Site_Notifier::subscribe( $site_key, $email, $user_id );
            if ( is_wp_error( $result ) ) {
                $result->add_data( array( 'status' => 400 ) );
                return $result;
            }

            return array( 'success' => true );
        },
    ) );
} );