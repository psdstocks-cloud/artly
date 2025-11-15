<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Maint_Scheduler {
    const OPTION_KEY = 'nehtw_maint_events';

    public static function init() {
        add_filter( 'cron_schedules', array( __CLASS__, 'register_schedule' ) );
        add_action( 'nehtw_maint_tick', array( __CLASS__, 'process_events' ) );
    }

    public static function register_schedule( $schedules ) {
        $schedules['nehtw_every5'] = array(
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display'  => __( 'Every 5 minutes', 'nehtw-gateway' ),
        );
        return $schedules;
    }

    public static function schedule_event() {
        if ( wp_next_scheduled( 'nehtw_maint_tick' ) ) {
            return;
        }
        wp_schedule_event( time() + 120, 'nehtw_every5', 'nehtw_maint_tick' );
    }

    public static function get_events() {
        $events = get_option( self::OPTION_KEY, array() );
        if ( ! is_array( $events ) ) {
            $events = array();
        }
        return $events;
    }

    public static function save_events( $events ) {
        update_option( self::OPTION_KEY, array_values( $events ) );
    }

    public static function add_event( $event ) {
        $events   = self::get_events();
        $event['id'] = isset( $event['id'] ) ? $event['id'] : uniqid( 'nehtw_', true );
        $events[] = $event;
        self::save_events( $events );
    }

    public static function remove_event( $event_id ) {
        $events = self::get_events();
        $filtered = array();
        foreach ( $events as $event ) {
            if ( $event['id'] === $event_id ) {
                continue;
            }
            $filtered[] = $event;
        }
        self::save_events( $filtered );
    }

    public static function process_events() {
        if ( ! nehtw_gateway_is_control_enabled( 'enable_scheduled_maintenance' ) ) {
            return;
        }

        $events = self::get_events();
        if ( empty( $events ) ) {
            return;
        }

        $now      = current_time( 'timestamp' );
        $changed  = false;
        $updated  = array();

        foreach ( $events as $index => $event ) {
            $site_key = isset( $event['site_key'] ) ? sanitize_key( $event['site_key'] ) : '';
            if ( '' === $site_key ) {
                continue;
            }

            $start = isset( $event['start'] ) ? (int) $event['start'] : 0;
            $end   = isset( $event['end'] ) ? (int) $event['end'] : 0;
            $target_status = isset( $event['status'] ) ? sanitize_text_field( $event['status'] ) : 'maintenance';

            $row = Nehtw_Sites::get( $site_key );
            if ( ! $row ) {
                continue;
            }

            if ( $start && $now >= $start && $row->status !== $target_status ) {
                Nehtw_Sites::update_many( array(
                    $site_key => array(
                        'status' => $target_status,
                        'points' => $row->points_per_file,
                    ),
                ), array(
                    'source'   => 'scheduler',
                    'actor_id' => 0,
                    'note'     => isset( $event['note'] ) ? $event['note'] : '',
                ) );
                $changed = true;
                $row = Nehtw_Sites::get( $site_key );
            }

            if ( $end && $now >= $end && $row && 'active' !== $row->status ) {
                Nehtw_Sites::update_many( array(
                    $site_key => array(
                        'status' => 'active',
                        'points' => $row->points_per_file,
                    ),
                ), array(
                    'source'   => 'scheduler',
                    'actor_id' => 0,
                    'note'     => isset( $event['note'] ) ? $event['note'] : '',
                ) );
                $changed = true;
                $event['completed'] = true;
                $row = Nehtw_Sites::get( $site_key );
            }

            $updated[] = $event;
        }

        $filtered = array();
        foreach ( $updated as $event ) {
            if ( ! empty( $event['completed'] ) ) {
                continue;
            }
            $filtered[] = $event;
        }

        self::save_events( $filtered );

        if ( $changed ) {
            do_action( 'nehtw_sites_status_changed_batch' );
        }
    }
}
Nehtw_Maint_Scheduler::init();
