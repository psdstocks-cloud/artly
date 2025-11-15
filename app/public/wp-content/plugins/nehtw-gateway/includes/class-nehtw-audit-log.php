<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Audit_Log {
    public static function log( $entry ) {
        if ( ! nehtw_gateway_is_control_enabled( 'enable_audit_log' ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_audit';
        $now   = current_time( 'mysql' );

        $wpdb->insert( $table, array(
            'actor_id'    => isset( $entry['actor_id'] ) ? (int) $entry['actor_id'] : null,
            'action'      => isset( $entry['action'] ) ? sanitize_text_field( $entry['action'] ) : 'update_site',
            'site_key'    => isset( $entry['site_key'] ) ? sanitize_key( $entry['site_key'] ) : '',
            'old_status'  => isset( $entry['old_status'] ) ? sanitize_text_field( $entry['old_status'] ) : null,
            'new_status'  => isset( $entry['new_status'] ) ? sanitize_text_field( $entry['new_status'] ) : null,
            'points_from' => isset( $entry['points_from'] ) ? (int) $entry['points_from'] : null,
            'points_to'   => isset( $entry['points_to'] ) ? (int) $entry['points_to'] : null,
            'context'     => isset( $entry['context'] ) ? sanitize_text_field( $entry['context'] ) : '',
            'note'        => isset( $entry['note'] ) ? sanitize_text_field( $entry['note'] ) : '',
            'created_at'  => $now,
        ) );
    }

    public static function query( $args = array() ) {
        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_audit';

        $defaults = array(
            'site_key' => '',
            'date'     => '',
            'limit'    => 50,
        );
        $args = wp_parse_args( $args, $defaults );

        $where = array();
        $params = array();

        if ( $args['site_key'] ) {
            $where[]  = 'site_key = %s';
            $params[] = sanitize_key( $args['site_key'] );
        }

        if ( $args['date'] ) {
            $where[]  = 'DATE(created_at) = %s';
            $params[] = sanitize_text_field( $args['date'] );
        }

        $sql = "SELECT * FROM {$table}";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY created_at DESC LIMIT %d';
        $params[] = (int) $args['limit'];

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }
}