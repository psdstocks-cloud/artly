<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function nehtw_seed_sites_if_empty() {
    global $wpdb;
    $table = $wpdb->prefix . 'nehtw_sites';
    $has_rows = $wpdb->get_var( "SELECT COUNT(1) FROM {$table}" );

    if ( $has_rows ) {
        return;
    }

    $now      = current_time( 'mysql' );
    $defaults = array(
        array(
            'site_key'        => 'shutterstock',
            'label'           => 'Shutterstock',
            'regex_pattern'   => '/(shutterstock\\.com)/',
            'points_per_file' => 10,
        ),
        array(
            'site_key'        => 'adobestock',
            'label'           => 'Adobe Stock',
            'regex_pattern'   => '/(stock\\.adobe\\.com)/',
            'points_per_file' => 10,
        ),
        array(
            'site_key'        => 'freepik',
            'label'           => 'Freepik',
            'regex_pattern'   => '/(freepik\\.com)/',
            'points_per_file' => 6,
        ),
    );

    foreach ( $defaults as $row ) {
        $row['created_at'] = $now;
        $row['updated_at'] = $now;
        $wpdb->insert( $table, $row );
    }
}
add_action( 'admin_init', 'nehtw_seed_sites_if_empty' );
