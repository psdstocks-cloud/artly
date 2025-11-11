<?php
/**
 * Dunning Manager Admin Page
 * 
 * Admin interface for managing dunning emails and viewing dunning history
 * 
 * @package Nehtw_Gateway
 * @subpackage Billing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Dunning_Admin {
    
    /**
     * Initialize admin page
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_page' ], 25 );
        add_action( 'admin_post_nehtw_send_dunning_email', [ $this, 'handle_send_dunning_email' ] );
        add_action( 'admin_post_nehtw_cancel_dunning_sequence', [ $this, 'handle_cancel_dunning' ] );
        add_action( 'admin_post_nehtw_process_dunning_queue', [ $this, 'handle_process_dunning_queue' ] );
        add_action( 'admin_post_nehtw_test_dunning_email', [ $this, 'handle_test_dunning_email' ] );
    }
    
    /**
     * Add admin page
     */
    public function add_admin_page() {
        add_submenu_page(
            'nehtw-gateway',
            __( 'Dunning Management', 'nehtw-gateway' ),
            __( 'Dunning Management', 'nehtw-gateway' ),
            'manage_options',
            'nehtw-dunning-management',
            [ $this, 'render_admin_page' ]
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
        }
        
        global $wpdb;
        
        // Get current tab
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overview';
        
        // Handle tab-specific rendering
        if ( $tab === 'templates' ) {
            $this->render_templates_tab();
            return;
        } elseif ( $tab === 'schedule' ) {
            $this->render_schedule_tab();
            return;
        } elseif ( $tab === 'automation' ) {
            $this->render_automation_tab();
            return;
        } elseif ( $tab === 'email-service' ) {
            $this->render_email_service_tab();
            return;
        }
        
        // Default to overview tab
        $tab = 'overview';
        
        // Get current page and filters
        $paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $offset = ( $paged - 1 ) * $per_page;
        
        $status_filter = isset( $_GET['status_filter'] ) ? sanitize_text_field( $_GET['status_filter'] ) : '';
        $level_filter = isset( $_GET['level_filter'] ) ? intval( $_GET['level_filter'] ) : 0;
        
        // Get dunning emails
        $where = [];
        $where_values = [];
        
        if ( $level_filter > 0 ) {
            $where[] = 'd.dunning_level = %d';
            $where_values[] = $level_filter;
        }
        
        $where_sql = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        
        $dunning_emails = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT d.*, 
                        i.invoice_number,
                        i.total_amount,
                        i.status as invoice_status,
                        u.display_name as user_name,
                        u.user_email,
                        s.plan_key,
                        s.status as subscription_status
                 FROM {$wpdb->prefix}nehtw_dunning_emails d
                 LEFT JOIN {$wpdb->prefix}nehtw_invoices i ON i.id = d.invoice_id
                 LEFT JOIN {$wpdb->prefix}users u ON u.ID = d.user_id
                 LEFT JOIN {$wpdb->prefix}nehtw_subscriptions s ON s.id = d.subscription_id
                 {$where_sql}
                 ORDER BY d.sent_at DESC
                 LIMIT %d OFFSET %d",
                array_merge( $where_values, [ $per_page, $offset ] )
            ),
            ARRAY_A
        );
        
        $total_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_dunning_emails d {$where_sql}",
                $where_values
            )
        );
        
        // Get subscriptions with active dunning
        $active_dunning = $wpdb->get_results(
            "SELECT s.*, 
                    i.id as invoice_id,
                    i.invoice_number,
                    i.total_amount,
                    u.display_name as user_name,
                    u.user_email
             FROM {$wpdb->prefix}nehtw_subscriptions s
             LEFT JOIN {$wpdb->prefix}nehtw_invoices i ON i.subscription_id = s.id AND i.status = 'pending'
             LEFT JOIN {$wpdb->prefix}users u ON u.ID = s.user_id
             WHERE s.dunning_level > 0
             AND s.status = 'active'
             ORDER BY s.dunning_level DESC, s.last_payment_attempt DESC
             LIMIT 50",
            ARRAY_A
        );
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Dunning Management', 'nehtw-gateway' ); ?></h1>
            
            <!-- Tabs -->
            <nav class="nav-tab-wrapper" style="margin: 20px 0;">
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=overview' ); ?>" class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Overview', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=templates' ); ?>" class="nav-tab <?php echo $tab === 'templates' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Email Templates', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=schedule' ); ?>" class="nav-tab <?php echo $tab === 'schedule' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Schedule', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=automation' ); ?>" class="nav-tab <?php echo $tab === 'automation' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Automation', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=email-service' ); ?>" class="nav-tab <?php echo $tab === 'email-service' ? 'nav-tab-active' : ''; ?>"><?php _e( 'Email Service', 'nehtw-gateway' ); ?></a>
            </nav>
            
            <?php
            // Show admin notices
            if ( isset( $_GET['dunning_sent'] ) && $_GET['dunning_sent'] == '1' ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Dunning email sent successfully.', 'nehtw-gateway' ) . '</p></div>';
            }
            if ( isset( $_GET['dunning_error'] ) && $_GET['dunning_error'] == '1' ) {
                echo '<div class="notice notice-error is-dismissible"><p>' . __( 'Failed to send dunning email.', 'nehtw-gateway' ) . '</p></div>';
            }
            if ( isset( $_GET['dunning_cancelled'] ) && $_GET['dunning_cancelled'] == '1' ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Dunning sequence cancelled successfully.', 'nehtw-gateway' ) . '</p></div>';
            }
            if ( isset( $_GET['queue_processed'] ) && $_GET['queue_processed'] == '1' ) {
                $sent = isset( $_GET['sent'] ) ? intval( $_GET['sent'] ) : 0;
                $failed = isset( $_GET['failed'] ) ? intval( $_GET['failed'] ) : 0;
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Dunning queue processed: %d emails sent, %d failed.', 'nehtw-gateway' ), $sent, $failed ) . '</p></div>';
            }
            ?>
            
            <div class="nehtw-dunning-admin">
                <!-- Stats Cards -->
                <div class="nehtw-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                    <?php
                    $stats = [
                        'total_sent' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_dunning_emails" ),
                        'active_dunning' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_subscriptions WHERE dunning_level > 0 AND status = 'active'" ),
                        'level_1' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_dunning_emails WHERE dunning_level = 1" ),
                        'level_4' => $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_dunning_emails WHERE dunning_level = 4" ),
                    ];
                    ?>
                    <div class="nehtw-stat-card">
                        <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo esc_html( $stats['total_sent'] ); ?></div>
                        <div style="color: #646970; margin-top: 8px;">Total Emails Sent</div>
                    </div>
                    <div class="nehtw-stat-card">
                        <div style="font-size: 32px; font-weight: bold; color: #dba617;"><?php echo esc_html( $stats['active_dunning'] ); ?></div>
                        <div style="color: #646970; margin-top: 8px;">Active Dunning</div>
                    </div>
                    <div class="nehtw-stat-card">
                        <div style="font-size: 32px; font-weight: bold; color: #d63638;"><?php echo esc_html( $stats['level_4'] ); ?></div>
                        <div style="color: #646970; margin-top: 8px;">Cancelled (Level 4)</div>
                    </div>
                </div>
                
                <!-- Active Dunning Subscriptions -->
                <div class="nehtw-glass-card">
                    <h2><?php _e( 'Active Dunning Subscriptions', 'nehtw-gateway' ); ?></h2>
                    <?php if ( ! empty( $active_dunning ) ) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e( 'User', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Plan', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Invoice', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Amount', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Dunning Level', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Last Attempt', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Actions', 'nehtw-gateway' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $active_dunning as $sub ) : ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html( $sub['user_name'] ); ?></strong><br>
                                            <small><?php echo esc_html( $sub['user_email'] ); ?></small>
                                        </td>
                                        <td><?php echo esc_html( $sub['plan_key'] ); ?></td>
                                        <td><?php echo esc_html( $sub['invoice_number'] ?? 'N/A' ); ?></td>
                                        <td>$<?php echo esc_html( number_format( $sub['total_amount'] ?? 0, 2 ) ); ?></td>
                                        <td>
                                            <span class="nehtw-badge" style="background: rgba(255, 63, 111, 0.2); color: #ff3f6f; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                                Level <?php echo esc_html( $sub['dunning_level'] ); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html( $sub['last_payment_attempt'] ? date( 'Y-m-d H:i', strtotime( $sub['last_payment_attempt'] ) ) : 'N/A' ); ?></td>
                                        <td>
                                            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline;">
                                                <input type="hidden" name="action" value="nehtw_send_dunning_email">
                                                <input type="hidden" name="invoice_id" value="<?php echo esc_attr( $sub['invoice_id'] ); ?>">
                                                <input type="hidden" name="level" value="<?php echo esc_attr( $sub['dunning_level'] + 1 ); ?>">
                                                <?php wp_nonce_field( 'nehtw_send_dunning_' . $sub['invoice_id'] ); ?>
                                                <button type="submit" class="button button-small">Send Next Level</button>
                                            </form>
                                            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline; margin-left: 5px;">
                                                <input type="hidden" name="action" value="nehtw_cancel_dunning_sequence">
                                                <input type="hidden" name="invoice_id" value="<?php echo esc_attr( $sub['invoice_id'] ); ?>">
                                                <?php wp_nonce_field( 'nehtw_cancel_dunning_' . $sub['invoice_id'] ); ?>
                                                <button type="submit" class="button button-small" onclick="return confirm('Cancel dunning sequence for this subscription?');">Cancel</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else : ?>
                        <p><?php _e( 'No active dunning subscriptions.', 'nehtw-gateway' ); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Dunning Email History -->
                <div class="nehtw-glass-card">
                    <h2><?php _e( 'Dunning Email History', 'nehtw-gateway' ); ?></h2>
                    
                    <!-- Filters -->
                    <div style="margin: 15px 0;">
                        <form method="get" style="display: flex; gap: 10px; align-items: center;">
                            <input type="hidden" name="page" value="nehtw-dunning-management">
                            <label>
                                <?php _e( 'Dunning Level:', 'nehtw-gateway' ); ?>
                                <select name="level_filter">
                                    <option value="0"><?php _e( 'All Levels', 'nehtw-gateway' ); ?></option>
                                    <option value="1" <?php selected( $level_filter, 1 ); ?>>Level 1 - Immediate</option>
                                    <option value="2" <?php selected( $level_filter, 2 ); ?>>Level 2 - 3 Days</option>
                                    <option value="3" <?php selected( $level_filter, 3 ); ?>>Level 3 - 7 Days</option>
                                    <option value="4" <?php selected( $level_filter, 4 ); ?>>Level 4 - Cancelled</option>
                                </select>
                            </label>
                            <button type="submit" class="button"><?php _e( 'Filter', 'nehtw-gateway' ); ?></button>
                            <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management' ); ?>" class="button"><?php _e( 'Reset', 'nehtw-gateway' ); ?></a>
                        </form>
                    </div>
                    
                    <?php if ( ! empty( $dunning_emails ) ) : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e( 'Sent Date', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'User', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Invoice', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Amount', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Dunning Level', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Email Type', 'nehtw-gateway' ); ?></th>
                                    <th><?php _e( 'Status', 'nehtw-gateway' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $dunning_emails as $email ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( date( 'Y-m-d H:i:s', strtotime( $email['sent_at'] ) ) ); ?></td>
                                        <td>
                                            <strong><?php echo esc_html( $email['user_name'] ); ?></strong><br>
                                            <small><?php echo esc_html( $email['user_email'] ); ?></small>
                                        </td>
                                        <td><?php echo esc_html( $email['invoice_number'] ?? 'N/A' ); ?></td>
                                        <td>$<?php echo esc_html( number_format( $email['total_amount'] ?? 0, 2 ) ); ?></td>
                                        <td>
                                            <span class="nehtw-badge" style="background: rgba(255, 63, 111, 0.2); color: #ff3f6f; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                                                Level <?php echo esc_html( $email['dunning_level'] ); ?>
                                            </span>
                                        </td>
                                        <td><?php echo esc_html( $email['email_type'] ); ?></td>
                                        <td>
                                            <?php if ( $email['converted_at'] ) : ?>
                                                <span style="color: #53ff9d;">✓ Converted</span>
                                            <?php elseif ( $email['clicked_at'] ) : ?>
                                                <span style="color: #1af2ff;">Clicked</span>
                                            <?php elseif ( $email['opened_at'] ) : ?>
                                                <span style="color: #ffbb33;">Opened</span>
                                            <?php else : ?>
                                                <span style="color: #646970;">Sent</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <!-- Pagination -->
                        <?php
                        $total_pages = ceil( $total_count / $per_page );
                        if ( $total_pages > 1 ) {
                            echo '<div class="tablenav">';
                            echo paginate_links( [
                                'base' => add_query_arg( 'paged', '%#%' ),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $paged,
                            ] );
                            echo '</div>';
                        }
                        ?>
                    <?php else : ?>
                        <p><?php _e( 'No dunning emails found.', 'nehtw-gateway' ); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Manual Actions -->
                <div class="nehtw-glass-card">
                    <h2><?php _e( 'Manual Actions', 'nehtw-gateway' ); ?></h2>
                    <p><?php _e( 'Manually trigger dunning email processing or send test emails.', 'nehtw-gateway' ); ?></p>
                    <div style="margin-top: 15px;">
                        <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline;">
                            <input type="hidden" name="action" value="nehtw_process_dunning_queue">
                            <?php wp_nonce_field( 'nehtw_process_dunning' ); ?>
                            <button type="submit" class="button button-primary"><?php _e( 'Process Dunning Queue Now', 'nehtw-gateway' ); ?></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .nehtw-dunning-admin {
            max-width: 1400px;
        }
        .nehtw-glass-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin: 20px 0;
        }
        .nehtw-glass-card h2 {
            color: #23282d;
            font-size: 18px;
            margin-top: 0;
        }
        .nehtw-dunning-admin table {
            background: #fff;
        }
        .nehtw-dunning-admin table th {
            background: #f6f7f7;
            color: #23282d;
            font-weight: 600;
        }
        .nehtw-dunning-admin table td {
            color: #50575e;
        }
        .nehtw-stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        .nehtw-stat-card div:first-child {
            color: #23282d;
        }
        .nehtw-stat-card div:last-child {
            color: #646970;
        }
        .nehtw-badge {
            background: #f0f0f1;
            color: #50575e;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        </style>
        <?php
    }
    
    /**
     * Handle send dunning email action
     */
    public function handle_send_dunning_email() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'nehtw-gateway' ) );
        }
        
        $invoice_id = isset( $_POST['invoice_id'] ) ? intval( $_POST['invoice_id'] ) : 0;
        $level = isset( $_POST['level'] ) ? intval( $_POST['level'] ) : 1;
        
        check_admin_referer( 'nehtw_send_dunning_' . $invoice_id );
        
        if ( ! $invoice_id ) {
            wp_die( __( 'Invalid invoice ID', 'nehtw-gateway' ) );
        }
        
        $dunning_manager = new Nehtw_Dunning_Manager();
        $result = $dunning_manager->send_dunning_email( $invoice_id, $level );
        
        if ( $result ) {
            wp_redirect( add_query_arg( [
                'page' => 'nehtw-dunning-management',
                'dunning_sent' => '1',
            ], admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( [
                'page' => 'nehtw-dunning-management',
                'dunning_error' => '1',
            ], admin_url( 'admin.php' ) ) );
        }
        exit;
    }
    
    /**
     * Handle cancel dunning sequence action
     */
    public function handle_cancel_dunning() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'nehtw-gateway' ) );
        }
        
        $invoice_id = isset( $_POST['invoice_id'] ) ? intval( $_POST['invoice_id'] ) : 0;
        
        check_admin_referer( 'nehtw_cancel_dunning_' . $invoice_id );
        
        if ( ! $invoice_id ) {
            wp_die( __( 'Invalid invoice ID', 'nehtw-gateway' ) );
        }
        
        $dunning_manager = new Nehtw_Dunning_Manager();
        $result = $dunning_manager->cancel_dunning_sequence( $invoice_id );
        
        wp_redirect( add_query_arg( [
            'page' => 'nehtw-dunning-management',
            'dunning_cancelled' => $result ? '1' : '0',
        ], admin_url( 'admin.php' ) ) );
        exit;
    }
    
    /**
     * Handle process dunning queue action
     */
    public function handle_process_dunning_queue() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'nehtw-gateway' ) );
        }
        
        check_admin_referer( 'nehtw_process_dunning' );
        
        $dunning_manager = new Nehtw_Dunning_Manager();
        $result = $dunning_manager->process_dunning_queue();
        
        wp_redirect( add_query_arg( [
            'page' => 'nehtw-dunning-management',
            'queue_processed' => '1',
            'sent' => isset( $result['sent'] ) ? $result['sent'] : 0,
            'failed' => isset( $result['failed'] ) ? $result['failed'] : 0,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }
    
    /**
     * Render templates tab
     */
    private function render_templates_tab() {
        // Handle template save
        if ( isset( $_POST['save_templates'] ) && check_admin_referer( 'nehtw_save_templates' ) ) {
            $templates = [];
            for ( $level = 1; $level <= 4; $level++ ) {
                $templates[ $level ] = [
                    'subject' => isset( $_POST[ "template_{$level}_subject" ] ) ? sanitize_text_field( $_POST[ "template_{$level}_subject" ] ) : '',
                    'body' => isset( $_POST[ "template_{$level}_body" ] ) ? wp_kses_post( $_POST[ "template_{$level}_body" ] ) : '',
                ];
            }
            update_option( 'nehtw_dunning_email_templates', $templates );
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Templates saved successfully.', 'nehtw-gateway' ) . '</p></div>';
        }
        
        // Get saved templates or use defaults
        $saved_templates = get_option( 'nehtw_dunning_email_templates', [] );
        $dunning_manager = new Nehtw_Dunning_Manager();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass( $dunning_manager );
        $method = $reflection->getMethod( 'get_email_templates' );
        $method->setAccessible( true );
        $default_templates = $method->invoke( $dunning_manager );
        
        $templates = [];
        $template_keys = [ 1 => 'payment-failed-immediate', 2 => 'payment-failed-reminder', 3 => 'payment-failed-final-warning', 4 => 'subscription-cancelled' ];
        
        for ( $level = 1; $level <= 4; $level++ ) {
            $template_key = $template_keys[ $level ];
            if ( isset( $saved_templates[ $level ] ) ) {
                $templates[ $level ] = $saved_templates[ $level ];
            } elseif ( isset( $default_templates[ $template_key ] ) ) {
                $templates[ $level ] = $default_templates[ $template_key ];
            } else {
                $templates[ $level ] = [ 'subject' => '', 'body' => '' ];
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Email Templates', 'nehtw-gateway' ); ?></h1>
            
            <?php
            // Show test email notices
            if ( isset( $_GET['test_sent'] ) && $_GET['test_sent'] == '1' ) {
                $test_email = isset( $_GET['test_email'] ) ? urldecode( $_GET['test_email'] ) : '';
                $test_level = isset( $_GET['test_level'] ) ? intval( $_GET['test_level'] ) : 0;
                $level_names = [ 1 => 'Level 1', 2 => 'Level 2', 3 => 'Level 3', 4 => 'Level 4' ];
                echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( __( 'Test email sent successfully to %s for %s.', 'nehtw-gateway' ), esc_html( $test_email ), esc_html( $level_names[ $test_level ] ?? 'Unknown' ) ) . '</p></div>';
            }
            if ( isset( $_GET['test_error'] ) && $_GET['test_error'] == '1' ) {
                $error_msg = isset( $_GET['error_msg'] ) ? urldecode( $_GET['error_msg'] ) : __( 'Failed to send test email.', 'nehtw-gateway' );
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error_msg ) . '</p></div>';
            }
            ?>
            
            <!-- Tabs -->
            <nav class="nav-tab-wrapper" style="margin: 20px 0;">
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=overview' ); ?>" class="nav-tab"><?php _e( 'Overview', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=templates' ); ?>" class="nav-tab nav-tab-active"><?php _e( 'Email Templates', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=schedule' ); ?>" class="nav-tab"><?php _e( 'Schedule', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=automation' ); ?>" class="nav-tab"><?php _e( 'Automation', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=email-service' ); ?>" class="nav-tab"><?php _e( 'Email Service', 'nehtw-gateway' ); ?></a>
            </nav>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'nehtw_save_templates' ); ?>
                
                <div class="nehtw-glass-card">
                    <p><strong><?php _e( 'Available Placeholders:', 'nehtw-gateway' ); ?></strong></p>
                    <p><code>{user_name}</code>, <code>{user_first_name}</code>, <code>{invoice_number}</code>, <code>{amount}</code>, <code>{plan_name}</code>, <code>{due_date}</code>, <code>{update_payment_url}</code>, <code>{dashboard_url}</code>, <code>{support_email}</code>, <code>{site_name}</code></p>
                </div>
                
                <?php for ( $level = 1; $level <= 4; $level++ ) : 
                    $level_names = [ 1 => 'Level 1 - Immediate Payment Failed', 2 => 'Level 2 - 3 Days Reminder', 3 => 'Level 3 - Final Warning', 4 => 'Level 4 - Subscription Cancelled' ];
                ?>
                    <div class="nehtw-glass-card">
                        <h2><?php echo esc_html( $level_names[ $level ] ); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="template_<?php echo $level; ?>_subject"><?php _e( 'Email Subject', 'nehtw-gateway' ); ?></label></th>
                                <td>
                                    <input type="text" id="template_<?php echo $level; ?>_subject" name="template_<?php echo $level; ?>_subject" value="<?php echo esc_attr( $templates[ $level ]['subject'] ); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th><label for="template_<?php echo $level; ?>_body"><?php _e( 'Email Body (HTML)', 'nehtw-gateway' ); ?></label></th>
                                <td>
                                    <?php
                                    wp_editor( $templates[ $level ]['body'], "template_{$level}_body", [
                                        'textarea_name' => "template_{$level}_body",
                                        'textarea_rows' => 15,
                                        'media_buttons' => false,
                                    ] );
                                    ?>
                                </td>
                            </tr>
                        </table>
                        
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                            <h3 style="margin-top: 0;"><?php _e( 'Test Email', 'nehtw-gateway' ); ?></h3>
                            <p class="description"><?php _e( 'Send a test email to verify the template and SMTP configuration.', 'nehtw-gateway' ); ?></p>
                            <form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" style="display: inline-block; margin-top: 10px;">
                                <input type="hidden" name="action" value="nehtw_test_dunning_email">
                                <input type="hidden" name="template_level" value="<?php echo $level; ?>">
                                <?php wp_nonce_field( 'nehtw_test_dunning_email_' . $level ); ?>
                                <label for="test_email_<?php echo $level; ?>" style="display: inline-block; margin-right: 10px;">
                                    <?php _e( 'Test Email Address:', 'nehtw-gateway' ); ?>
                                    <input type="email" id="test_email_<?php echo $level; ?>" name="test_email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" class="regular-text" style="margin-left: 5px;" />
                                </label>
                                <button type="submit" class="button button-secondary"><?php _e( 'Send Test Email', 'nehtw-gateway' ); ?></button>
                            </form>
                        </div>
                    </div>
                <?php endfor; ?>
                
                <p class="submit">
                    <button type="submit" name="save_templates" class="button button-primary"><?php _e( 'Save Templates', 'nehtw-gateway' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render schedule tab
     */
    private function render_schedule_tab() {
        // Handle schedule save
        if ( isset( $_POST['save_schedule'] ) && check_admin_referer( 'nehtw_save_schedule' ) ) {
            $schedule = [];
            for ( $level = 1; $level <= 4; $level++ ) {
                $schedule[ $level ] = [
                    'delay' => isset( $_POST[ "schedule_{$level}_delay" ] ) ? sanitize_text_field( $_POST[ "schedule_{$level}_delay" ] ) : '0 hours',
                    'enabled' => isset( $_POST[ "schedule_{$level}_enabled" ] ) ? 1 : 0,
                ];
            }
            update_option( 'nehtw_dunning_schedule', $schedule );
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Schedule saved successfully.', 'nehtw-gateway' ) . '</p></div>';
        }
        
        $saved_schedule = get_option( 'nehtw_dunning_schedule', [] );
        $default_schedule = [
            1 => [ 'delay' => '0 hours', 'enabled' => 1 ],
            2 => [ 'delay' => '+3 days', 'enabled' => 1 ],
            3 => [ 'delay' => '+7 days', 'enabled' => 1 ],
            4 => [ 'delay' => '+10 days', 'enabled' => 1 ],
        ];
        
        $schedule = [];
        for ( $level = 1; $level <= 4; $level++ ) {
            $schedule[ $level ] = isset( $saved_schedule[ $level ] ) ? $saved_schedule[ $level ] : $default_schedule[ $level ];
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Dunning Schedule Configuration', 'nehtw-gateway' ); ?></h1>
            
            <!-- Tabs -->
            <nav class="nav-tab-wrapper" style="margin: 20px 0;">
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=overview' ); ?>" class="nav-tab"><?php _e( 'Overview', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=templates' ); ?>" class="nav-tab"><?php _e( 'Email Templates', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=schedule' ); ?>" class="nav-tab nav-tab-active"><?php _e( 'Schedule', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=automation' ); ?>" class="nav-tab"><?php _e( 'Automation', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=email-service' ); ?>" class="nav-tab"><?php _e( 'Email Service', 'nehtw-gateway' ); ?></a>
            </nav>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'nehtw_save_schedule' ); ?>
                
                <div class="nehtw-glass-card">
                    <p><strong><?php _e( 'Schedule Format:', 'nehtw-gateway' ); ?></strong> Use PHP strtotime format (e.g., "0 hours", "+3 days", "+7 days", "+10 days")</p>
                </div>
                
                <?php for ( $level = 1; $level <= 4; $level++ ) : 
                    $level_names = [ 1 => 'Level 1 - Immediate Payment Failed', 2 => 'Level 2 - 3 Days Reminder', 3 => 'Level 3 - Final Warning', 4 => 'Level 4 - Subscription Cancelled' ];
                ?>
                    <div class="nehtw-glass-card">
                        <h2><?php echo esc_html( $level_names[ $level ] ); ?></h2>
                        
                        <table class="form-table">
                            <tr>
                                <th><label for="schedule_<?php echo $level; ?>_enabled"><?php _e( 'Enabled', 'nehtw-gateway' ); ?></label></th>
                                <td>
                                    <input type="checkbox" id="schedule_<?php echo $level; ?>_enabled" name="schedule_<?php echo $level; ?>_enabled" value="1" <?php checked( $schedule[ $level ]['enabled'], 1 ); ?> />
                                </td>
                            </tr>
                            <tr>
                                <th><label for="schedule_<?php echo $level; ?>_delay"><?php _e( 'Delay After Payment Failure', 'nehtw-gateway' ); ?></label></th>
                                <td>
                                    <input type="text" id="schedule_<?php echo $level; ?>_delay" name="schedule_<?php echo $level; ?>_delay" value="<?php echo esc_attr( $schedule[ $level ]['delay'] ); ?>" class="regular-text" placeholder="e.g., +3 days" />
                                    <p class="description"><?php _e( 'When to send this email after payment failure.', 'nehtw-gateway' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                <?php endfor; ?>
                
                <p class="submit">
                    <button type="submit" name="save_schedule" class="button button-primary"><?php _e( 'Save Schedule', 'nehtw-gateway' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render automation tab
     */
    private function render_automation_tab() {
        // Handle automation save
        if ( isset( $_POST['save_automation'] ) && check_admin_referer( 'nehtw_save_automation' ) ) {
            $automation = [
                'enabled' => isset( $_POST['automation_enabled'] ) ? 1 : 0,
                'exclude_roles' => isset( $_POST['exclude_roles'] ) ? array_map( 'sanitize_text_field', $_POST['exclude_roles'] ) : [],
                'min_subscription_amount' => isset( $_POST['min_subscription_amount'] ) ? floatval( $_POST['min_subscription_amount'] ) : 0,
                'max_emails_per_day' => isset( $_POST['max_emails_per_day'] ) ? intval( $_POST['max_emails_per_day'] ) : 10,
            ];
            update_option( 'nehtw_dunning_automation', $automation );
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Automation settings saved successfully.', 'nehtw-gateway' ) . '</p></div>';
        }
        
        $automation = get_option( 'nehtw_dunning_automation', [
            'enabled' => 1,
            'exclude_roles' => [],
            'min_subscription_amount' => 0,
            'max_emails_per_day' => 10,
        ] );
        
        global $wp_roles;
        $roles = $wp_roles->get_names();
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Automation Configuration', 'nehtw-gateway' ); ?></h1>
            
            <!-- Tabs -->
            <nav class="nav-tab-wrapper" style="margin: 20px 0;">
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=overview' ); ?>" class="nav-tab"><?php _e( 'Overview', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=templates' ); ?>" class="nav-tab"><?php _e( 'Email Templates', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=schedule' ); ?>" class="nav-tab"><?php _e( 'Schedule', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=automation' ); ?>" class="nav-tab nav-tab-active"><?php _e( 'Automation', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=email-service' ); ?>" class="nav-tab"><?php _e( 'Email Service', 'nehtw-gateway' ); ?></a>
            </nav>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'nehtw_save_automation' ); ?>
                
                <div class="nehtw-glass-card">
                    <h2><?php _e( 'General Settings', 'nehtw-gateway' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="automation_enabled"><?php _e( 'Enable Automated Dunning', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <input type="checkbox" id="automation_enabled" name="automation_enabled" value="1" <?php checked( $automation['enabled'], 1 ); ?> />
                                <p class="description"><?php _e( 'Automatically send dunning emails based on payment failures.', 'nehtw-gateway' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="max_emails_per_day"><?php _e( 'Max Emails Per Day', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <input type="number" id="max_emails_per_day" name="max_emails_per_day" value="<?php echo esc_attr( $automation['max_emails_per_day'] ); ?>" class="small-text" min="1" />
                                <p class="description"><?php _e( 'Maximum number of dunning emails to send per day (prevents spam).', 'nehtw-gateway' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="nehtw-glass-card">
                    <h2><?php _e( 'Client Selection', 'nehtw-gateway' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label><?php _e( 'Exclude User Roles', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <?php foreach ( $roles as $role_key => $role_name ) : ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" name="exclude_roles[]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $automation['exclude_roles'] ), true ); ?> />
                                        <?php echo esc_html( $role_name ); ?>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description"><?php _e( 'Users with these roles will not receive automated dunning emails.', 'nehtw-gateway' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="min_subscription_amount"><?php _e( 'Minimum Subscription Amount', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <input type="number" id="min_subscription_amount" name="min_subscription_amount" value="<?php echo esc_attr( $automation['min_subscription_amount'] ); ?>" class="small-text" step="0.01" min="0" />
                                <p class="description"><?php _e( 'Only send dunning emails for subscriptions above this amount (0 = all subscriptions).', 'nehtw-gateway' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="save_automation" class="button button-primary"><?php _e( 'Save Automation Settings', 'nehtw-gateway' ); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render email service tab
     */
    private function render_email_service_tab() {
        // Handle email service save
        if ( isset( $_POST['save_email_service'] ) && check_admin_referer( 'nehtw_save_email_service' ) ) {
            $email_service = [
                'provider' => isset( $_POST['email_provider'] ) ? sanitize_text_field( $_POST['email_provider'] ) : 'wp_mail',
                'smtp_host' => isset( $_POST['smtp_host'] ) ? sanitize_text_field( $_POST['smtp_host'] ) : '',
                'smtp_port' => isset( $_POST['smtp_port'] ) ? intval( $_POST['smtp_port'] ) : 587,
                'smtp_username' => isset( $_POST['smtp_username'] ) ? sanitize_text_field( $_POST['smtp_username'] ) : '',
                'smtp_password' => isset( $_POST['smtp_password'] ) ? sanitize_text_field( $_POST['smtp_password'] ) : '',
                'smtp_encryption' => isset( $_POST['smtp_encryption'] ) ? sanitize_text_field( $_POST['smtp_encryption'] ) : 'tls',
                'from_email' => isset( $_POST['from_email'] ) ? sanitize_email( $_POST['from_email'] ) : get_option( 'admin_email' ),
                'from_name' => isset( $_POST['from_name'] ) ? sanitize_text_field( $_POST['from_name'] ) : get_bloginfo( 'name' ),
            ];
            update_option( 'nehtw_email_service', $email_service );
            echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Email service settings saved successfully.', 'nehtw-gateway' ) . '</p></div>';
        }
        
        $email_service = get_option( 'nehtw_email_service', [
            'provider' => 'wp_mail',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'from_email' => get_option( 'admin_email' ),
            'from_name' => get_bloginfo( 'name' ),
        ] );
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Email Service Configuration', 'nehtw-gateway' ); ?></h1>
            
            <!-- Tabs -->
            <nav class="nav-tab-wrapper" style="margin: 20px 0;">
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=overview' ); ?>" class="nav-tab"><?php _e( 'Overview', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=templates' ); ?>" class="nav-tab"><?php _e( 'Email Templates', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=schedule' ); ?>" class="nav-tab"><?php _e( 'Schedule', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=automation' ); ?>" class="nav-tab"><?php _e( 'Automation', 'nehtw-gateway' ); ?></a>
                <a href="<?php echo admin_url( 'admin.php?page=nehtw-dunning-management&tab=email-service' ); ?>" class="nav-tab nav-tab-active"><?php _e( 'Email Service', 'nehtw-gateway' ); ?></a>
            </nav>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'nehtw_save_email_service' ); ?>
                
                <div class="nehtw-glass-card">
                    <h2><?php _e( 'Email Provider', 'nehtw-gateway' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="email_provider"><?php _e( 'Email Provider', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <select id="email_provider" name="email_provider" onchange="toggleSMTPFields()">
                                    <option value="wp_mail" <?php selected( $email_service['provider'], 'wp_mail' ); ?>><?php _e( 'WordPress Default (wp_mail)', 'nehtw-gateway' ); ?></option>
                                    <option value="smtp" <?php selected( $email_service['provider'], 'smtp' ); ?>><?php _e( 'SMTP', 'nehtw-gateway' ); ?></option>
                                </select>
                                <p class="description"><?php _e( 'For production, use SMTP or a service like SendGrid/Mailgun for better deliverability.', 'nehtw-gateway' ); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="nehtw-glass-card" id="smtp-settings" style="<?php echo $email_service['provider'] !== 'smtp' ? 'display: none;' : ''; ?>">
                    <h2><?php _e( 'SMTP Settings', 'nehtw-gateway' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="smtp_host"><?php _e( 'SMTP Host', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <input type="text" id="smtp_host" name="smtp_host" value="<?php echo esc_attr( $email_service['smtp_host'] ); ?>" class="regular-text" placeholder="smtp.gmail.com" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="smtp_port"><?php _e( 'SMTP Port', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <input type="number" id="smtp_port" name="smtp_port" value="<?php echo esc_attr( $email_service['smtp_port'] ); ?>" class="small-text" />
                                <p class="description"><?php _e( 'Common ports: 587 (TLS), 465 (SSL), 25 (unencrypted)', 'nehtw-gateway' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="smtp_encryption"><?php _e( 'Encryption', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <select id="smtp_encryption" name="smtp_encryption">
                                    <option value="tls" <?php selected( $email_service['smtp_encryption'], 'tls' ); ?>>TLS</option>
                                    <option value="ssl" <?php selected( $email_service['smtp_encryption'], 'ssl' ); ?>>SSL</option>
                                    <option value="none" <?php selected( $email_service['smtp_encryption'], 'none' ); ?>><?php _e( 'None', 'nehtw-gateway' ); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="smtp_username"><?php _e( 'SMTP Username', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <input type="text" id="smtp_username" name="smtp_username" value="<?php echo esc_attr( $email_service['smtp_username'] ); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="smtp_password"><?php _e( 'SMTP Password', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <input type="password" id="smtp_password" name="smtp_password" value="<?php echo esc_attr( $email_service['smtp_password'] ); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="nehtw-glass-card">
                    <h2><?php _e( 'From Settings', 'nehtw-gateway' ); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="from_email"><?php _e( 'From Email', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <input type="email" id="from_email" name="from_email" value="<?php echo esc_attr( $email_service['from_email'] ); ?>" class="regular-text" />
                            </td>
                        </tr>
                        <tr>
                            <th><label for="from_name"><?php _e( 'From Name', 'nehtw-gateway' ); ?></label></th>
                            <td>
                                <input type="text" id="from_name" name="from_name" value="<?php echo esc_attr( $email_service['from_name'] ); ?>" class="regular-text" />
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <button type="submit" name="save_email_service" class="button button-primary"><?php _e( 'Save Email Service Settings', 'nehtw-gateway' ); ?></button>
                </p>
            </form>
            
            <script>
            function toggleSMTPFields() {
                var provider = document.getElementById('email_provider').value;
                document.getElementById('smtp-settings').style.display = provider === 'smtp' ? 'block' : 'none';
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * Handle test email action
     */
    public function handle_test_dunning_email() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'nehtw-gateway' ) );
        }
        
        $level = isset( $_POST['template_level'] ) ? intval( $_POST['template_level'] ) : 0;
        $test_email = isset( $_POST['test_email'] ) ? sanitize_email( $_POST['test_email'] ) : get_option( 'admin_email' );
        
        check_admin_referer( 'nehtw_test_dunning_email_' . $level );
        
        if ( ! $test_email || ! is_email( $test_email ) ) {
            wp_redirect( add_query_arg( [
                'page' => 'nehtw-dunning-management',
                'tab' => 'templates',
                'test_error' => '1',
                'error_msg' => urlencode( __( 'Invalid email address.', 'nehtw-gateway' ) ),
            ], admin_url( 'admin.php' ) ) );
            exit;
        }
        
        if ( $level < 1 || $level > 4 ) {
            wp_redirect( add_query_arg( [
                'page' => 'nehtw-dunning-management',
                'tab' => 'templates',
                'test_error' => '1',
                'error_msg' => urlencode( __( 'Invalid template level.', 'nehtw-gateway' ) ),
            ], admin_url( 'admin.php' ) ) );
            exit;
        }
        
        // Get saved templates
        $saved_templates = get_option( 'nehtw_dunning_email_templates', [] );
        $dunning_manager = new Nehtw_Dunning_Manager();
        
        // Use reflection to access private method
        $reflection = new ReflectionClass( $dunning_manager );
        $method = $reflection->getMethod( 'get_email_templates' );
        $method->setAccessible( true );
        $default_templates = $method->invoke( $dunning_manager );
        
        $template_keys = [ 1 => 'payment-failed-immediate', 2 => 'payment-failed-reminder', 3 => 'payment-failed-final-warning', 4 => 'subscription-cancelled' ];
        $template_key = $template_keys[ $level ];
        
        // Get template content
        if ( isset( $saved_templates[ $level ] ) ) {
            $template = $saved_templates[ $level ];
        } elseif ( isset( $default_templates[ $template_key ] ) ) {
            $template = $default_templates[ $template_key ];
        } else {
            wp_redirect( add_query_arg( [
                'page' => 'nehtw-dunning-management',
                'tab' => 'templates',
                'test_error' => '1',
                'error_msg' => urlencode( __( 'Template not found.', 'nehtw-gateway' ) ),
            ], admin_url( 'admin.php' ) ) );
            exit;
        }
        
        // Create test data
        $current_user = wp_get_current_user();
        $test_data = [
            'user' => (object) [
                'display_name' => $current_user->display_name ?: 'Test User',
                'first_name' => $current_user->first_name ?: 'Test',
                'user_email' => $test_email,
            ],
            'invoice' => [
                'invoice_number' => 'TEST-' . date( 'Ymd' ) . '-' . rand( 1000, 9999 ),
                'total_amount' => 29.99,
                'due_date' => date( 'Y-m-d', strtotime( '+7 days' ) ),
            ],
            'subscription' => [
                'id' => 0,
                'plan_key' => 'test_plan',
            ],
            'level' => $level,
        ];
        
        // Replace placeholders
        $placeholders = [
            '{user_name}' => $test_data['user']->display_name,
            '{user_first_name}' => $test_data['user']->first_name,
            '{invoice_number}' => $test_data['invoice']['invoice_number'],
            '{amount}' => '$' . number_format( $test_data['invoice']['total_amount'], 2 ),
            '{plan_name}' => 'Test Plan',
            '{due_date}' => date( 'F j, Y', strtotime( $test_data['invoice']['due_date'] ) ),
            '{update_payment_url}' => home_url( '/my-account/payment-methods/' ),
            '{dashboard_url}' => get_permalink( get_option( 'woocommerce_myaccount_page_id' ) ) ?: home_url(),
            '{support_email}' => get_option( 'admin_email' ),
            '{site_name}' => get_bloginfo( 'name' ),
        ];
        
        $subject = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template['subject'] );
        $body = str_replace( array_keys( $placeholders ), array_values( $placeholders ), $template['body'] );
        
        // Get email service settings
        $email_service = get_option( 'nehtw_email_service', [
            'provider' => 'wp_mail',
            'from_email' => get_option( 'admin_email' ),
            'from_name' => get_bloginfo( 'name' ),
        ] );
        
        // Prepare headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $email_service['from_name'] . ' <' . $email_service['from_email'] . '>',
        ];
        
        // Send email using configured service
        if ( $email_service['provider'] === 'smtp' && ! empty( $email_service['smtp_host'] ) ) {
            // Use PHPMailer for SMTP
            $sent = $this->send_smtp_email( $test_email, $subject, $body, $headers, $email_service );
        } else {
            // Use WordPress default
            $sent = wp_mail( $test_email, $subject, $body, $headers );
        }
        
        if ( $sent ) {
            wp_redirect( add_query_arg( [
                'page' => 'nehtw-dunning-management',
                'tab' => 'templates',
                'test_sent' => '1',
                'test_email' => urlencode( $test_email ),
                'test_level' => $level,
            ], admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( [
                'page' => 'nehtw-dunning-management',
                'tab' => 'templates',
                'test_error' => '1',
                'error_msg' => urlencode( __( 'Failed to send test email. Check your email service configuration.', 'nehtw-gateway' ) ),
            ], admin_url( 'admin.php' ) ) );
        }
        exit;
    }
    
    /**
     * Send email via SMTP using PHPMailer
     */
    private function send_smtp_email( $to, $subject, $body, $headers, $email_service ) {
        // Load PHPMailer if not already loaded
        if ( ! class_exists( 'PHPMailer\PHPMailer\PHPMailer', false ) ) {
            require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
            require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
            require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
        }
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer( true );
            
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = $email_service['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $email_service['smtp_username'];
            $mail->Password = $email_service['smtp_password'];
            $mail->Port = intval( $email_service['smtp_port'] );
            
            // Encryption
            if ( $email_service['smtp_encryption'] === 'ssl' ) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ( $email_service['smtp_encryption'] === 'tls' ) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
            }
            
            // Enable debug if WP_DEBUG is on (optional)
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = function( $str, $level ) {
                    error_log( "PHPMailer: $str" );
                };
            }
            
            // From
            $mail->setFrom( $email_service['from_email'], $email_service['from_name'] );
            
            // To
            $mail->addAddress( $to );
            
            // Content
            $mail->isHTML( true );
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            
            // Send
            $result = $mail->send();
            
            if ( ! $result ) {
                error_log( 'Nehtw Dunning Test Email Error: ' . $mail->ErrorInfo );
            }
            
            return $result;
        } catch ( \Exception $e ) {
            error_log( 'Nehtw Dunning Test Email Exception: ' . $e->getMessage() );
            return false;
        }
    }
}

// Initialize
new Nehtw_Dunning_Admin();

