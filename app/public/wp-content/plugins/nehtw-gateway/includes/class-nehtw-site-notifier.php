<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Site_Notifier {
    public static function subscribe( $site_key, $email, $user_id = 0 ) {
        if ( ! nehtw_gateway_is_control_enabled( 'enable_notify_back_online' ) ) {
            return new WP_Error( 'nehtw_notify_disabled', __( 'Notifications are disabled.', 'nehtw-gateway' ) );
        }

        if ( ! is_email( $email ) ) {
            return new WP_Error( 'nehtw_bad_email', __( 'Please enter a valid email.', 'nehtw-gateway' ) );
        }

        $site = Nehtw_Sites::get( $site_key );
        if ( ! $site ) {
            return new WP_Error( 'nehtw_site_unknown', __( 'Unsupported website.', 'nehtw-gateway' ) );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_notify';
        $now   = current_time( 'mysql' );

        $wpdb->insert( $table, array(
            'site_key'   => $site_key,
            'user_id'    => $user_id ? (int) $user_id : null,
            'email'      => sanitize_email( $email ),
            'created_at' => $now,
        ) );

        return true;
    }

    public static function notify_site_online( $site_key, $label ) {
        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_notify';

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE site_key = %s", $site_key ) );
        if ( empty( $rows ) ) {
            return;
        }

        $subject = sprintf( __( '%s is back online', 'nehtw-gateway' ), $label );
        $body    = sprintf(
            '<p><strong>%1$s</strong> %2$s</p><p><a href="%3$s" style="display:inline-block;padding:10px 18px;background:#6c5ce7;color:#fff;border-radius:999px;text-decoration:none;">%4$s</a></p>',
            esc_html( $label ),
            esc_html__( 'is active again. You can resume your downloads.', 'nehtw-gateway' ),
            esc_url( home_url( '/stock/' ) ),
            esc_html__( 'Go to Stock Page', 'nehtw-gateway' )
        );

        foreach ( $rows as $row ) {
            $email = sanitize_email( $row->email );
            if ( '' === $email ) {
                continue;
            }
            wp_mail( $email, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
        }

        $wpdb->delete( $table, array( 'site_key' => $site_key ) );
    }
}