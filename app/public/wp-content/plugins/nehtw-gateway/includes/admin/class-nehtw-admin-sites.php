<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Admin_Sites {
    public static function init() {
        // Register menu with priority 20 to ensure parent menu exists
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
    }

    public static function register_menu() {

        add_submenu_page(
            'nehtw-gateway',
            __( 'Sites & Points', 'nehtw-gateway' ),
            __( 'Sites & Points', 'nehtw-gateway' ),
            'manage_options',
            'nehtw-sites',
            array( __CLASS__, 'render' )
        );

        add_submenu_page(
            'nehtw-gateway',
            __( 'Logs', 'nehtw-gateway' ),
            __( 'Logs', 'nehtw-gateway' ),
            'manage_options',
            'nehtw-logs',
            array( __CLASS__, 'render_logs' )
        );
    }

    public static function enqueue_assets( $hook ) {
        if ( false === strpos( $hook, 'nehtw-sites' ) && false === strpos( $hook, 'nehtw-logs' ) ) {
            return;
        }

        wp_enqueue_style(
            'nehtw-sites-admin',
            NEHTW_GATEWAY_PLUGIN_URL . 'assets/css/nehtw-sites-admin.css',
            array(),
            NEHTW_GATEWAY_VERSION
        );
    }

    protected static function current_user_can_manage() {
        return current_user_can( 'manage_options' );
    }

    public static function render() {
        if ( ! self::current_user_can_manage() ) {
            wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
        }

        $notice = '';
        
        // Handle sync all sites
        if ( isset( $_POST['nehtw_sync_sites'] ) && wp_verify_nonce( $_POST['nehtw_sync_sites'], 'nehtw_sync_all_sites' ) ) {
            if ( function_exists( 'nehtw_sync_all_sites' ) ) {
                $synced = nehtw_sync_all_sites();
                if ( $synced ) {
                    // Force clear all caches
                    delete_transient( 'nehtw_sites_cache' );
                    if ( function_exists( 'wp_cache_flush' ) ) {
                        wp_cache_flush();
                    }
                    $notice = __( 'All sites synced successfully. Missing sites have been added and patterns updated. Cache cleared.', 'nehtw-gateway' );
                } else {
                    $notice = __( 'Failed to sync sites. Please check database table exists.', 'nehtw-gateway' );
                }
            }
        }
        
        if ( isset( $_POST['nehtw_sites_nonce'] ) && wp_verify_nonce( $_POST['nehtw_sites_nonce'], 'nehtw_sites_save' ) ) {
            $payload = array();
            $statuses = isset( $_POST['status'] ) ? (array) wp_unslash( $_POST['status'] ) : array();
            $points   = isset( $_POST['points'] ) ? (array) wp_unslash( $_POST['points'] ) : array();
            $labels   = isset( $_POST['labels'] ) ? (array) wp_unslash( $_POST['labels'] ) : array();
            $urls     = isset( $_POST['urls'] ) ? (array) wp_unslash( $_POST['urls'] ) : array();

            foreach ( $statuses as $key => $value ) {
                $payload[ $key ] = array(
                    'status' => sanitize_text_field( $value ),
                    'points' => isset( $points[ $key ] ) ? (int) $points[ $key ] : 0,
                );
                
                // Add label if provided
                if ( isset( $labels[ $key ] ) ) {
                    $payload[ $key ]['label'] = sanitize_text_field( $labels[ $key ] );
                }
                
                // Add URL if provided
                if ( isset( $urls[ $key ] ) ) {
                    $payload[ $key ]['url'] = esc_url_raw( trim( $urls[ $key ] ) );
                }
            }

            if ( ! empty( $payload ) ) {
                Nehtw_Sites::update_many( $payload, array( 'source' => 'admin' ) );
                // Clear cache after update
                delete_transient( 'nehtw_sites_cache' );
                if ( function_exists( 'wp_cache_flush' ) ) {
                    wp_cache_flush();
                }
                $notice = __( 'Sites updated. Cache cleared.', 'nehtw-gateway' );
            }
        }

        if ( nehtw_gateway_is_control_enabled( 'enable_scheduled_maintenance' ) ) {
            self::handle_scheduler_form();
        }

        $sites = Nehtw_Sites::all();
        
        // Check if table exists
        global $wpdb;
        $table = $wpdb->prefix . 'nehtw_sites';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
        ?>
        <div class="wrap nehtw-sites-admin">
            <h1><?php esc_html_e( 'Sites & Points', 'nehtw-gateway' ); ?></h1>
            <?php if ( $notice ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
            <?php endif; ?>
            
            <div style="margin: 20px 0;">
                <form method="post" style="display: inline-block;">
                    <?php wp_nonce_field( 'nehtw_sync_all_sites', 'nehtw_sync_sites' ); ?>
                    <button type="submit" class="button button-secondary" name="sync_sites" value="1">
                        <?php esc_html_e( 'Sync All Sites', 'nehtw-gateway' ); ?>
                    </button>
                </form>
                <p class="description" style="margin-top: 5px;">
                    <?php esc_html_e( 'Click to add missing sites and update regex patterns from the latest configuration.', 'nehtw-gateway' ); ?>
                </p>
            </div>
            <?php if ( ! $table_exists ) : ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e( 'The sites table does not exist. Please deactivate and reactivate the Nehtw Gateway plugin to create the required database tables.', 'nehtw-gateway' ); ?></p>
                </div>
            <?php elseif ( empty( $sites ) ) : ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e( 'No sites configured. Sites will be added automatically when you place your first stock order.', 'nehtw-gateway' ); ?></p>
                </div>
            <?php endif; ?>
            <form method="post" class="nehtw-glass-block">
                <?php wp_nonce_field( 'nehtw_sites_save', 'nehtw_sites_nonce' ); ?>
                <table class="widefat fixed striped nehtw-sites-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Display Name', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Website URL', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Points per file', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'URL match', 'nehtw-gateway' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $sites as $site ) : ?>
                            <tr>
                                <td>
                                    <input 
                                        type="text" 
                                        name="labels[<?php echo esc_attr( $site->site_key ); ?>]" 
                                        value="<?php echo esc_attr( $site->label ); ?>" 
                                        class="regular-text"
                                        placeholder="<?php esc_attr_e( 'Display name', 'nehtw-gateway' ); ?>"
                                    />
                                    <div style="margin-top: 4px;">
                                        <code style="font-size: 11px; color: #666;"><?php echo esc_html( $site->site_key ); ?></code>
                                        <span class="nehtw-chip nehtw-chip-<?php echo esc_attr( $site->status ); ?>" style="margin-left: 8px;">
                                            <?php echo esc_html( ucfirst( $site->status ) ); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <input 
                                        type="url" 
                                        name="urls[<?php echo esc_attr( $site->site_key ); ?>]" 
                                        value="<?php echo esc_attr( $site->url ?? '' ); ?>" 
                                        class="regular-text"
                                        placeholder="<?php esc_attr_e( 'https://example.com', 'nehtw-gateway' ); ?>"
                                    />
                                    <p class="description" style="margin: 4px 0 0; font-size: 11px;">
                                        <?php esc_html_e( 'Link shown on frontend', 'nehtw-gateway' ); ?>
                                    </p>
                                </td>
                                <td>
                                    <select name="status[<?php echo esc_attr( $site->site_key ); ?>]">
                                        <?php foreach ( array( 'active' => __( 'Active', 'nehtw-gateway' ), 'maintenance' => __( 'Maintenance', 'nehtw-gateway' ), 'offline' => __( 'Offline', 'nehtw-gateway' ) ) as $value => $label ) : ?>
                                            <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $site->status, $value ); ?>><?php echo esc_html( $label ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" min="0" name="points[<?php echo esc_attr( $site->site_key ); ?>]" value="<?php echo esc_attr( $site->points_per_file ); ?>" />
                                </td>
                                <td>
                                    <code style="font-size: 11px;"><?php echo $site->regex_pattern ? esc_html( $site->regex_pattern ) : '—'; ?></code>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save changes', 'nehtw-gateway' ); ?></button></p>
            </form>

            <?php if ( nehtw_gateway_is_control_enabled( 'enable_scheduled_maintenance' ) ) : ?>
                <?php self::render_scheduler_panel(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    protected static function handle_scheduler_form() {
        if ( isset( $_POST['nehtw_maint_nonce'] ) && wp_verify_nonce( $_POST['nehtw_maint_nonce'], 'nehtw_maint_event' ) ) {
            $action = isset( $_POST['maint_action'] ) ? sanitize_text_field( wp_unslash( $_POST['maint_action'] ) ) : '';
            if ( 'add' === $action ) {
                $site_key = isset( $_POST['maint_site'] ) ? sanitize_key( wp_unslash( $_POST['maint_site'] ) ) : '';
                $status   = isset( $_POST['maint_status'] ) ? sanitize_text_field( wp_unslash( $_POST['maint_status'] ) ) : 'maintenance';
                $start    = isset( $_POST['maint_start'] ) ? strtotime( sanitize_text_field( wp_unslash( $_POST['maint_start'] ) ) ) : 0;
                $end      = isset( $_POST['maint_end'] ) ? strtotime( sanitize_text_field( wp_unslash( $_POST['maint_end'] ) ) ) : 0;
                $note     = isset( $_POST['maint_note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['maint_note'] ) ) : '';

                if ( $site_key && $start ) {
                    Nehtw_Maint_Scheduler::add_event( array(
                        'site_key' => $site_key,
                        'status'   => $status,
                        'start'    => $start,
                        'end'      => $end,
                        'note'     => $note,
                    ) );
                }
            } elseif ( 'delete' === $action ) {
                $event_id = isset( $_POST['event_id'] ) ? sanitize_text_field( wp_unslash( $_POST['event_id'] ) ) : '';
                if ( $event_id ) {
                    Nehtw_Maint_Scheduler::remove_event( $event_id );
                }
            }
        }
    }

    protected static function render_scheduler_panel() {
        $sites  = Nehtw_Sites::all();
        $events = Nehtw_Maint_Scheduler::get_events();
        ?>
        <div class="nehtw-glass-block nehtw-maint-panel">
            <h2><?php esc_html_e( 'Scheduled Maintenance', 'nehtw-gateway' ); ?></h2>
            <form method="post" class="nehtw-maint-form">
                <?php wp_nonce_field( 'nehtw_maint_event', 'nehtw_maint_nonce' ); ?>
                <input type="hidden" name="maint_action" value="add" />
                <div class="nehtw-maint-grid">
                    <label>
                        <?php esc_html_e( 'Provider', 'nehtw-gateway' ); ?>
                        <select name="maint_site" required>
                            <?php foreach ( $sites as $site ) : ?>
                                <option value="<?php echo esc_attr( $site->site_key ); ?>"><?php echo esc_html( $site->label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <?php esc_html_e( 'Start (local time)', 'nehtw-gateway' ); ?>
                        <input type="datetime-local" name="maint_start" required />
                    </label>
                    <label>
                        <?php esc_html_e( 'End (optional)', 'nehtw-gateway' ); ?>
                        <input type="datetime-local" name="maint_end" />
                    </label>
                    <label>
                        <?php esc_html_e( 'Status during window', 'nehtw-gateway' ); ?>
                        <select name="maint_status">
                            <option value="maintenance"><?php esc_html_e( 'Maintenance', 'nehtw-gateway' ); ?></option>
                            <option value="offline"><?php esc_html_e( 'Offline', 'nehtw-gateway' ); ?></option>
                        </select>
                    </label>
                </div>
                <label>
                    <?php esc_html_e( 'Note', 'nehtw-gateway' ); ?>
                    <textarea name="maint_note" rows="2" placeholder="<?php esc_attr_e( 'Optional note for audit log', 'nehtw-gateway' ); ?>"></textarea>
                </label>
                <p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Schedule window', 'nehtw-gateway' ); ?></button></p>
            </form>
            <?php if ( ! empty( $events ) ) : ?>
                <h3><?php esc_html_e( 'Upcoming windows', 'nehtw-gateway' ); ?></h3>
                <table class="widefat fixed">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Provider', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Start', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'End', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Note', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nehtw-gateway' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $events as $event ) : ?>
                            <tr>
                                <td><?php echo esc_html( $event['site_key'] ); ?></td>
                                <td><?php echo esc_html( self::format_timestamp( $event['start'] ) ); ?></td>
                                <td><?php echo $event['end'] ? esc_html( self::format_timestamp( $event['end'] ) ) : '—'; ?></td>
                                <td><?php echo esc_html( ucfirst( $event['status'] ) ); ?></td>
                                <td><?php echo ! empty( $event['note'] ) ? esc_html( $event['note'] ) : '—'; ?></td>
                                <td>
                                    <form method="post">
                                        <?php wp_nonce_field( 'nehtw_maint_event', 'nehtw_maint_nonce' ); ?>
                                        <input type="hidden" name="maint_action" value="delete" />
                                        <input type="hidden" name="event_id" value="<?php echo esc_attr( $event['id'] ); ?>" />
                                        <button type="submit" class="button button-small">
                                            <?php esc_html_e( 'Remove', 'nehtw-gateway' ); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    protected static function format_timestamp( $timestamp ) {
        if ( ! $timestamp ) {
            return '—';
        }
        $offset = get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS;
        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp + $offset );
    }

    public static function render_logs() {
        if ( ! self::current_user_can_manage() ) {
            wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
        }

        $site = isset( $_GET['log_site'] ) ? sanitize_key( wp_unslash( $_GET['log_site'] ) ) : '';
        $date = isset( $_GET['log_date'] ) ? sanitize_text_field( wp_unslash( $_GET['log_date'] ) ) : '';

        if ( ! nehtw_gateway_is_control_enabled( 'enable_audit_log' ) ) {
            echo '<div class="wrap"><h1>' . esc_html__( 'Provider Logs', 'nehtw-gateway' ) . '</h1><p>' . esc_html__( 'Enable the audit log from Settings → Advanced Controls to start tracking changes.', 'nehtw-gateway' ) . '</p></div>';
            return;
        }

        $logs = Nehtw_Audit_Log::query( array( 'site_key' => $site, 'date' => $date ) );
        ?>
        <div class="wrap nehtw-sites-admin">
            <h1><?php esc_html_e( 'Provider Logs', 'nehtw-gateway' ); ?></h1>
            <form method="get" class="nehtw-glass-block" style="margin-bottom:20px;">
                <input type="hidden" name="page" value="nehtw-logs" />
                <label>
                    <?php esc_html_e( 'Site key', 'nehtw-gateway' ); ?>
                    <input type="text" name="log_site" value="<?php echo esc_attr( $site ); ?>" />
                </label>
                <label>
                    <?php esc_html_e( 'Date (YYYY-MM-DD)', 'nehtw-gateway' ); ?>
                    <input type="text" name="log_date" value="<?php echo esc_attr( $date ); ?>" />
                </label>
                <p><button type="submit" class="button button-secondary"><?php esc_html_e( 'Filter', 'nehtw-gateway' ); ?></button></p>
            </form>
            <?php if ( empty( $logs ) ) : ?>
                <p><?php esc_html_e( 'No log entries found.', 'nehtw-gateway' ); ?></p>
            <?php else : ?>
                <table class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Site', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Points', 'nehtw-gateway' ); ?></th>
                            <th><?php esc_html_e( 'Context', 'nehtw-gateway' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $log->created_at ) ) ); ?></td>
                                <td><?php echo esc_html( $log->site_key ); ?></td>
                                <td><?php echo esc_html( $log->action ); ?></td>
                                <td><?php echo esc_html( $log->old_status . ' → ' . $log->new_status ); ?></td>
                                <td><?php echo esc_html( $log->points_from . ' → ' . $log->points_to ); ?></td>
                                <td><?php echo esc_html( $log->context ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }
}
Nehtw_Admin_Sites::init();