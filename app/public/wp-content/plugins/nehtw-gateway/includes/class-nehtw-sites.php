<?php
/**
 * Nehtw_Sites
 * Core class for managing stock photo sites and their configurations.
 *
 * @package Nehtw_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Sites {
    /**
     * Get all sites.
     *
     * @return array
     */
    public static function all() {
        $cache = get_transient( 'nehtw_sites_cache' );
        if ( false !== $cache && is_array( $cache ) ) {
            return $cache;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_sites';
        
        // Check if table exists by trying to query it
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
        
        if ( ! $table_exists ) {
            // Table doesn't exist, return empty array
            return array();
        }
        
        $rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY label ASC" );

        if ( ! is_array( $rows ) ) {
            $rows = array();
        }

        set_transient( 'nehtw_sites_cache', $rows, 10 * MINUTE_IN_SECONDS );
        return $rows;
    }

    /**
     * Get a single site by site_key.
     *
     * @param string $site_key
     * @return object|null
     */
    public static function get( $site_key ) {
        if ( '' === $site_key ) {
            return null;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_sites';
        
        // Check if table exists
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
        
        if ( ! $table_exists ) {
            return null;
        }
        
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE site_key = %s", $site_key ) );
    }

    /**
     * Update multiple sites.
     *
     * @param array $payload Array of site_key => data pairs
     * @param array $context Optional context (actor_id, source, note)
     * @return void
     */
    public static function update_many( $payload, $context = array() ) {
        if ( empty( $payload ) || ! is_array( $payload ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_sites';
        $now   = current_time( 'mysql' );
        $context = wp_parse_args( $context, array(
            'actor_id' => get_current_user_id(),
            'source'   => 'manual',
        ) );

        foreach ( $payload as $key => $values ) {
            $site_key = sanitize_key( $key );
            if ( '' === $site_key ) {
                continue;
            }

            $row = self::get( $site_key );
            if ( ! $row ) {
                continue;
            }

            $data = array( 'updated_at' => $now );

            if ( isset( $values['status'] ) ) {
                $allowed_status = array( 'active', 'maintenance', 'offline' );
                $status = sanitize_text_field( $values['status'] );
                if ( in_array( $status, $allowed_status, true ) ) {
                    $data['status'] = $status;
                }
            }

            if ( isset( $values['points'] ) ) {
                $data['points_per_file'] = max( 0, (int) $values['points'] );
            }

            if ( isset( $values['label'] ) ) {
                $data['label'] = sanitize_text_field( $values['label'] );
            }

            if ( isset( $values['url'] ) ) {
                $url = esc_url_raw( $values['url'] );
                $data['url'] = ! empty( $url ) ? $url : null;
            }

            if ( count( $data ) <= 1 ) {
                continue;
            }

            $updated = $wpdb->update( $table, $data, array( 'site_key' => $site_key ) );
            if ( false === $updated ) {
                continue;
            }

            self::maybe_log_change( $row, $data, $context );
            self::maybe_trigger_notifications( $row, $data, $context );
        }

        delete_transient( 'nehtw_sites_cache' );
        do_action( 'nehtw_sites_updated', $payload, $context );
    }

    /**
     * Log changes to audit log if enabled.
     *
     * @param object $row Original site row
     * @param array  $data Updated data
     * @param array  $context Context information
     * @return void
     */
    protected static function maybe_log_change( $row, $data, $context ) {
        $old_status = isset( $row->status ) ? $row->status : null;
        $new_status = isset( $data['status'] ) ? $data['status'] : $old_status;
        $old_points = isset( $row->points_per_file ) ? (int) $row->points_per_file : null;
        $new_points = isset( $data['points_per_file'] ) ? (int) $data['points_per_file'] : $old_points;

        if ( $old_status === $new_status && $old_points === $new_points ) {
            return;
        }

        do_action( 'nehtw_sites_status_changed', $row, array(
            'old_status' => $old_status,
            'new_status' => $new_status,
            'old_points' => $old_points,
            'new_points' => $new_points,
            'context'    => $context,
        ) );

        if ( ! nehtw_gateway_is_control_enabled( 'enable_audit_log' ) ) {
            return;
        }

        if ( ! class_exists( 'Nehtw_Audit_Log' ) ) {
            return;
        }

        Nehtw_Audit_Log::log( array(
            'site_key'    => $row->site_key,
            'actor_id'    => isset( $context['actor_id'] ) ? (int) $context['actor_id'] : 0,
            'action'      => 'update_site',
            'old_status'  => $old_status,
            'new_status'  => $new_status,
            'points_from' => $old_points,
            'points_to'   => $new_points,
            'context'     => isset( $context['source'] ) ? $context['source'] : 'manual',
            'note'        => isset( $context['note'] ) ? $context['note'] : '',
        ) );
    }

    /**
     * Trigger notifications if site comes back online.
     *
     * @param object $row Original site row
     * @param array  $data Updated data
     * @param array  $context Context information
     * @return void
     */
    protected static function maybe_trigger_notifications( $row, $data, $context ) {
        if ( empty( $data['status'] ) || 'active' !== $data['status'] || 'active' === $row->status ) {
            return;
        }

        if ( ! nehtw_gateway_is_control_enabled( 'enable_notify_back_online' ) ) {
            return;
        }

        if ( ! class_exists( 'Nehtw_Site_Notifier' ) ) {
            return;
        }

        Nehtw_Site_Notifier::notify_site_online( $row->site_key, $row->label );
    }

    /**
     * Match a site from a URL using regex patterns or site_key matching.
     *
     * @param string $url The URL to match
     * @return object|null
     */
    public static function match_from_url( $url ) {
        if ( empty( $url ) ) {
            return null;
        }

        $sites = self::all();
        foreach ( $sites as $site ) {
            if ( ! empty( $site->regex_pattern ) ) {
                $match = @preg_match( $site->regex_pattern, $url );
                if ( false !== $match && $match > 0 ) {
                    return $site;
                }
            } elseif ( false !== stripos( $url, $site->site_key ) ) {
                return $site;
            }
        }

        return null;
    }
}

