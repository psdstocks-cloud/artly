<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Activator {
    public static function activate() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $sites_table = $wpdb->prefix . 'nehtw_sites';
        $notify_table = $wpdb->prefix . 'nehtw_notify';
        $audit_table = $wpdb->prefix . 'nehtw_audit';

        $sql_sites = "CREATE TABLE {$sites_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_key VARCHAR(40) NOT NULL UNIQUE,
            label VARCHAR(80) NOT NULL,
            status ENUM('active','maintenance','offline') NOT NULL DEFAULT 'active',
            points_per_file INT UNSIGNED NOT NULL DEFAULT 0,
            regex_pattern VARCHAR(255) NULL,
            url VARCHAR(255) NULL,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        ) {$charset};";

        $sql_notify = "CREATE TABLE {$notify_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            site_key VARCHAR(40) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            email VARCHAR(191) NOT NULL,
            created_at DATETIME NOT NULL,
            KEY site_key (site_key),
            KEY email (email)
        ) {$charset};";

        $sql_audit = "CREATE TABLE {$audit_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            actor_id BIGINT UNSIGNED NULL,
            action VARCHAR(40) NOT NULL,
            site_key VARCHAR(40) NOT NULL,
            old_status VARCHAR(20) NULL,
            new_status VARCHAR(20) NULL,
            points_from INT NULL,
            points_to INT NULL,
            context VARCHAR(40) NULL,
            note TEXT NULL,
            created_at DATETIME NOT NULL,
            KEY site_key (site_key),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta( $sql_sites );
        dbDelta( $sql_notify );
        dbDelta( $sql_audit );

        self::ensure_capability();

        if ( class_exists( 'Nehtw_Maint_Scheduler' ) ) {
            Nehtw_Maint_Scheduler::schedule_event();
        }
    }

    protected static function ensure_capability() {
        $role = get_role( 'administrator' );
        if ( $role && ! $role->has_cap( 'manage_nehtw' ) ) {
            $role->add_cap( 'manage_nehtw' );
        }
    }
}