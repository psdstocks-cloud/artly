<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Nehtw_Admin_Subscriptions' ) ) {
    if ( ! class_exists( 'WP_List_Table' ) ) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
    }

    class Nehtw_Admin_Subscriptions {

        public static function init() {
            if ( ! is_admin() ) {
                return;
            }

            add_action( 'admin_menu', array( __CLASS__, 'register_menus' ), 35 );
        }

        public static function register_menus() {
            add_submenu_page(
                'nehtw-gateway',
                __( 'Nehtw Subscriptions', 'nehtw-gateway' ),
                __( 'Subscriptions', 'nehtw-gateway' ),
                'manage_nehtw',
                'nehtw-subscriptions-admin',
                array( __CLASS__, 'render_subscriptions_page' )
            );

            add_submenu_page(
                'nehtw-gateway',
                __( 'Nehtw Invoices', 'nehtw-gateway' ),
                __( 'Invoices', 'nehtw-gateway' ),
                'manage_nehtw',
                'nehtw-invoices-admin',
                array( __CLASS__, 'render_invoices_page' )
            );
        }

        protected static function can_manage() {
            return current_user_can( 'manage_nehtw' ) || current_user_can( 'manage_options' );
        }

        protected static function ensure_permissions() {
            if ( ! self::can_manage() ) {
                wp_die( __( 'You do not have permission to access this page.', 'nehtw-gateway' ) );
            }
        }

        public static function render_subscriptions_page() {
            self::ensure_permissions();
            self::handle_actions();

            if ( isset( $_GET['view'] ) && 'details' === $_GET['view'] ) {
                $subscription_id = isset( $_GET['subscription_id'] ) ? absint( wp_unslash( $_GET['subscription_id'] ) ) : 0;
                self::render_subscription_detail( $subscription_id );
                return;
            }

            $list_table = new Nehtw_Subscriptions_List_Table();
            $list_table->prepare_items();

            $notice = isset( $_GET['nehtw_notice'] ) ? sanitize_key( wp_unslash( $_GET['nehtw_notice'] ) ) : '';
            $message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Nehtw Subscriptions', 'nehtw-gateway' ); ?></h1>
                <?php self::render_notice( $notice, $message ); ?>
                <form method="get" class="nehtw-filters" style="margin-bottom:15px;">
                    <input type="hidden" name="page" value="nehtw-subscriptions-admin" />
                    <label for="nehtw-status-filter" class="screen-reader-text"><?php esc_html_e( 'Status filter', 'nehtw-gateway' ); ?></label>
                    <select name="status" id="nehtw-status-filter">
                        <option value=""><?php esc_html_e( 'All statuses', 'nehtw-gateway' ); ?></option>
                        <?php foreach ( array( 'active', 'paused', 'overdue', 'cancelled' ) as $status ) : ?>
                            <option value="<?php echo esc_attr( $status ); ?>" <?php selected( isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '', $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="button"><?php esc_html_e( 'Filter', 'nehtw-gateway' ); ?></button>
                </form>
                <form method="post">
                    <?php $list_table->display(); ?>
                </form>
            </div>
            <?php
        }

        public static function render_invoices_page() {
            self::ensure_permissions();
            $list_table = new Nehtw_Invoices_List_Table();
            $list_table->prepare_items();
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Nehtw Invoices', 'nehtw-gateway' ); ?></h1>
                <form method="get" class="nehtw-filters" style="margin-bottom:15px;">
                    <input type="hidden" name="page" value="nehtw-invoices-admin" />
                    <label for="nehtw-invoice-status" class="screen-reader-text"><?php esc_html_e( 'Invoice status', 'nehtw-gateway' ); ?></label>
                    <select id="nehtw-invoice-status" name="status">
                        <option value=""><?php esc_html_e( 'All statuses', 'nehtw-gateway' ); ?></option>
                        <?php foreach ( array( 'paid', 'pending', 'failed', 'void', 'refunded' ) as $status ) : ?>
                            <option value="<?php echo esc_attr( $status ); ?>" <?php selected( isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '', $status ); ?>><?php echo esc_html( ucfirst( $status ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="nehtw-invoice-subscription" style="margin-left:10px;">
                        <?php esc_html_e( 'Subscription ID', 'nehtw-gateway' ); ?>
                    </label>
                    <input type="number" id="nehtw-invoice-subscription" name="subscription_id" value="<?php echo isset( $_GET['subscription_id'] ) ? esc_attr( absint( $_GET['subscription_id'] ) ) : ''; ?>" style="width:120px;" />
                    <button type="submit" class="button"><?php esc_html_e( 'Filter', 'nehtw-gateway' ); ?></button>
                </form>
                <form method="post">
                    <?php $list_table->display(); ?>
                </form>
            </div>
            <?php
        }

        protected static function render_subscription_detail( $subscription_id ) {
            if ( $subscription_id <= 0 ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid subscription.', 'nehtw-gateway' ) . '</p></div>';
                return;
            }

            $subscription = nehtw_gateway_get_subscription( $subscription_id );
            if ( ! $subscription ) {
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Subscription not found.', 'nehtw-gateway' ) . '</p></div>';
                return;
            }

            $user = get_user_by( 'id', $subscription['user_id'] );
            $meta = isset( $subscription['meta'] ) && is_array( $subscription['meta'] ) ? $subscription['meta'] : array();
            $product_id = Nehtw_Subscription_Product_Helper::get_product_id_from_plan_key( $subscription['plan_key'] );
            $plan_name = ! empty( $meta['plan_name'] ) ? $meta['plan_name'] : $subscription['plan_key'];
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Subscription Details', 'nehtw-gateway' ); ?></h1>
                <p><a href="<?php echo esc_url( menu_page_url( 'nehtw-subscriptions-admin', false ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to list', 'nehtw-gateway' ); ?></a></p>
                <?php self::render_notice( isset( $_GET['nehtw_notice'] ) ? sanitize_key( wp_unslash( $_GET['nehtw_notice'] ) ) : '', isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '' ); ?>
                <table class="widefat striped" style="margin-bottom:20px;">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'Subscription ID', 'nehtw-gateway' ); ?></th>
                            <td>#<?php echo esc_html( $subscription['id'] ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'User', 'nehtw-gateway' ); ?></th>
                            <td>
                                <?php if ( $user ) : ?>
                                    <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->display_name ); ?></a>
                                    <br />
                                    <small><?php echo esc_html( $user->user_email ); ?></small>
                                <?php else : ?>
                                    <em><?php esc_html_e( 'User removed', 'nehtw-gateway' ); ?></em>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Plan', 'nehtw-gateway' ); ?></th>
                            <td>
                                <?php if ( $product_id ) : ?>
                                    <a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>"><?php echo esc_html( $plan_name ); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html( $plan_name ); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Status', 'nehtw-gateway' ); ?></th>
                            <td><span class="nehtw-chip nehtw-chip-<?php echo esc_attr( $subscription['status'] ); ?>"><?php echo esc_html( ucfirst( $subscription['status'] ) ); ?></span></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Points per interval', 'nehtw-gateway' ); ?></th>
                            <td><?php echo esc_html( number_format_i18n( $subscription['points_per_interval'] ) ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Billing interval', 'nehtw-gateway' ); ?></th>
                            <td><?php printf( esc_html__( 'Every %1$d %2$s', 'nehtw-gateway' ), isset( $meta['interval_count'] ) ? (int) $meta['interval_count'] : 1, esc_html( $subscription['interval'] ) ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Last renewal', 'nehtw-gateway' ); ?></th>
                            <td><?php echo esc_html( self::format_date( isset( $meta['last_renewal_at'] ) ? $meta['last_renewal_at'] : '' ) ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Next renewal', 'nehtw-gateway' ); ?></th>
                            <td><?php echo esc_html( self::format_date( $subscription['next_renewal_at'] ) ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Current wallet balance', 'nehtw-gateway' ); ?></th>
                            <td><?php echo esc_html( number_format_i18n( nehtw_gateway_get_balance( $subscription['user_id'] ) ) ); ?></td>
                        </tr>
                    </tbody>
                </table>
                <p>
                    <?php echo self::action_button( $subscription['id'], 'resume', __( 'Resume', 'nehtw-gateway' ), 'button' ); ?>
                    <?php echo self::action_button( $subscription['id'], 'pause', __( 'Pause', 'nehtw-gateway' ), 'button' ); ?>
                    <?php echo self::action_button( $subscription['id'], 'cancel', __( 'Cancel', 'nehtw-gateway' ), 'button button-danger' ); ?>
                </p>
                <?php self::render_related_tables( $subscription_id ); ?>
            </div>
            <?php
        }

        protected static function render_related_tables( $subscription_id ) {
            global $wpdb;
            $invoices = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nehtw_invoices WHERE subscription_id = %d ORDER BY id DESC LIMIT 20", $subscription_id ), ARRAY_A );
            $history  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nehtw_subscription_history WHERE subscription_id = %d ORDER BY id DESC LIMIT 20", $subscription_id ), ARRAY_A );
            $retries  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nehtw_payment_retries WHERE subscription_id = %d ORDER BY id DESC LIMIT 20", $subscription_id ), ARRAY_A );
            $dunning  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nehtw_dunning_emails WHERE subscription_id = %d ORDER BY id DESC LIMIT 20", $subscription_id ), ARRAY_A );
            ?>
            <h2><?php esc_html_e( 'Invoices', 'nehtw-gateway' ); ?></h2>
            <?php self::render_simple_table( $invoices, array( 'id' => __( 'Invoice ID', 'nehtw-gateway' ), 'total_amount' => __( 'Amount', 'nehtw-gateway' ), 'status' => __( 'Status', 'nehtw-gateway' ), 'paid_at' => __( 'Paid at', 'nehtw-gateway' ) ) ); ?>
            <h2><?php esc_html_e( 'Subscription History', 'nehtw-gateway' ); ?></h2>
            <?php self::render_simple_table( $history, array( 'created_at' => __( 'Date', 'nehtw-gateway' ), 'action' => __( 'Action', 'nehtw-gateway' ), 'note' => __( 'Note', 'nehtw-gateway' ) ) ); ?>
            <h2><?php esc_html_e( 'Payment Retries', 'nehtw-gateway' ); ?></h2>
            <?php self::render_simple_table( $retries, array( 'attempt_number' => __( 'Attempt', 'nehtw-gateway' ), 'status' => __( 'Status', 'nehtw-gateway' ), 'scheduled_at' => __( 'Scheduled at', 'nehtw-gateway' ) ) ); ?>
            <h2><?php esc_html_e( 'Dunning Emails', 'nehtw-gateway' ); ?></h2>
            <?php self::render_simple_table( $dunning, array( 'sent_at' => __( 'Sent at', 'nehtw-gateway' ), 'email_type' => __( 'Email Type', 'nehtw-gateway' ), 'dunning_level' => __( 'Level', 'nehtw-gateway' ) ) ); ?>
            <?php
        }

        protected static function render_simple_table( $rows, $columns ) {
            if ( empty( $rows ) ) {
                echo '<p>' . esc_html__( 'No records found.', 'nehtw-gateway' ) . '</p>';
                return;
            }
            echo '<table class="widefat striped" style="margin-bottom:20px;"><thead><tr>';
            foreach ( $columns as $label ) {
                echo '<th>' . esc_html( $label ) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ( $rows as $row ) {
                echo '<tr>';
                foreach ( array_keys( $columns ) as $key ) {
                    $value = isset( $row[ $key ] ) ? $row[ $key ] : '';
                    $suffix3 = substr( $key, -3 );
                    $suffix4 = substr( $key, -4 );
                    if ( '_at' === $suffix3 || 'date' === strtolower( $suffix4 ) ) {
                        $value = self::format_date( $value );
                    }
                    echo '<td>' . esc_html( (string) $value ) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        protected static function handle_actions() {
            if ( empty( $_GET['nehtw_action'] ) || empty( $_GET['subscription_id'] ) ) {
                return;
            }

            $action = sanitize_key( wp_unslash( $_GET['nehtw_action'] ) );
            $subscription_id = absint( wp_unslash( $_GET['subscription_id'] ) );
            if ( $subscription_id <= 0 ) {
                return;
            }

            $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
            if ( ! wp_verify_nonce( $nonce, 'nehtw_subscription_action_' . $action . '_' . $subscription_id ) ) {
                return;
            }

            $result = self::update_subscription_status( $subscription_id, $action );
            $args = array(
                'page' => 'nehtw-subscriptions-admin',
                'nehtw_notice' => is_wp_error( $result ) ? 'error' : $action,
            );
            if ( is_wp_error( $result ) ) {
                $args['message'] = rawurlencode( $result->get_error_message() );
            }
            if ( isset( $_GET['view'] ) && 'details' === $_GET['view'] ) {
                $args['view'] = 'details';
                $args['subscription_id'] = $subscription_id;
            }

            wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
            exit;
        }

        protected static function update_subscription_status( $subscription_id, $action ) {
            $subscription = nehtw_gateway_get_subscription( $subscription_id );
            if ( ! $subscription ) {
                return new WP_Error( 'nehtw_invalid_subscription', __( 'Subscription not found.', 'nehtw-gateway' ) );
            }

            $map = array(
                'pause'  => 'paused',
                'resume' => 'active',
                'cancel' => 'cancelled',
            );

            if ( ! isset( $map[ $action ] ) ) {
                return new WP_Error( 'nehtw_invalid_action', __( 'Unsupported action.', 'nehtw-gateway' ) );
            }

            $new_status = $map[ $action ];
            $table      = nehtw_gateway_get_subscriptions_table();
            global $wpdb;

            $set = array( 'status = %s', 'updated_at = %s' );
            $params = array( $new_status, current_time( 'mysql' ) );

            if ( 'paused' === $new_status ) {
                $set[]   = 'paused_at = %s';
                $params[] = current_time( 'mysql' );
            }

            if ( 'active' === $new_status ) {
                $set[] = 'paused_at = NULL';
            }

            if ( 'cancelled' === $new_status ) {
                $set[]   = 'cancelled_at = %s';
                $params[] = current_time( 'mysql' );
            }

            $params[] = $subscription_id;
            $sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $set ) . ' WHERE id = %d';
            $wpdb->query( $wpdb->prepare( $sql, $params ) );

            $wpdb->insert(
                $wpdb->prefix . 'nehtw_subscription_history',
                array(
                    'subscription_id' => $subscription_id,
                    'user_id'         => $subscription['user_id'],
                    'action'          => 'status_change',
                    'note'            => sprintf( 'Status set to %s via admin', $new_status ),
                    'created_at'      => current_time( 'mysql' ),
                    'created_by'      => get_current_user_id(),
                ),
                array( '%d','%d','%s','%s','%s','%d' )
            );

            return true;
        }

        protected static function action_button( $subscription_id, $action, $label, $class = 'button' ) {
            $nonce = wp_create_nonce( 'nehtw_subscription_action_' . $action . '_' . $subscription_id );
            $url   = add_query_arg(
                array(
                    'page'            => 'nehtw-subscriptions-admin',
                    'nehtw_action'    => $action,
                    'subscription_id' => $subscription_id,
                    '_wpnonce'        => $nonce,
                    'view'            => 'details',
                ),
                admin_url( 'admin.php' )
            );
            return '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
        }

        public static function format_date( $value ) {
            if ( empty( $value ) ) {
                return __( '—', 'nehtw-gateway' );
            }
            $timestamp = strtotime( $value );
            if ( ! $timestamp ) {
                return $value;
            }
            return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
        }

        protected static function render_notice( $code, $message ) {
            if ( ! $code ) {
                return;
            }

            $classes = 'notice notice-success';
            $text    = '';

            switch ( $code ) {
                case 'pause':
                    $text = __( 'Subscription paused.', 'nehtw-gateway' );
                    break;
                case 'resume':
                    $text = __( 'Subscription resumed.', 'nehtw-gateway' );
                    break;
                case 'cancel':
                    $text = __( 'Subscription cancelled.', 'nehtw-gateway' );
                    break;
                case 'error':
                    $classes = 'notice notice-error';
                    $text    = $message ? $message : __( 'An error occurred.', 'nehtw-gateway' );
                    break;
                default:
                    $text = $message;
            }

            if ( $text ) {
                echo '<div class="' . esc_attr( $classes ) . '"><p>' . esc_html( $text ) . '</p></div>';
            }
        }

        public static function get_health_metrics() {
            global $wpdb;
            $table = nehtw_gateway_get_subscriptions_table();
            if ( ! $table ) {
                return array(
                    'active'          => 0,
                    'overdue'         => 0,
                    'pending_retries' => 0,
                    'last_billing_run'=> get_option( 'nehtw_billing_cron_last_run' ),
                );
            }

            $active  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" );
            $overdue = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'overdue'" );
            $pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_payment_retries WHERE status IN ('scheduled','in_progress')" );

            return array(
                'active'          => $active,
                'overdue'         => $overdue,
                'pending_retries' => $pending,
                'last_billing_run'=> get_option( 'nehtw_billing_cron_last_run' ),
            );
        }
    }

    class Nehtw_Subscriptions_List_Table extends WP_List_Table {

        public function __construct() {
            parent::__construct( array(
                'singular' => 'nehtw_subscription',
                'plural'   => 'nehtw_subscriptions',
                'ajax'     => false,
            ) );
        }

        public function get_columns() {
            return array(
                'id'            => __( 'ID', 'nehtw-gateway' ),
                'user'          => __( 'User', 'nehtw-gateway' ),
                'product'       => __( 'Product', 'nehtw-gateway' ),
                'status'        => __( 'Status', 'nehtw-gateway' ),
                'points'        => __( 'Points/Interval', 'nehtw-gateway' ),
                'interval'      => __( 'Interval', 'nehtw-gateway' ),
                'last_renewal'  => __( 'Last Renewal', 'nehtw-gateway' ),
                'next_renewal'  => __( 'Next Renewal', 'nehtw-gateway' ),
                'wallet'        => __( 'Wallet Balance', 'nehtw-gateway' ),
            );
        }

        public function prepare_items() {
            global $wpdb;
            $table = nehtw_gateway_get_subscriptions_table();
            $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
            $per_page = 20;
            $current_page = $this->get_pagenum();
            $offset = ( $current_page - 1 ) * $per_page;

            $where = array();
            $params = array();
            if ( $status ) {
                $where[] = 'status = %s';
                $params[] = $status;
            }
            $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

            $sql = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
            $items = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
            $count_sql = "SELECT COUNT(*) FROM {$table} {$where_sql}";
            $total_items = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

            foreach ( $items as &$item ) {
                if ( ! empty( $item['meta'] ) && is_string( $item['meta'] ) ) {
                    $decoded = json_decode( $item['meta'], true );
                    $item['meta'] = is_array( $decoded ) ? $decoded : array();
                } elseif ( empty( $item['meta'] ) ) {
                    $item['meta'] = array();
                }
            }

            $this->items = $items;
            $this->set_pagination_args( array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => $total_items ? ceil( $total_items / $per_page ) : 1,
            ) );
        }

        public function column_id( $item ) {
            $url = add_query_arg(
                array(
                    'page'            => 'nehtw-subscriptions-admin',
                    'view'            => 'details',
                    'subscription_id' => $item['id'],
                ),
                admin_url( 'admin.php' )
            );
            $actions = array(
                'view'   => '<a href="' . esc_url( $url ) . '">' . esc_html__( 'View', 'nehtw-gateway' ) . '</a>',
                'pause'  => self::build_row_action( $item['id'], 'pause', __( 'Pause', 'nehtw-gateway' ) ),
                'resume' => self::build_row_action( $item['id'], 'resume', __( 'Resume', 'nehtw-gateway' ) ),
                'cancel' => self::build_row_action( $item['id'], 'cancel', __( 'Cancel', 'nehtw-gateway' ) ),
            );
            return sprintf( '#%1$d %2$s', $item['id'], $this->row_actions( $actions ) );
        }

        protected static function build_row_action( $subscription_id, $action, $label ) {
            $nonce = wp_create_nonce( 'nehtw_subscription_action_' . $action . '_' . $subscription_id );
            $url = add_query_arg(
                array(
                    'page'            => 'nehtw-subscriptions-admin',
                    'nehtw_action'    => $action,
                    'subscription_id' => $subscription_id,
                    '_wpnonce'        => $nonce,
                ),
                admin_url( 'admin.php' )
            );
            return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
        }

        public function column_user( $item ) {
            $user = get_user_by( 'id', $item['user_id'] );
            if ( ! $user ) {
                return '<em>' . esc_html__( 'Unknown user', 'nehtw-gateway' ) . '</em>';
            }
            $url = get_edit_user_link( $user->ID );
            return '<a href="' . esc_url( $url ) . '">' . esc_html( $user->display_name ) . '</a><br /><small>' . esc_html( $user->user_email ) . '</small>';
        }

        public function column_product( $item ) {
            $product_id = Nehtw_Subscription_Product_Helper::get_product_id_from_plan_key( $item['plan_key'] );
            $name = isset( $item['meta']['plan_name'] ) ? $item['meta']['plan_name'] : $item['plan_key'];
            if ( $product_id ) {
                $link = get_edit_post_link( $product_id );
                if ( $link ) {
                    return '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>';
                }
            }
            return esc_html( $name );
        }

        public function column_status( $item ) {
            return '<span class="nehtw-chip nehtw-chip-' . esc_attr( $item['status'] ) . '">' . esc_html( ucfirst( $item['status'] ) ) . '</span>';
        }

        public function column_points( $item ) {
            return esc_html( number_format_i18n( $item['points_per_interval'] ) );
        }

        public function column_interval( $item ) {
            $count = isset( $item['meta']['interval_count'] ) ? (int) $item['meta']['interval_count'] : 1;
            return esc_html( sprintf( _n( 'Every %1$d %2$s', 'Every %1$d %2$ss', $count, 'nehtw-gateway' ), $count, $item['interval'] ) );
        }

        public function column_last_renewal( $item ) {
            $value = isset( $item['meta']['last_renewal_at'] ) ? $item['meta']['last_renewal_at'] : '';
            return esc_html( Nehtw_Admin_Subscriptions::format_date( $value ) );
        }

        public function column_next_renewal( $item ) {
            return esc_html( Nehtw_Admin_Subscriptions::format_date( $item['next_renewal_at'] ) );
        }

        public function column_wallet( $item ) {
            return esc_html( number_format_i18n( nehtw_gateway_get_balance( $item['user_id'] ) ) );
        }

        public function no_items() {
            esc_html_e( 'No subscriptions found.', 'nehtw-gateway' );
        }
    }

    class Nehtw_Invoices_List_Table extends WP_List_Table {

        public function __construct() {
            parent::__construct( array(
                'singular' => 'nehtw_invoice',
                'plural'   => 'nehtw_invoices',
                'ajax'     => false,
            ) );
        }

        public function get_columns() {
            return array(
                'id'        => __( 'ID', 'nehtw-gateway' ),
                'subscription_id' => __( 'Subscription', 'nehtw-gateway' ),
                'user'      => __( 'User', 'nehtw-gateway' ),
                'order'     => __( 'Order', 'nehtw-gateway' ),
                'amount'    => __( 'Amount', 'nehtw-gateway' ),
                'status'    => __( 'Status', 'nehtw-gateway' ),
                'created_at'=> __( 'Invoice Date', 'nehtw-gateway' ),
            );
        }

        public function prepare_items() {
            global $wpdb;
            $per_page = 20;
            $current_page = $this->get_pagenum();
            $offset = ( $current_page - 1 ) * $per_page;
            $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
            $subscription_id = isset( $_GET['subscription_id'] ) ? absint( wp_unslash( $_GET['subscription_id'] ) ) : 0;

            $where = array();
            $params = array();
            if ( $status ) {
                $where[] = 'status = %s';
                $params[] = $status;
            }
            if ( $subscription_id ) {
                $where[] = 'subscription_id = %d';
                $params[] = $subscription_id;
            }
            $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

            $sql = "SELECT * FROM {$wpdb->prefix}nehtw_invoices {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
            $items = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
            $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}nehtw_invoices {$where_sql}";
            $total_items = $params ? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : (int) $wpdb->get_var( $count_sql );

            foreach ( $items as &$item ) {
                if ( ! empty( $item['meta'] ) && is_string( $item['meta'] ) ) {
                    $decoded = json_decode( $item['meta'], true );
                    $item['meta'] = is_array( $decoded ) ? $decoded : array();
                }
            }

            $this->items = $items;
            $this->set_pagination_args( array(
                'total_items' => $total_items,
                'per_page'    => $per_page,
                'total_pages' => $total_items ? ceil( $total_items / $per_page ) : 1,
            ) );
        }

        public function column_id( $item ) {
            return '#' . absint( $item['id'] );
        }

        public function column_subscription_id( $item ) {
            $url = add_query_arg(
                array(
                    'page'            => 'nehtw-subscriptions-admin',
                    'view'            => 'details',
                    'subscription_id' => $item['subscription_id'],
                ),
                admin_url( 'admin.php' )
            );
            return '<a href="' . esc_url( $url ) . '">#' . absint( $item['subscription_id'] ) . '</a>';
        }

        public function column_user( $item ) {
            $user = get_user_by( 'id', $item['user_id'] );
            if ( ! $user ) {
                return '<em>' . esc_html__( 'Unknown user', 'nehtw-gateway' ) . '</em>';
            }
            return '<a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a>';
        }

        public function column_order( $item ) {
            $order_id = isset( $item['meta']['order_id'] ) ? absint( $item['meta']['order_id'] ) : 0;
            if ( ! $order_id ) {
                return '—';
            }
            $link = get_edit_post_link( $order_id );
            if ( ! $link ) {
                return '#' . $order_id;
            }
            return '<a href="' . esc_url( $link ) . '">#' . $order_id . '</a>';
        }

        public function column_amount( $item ) {
            if ( function_exists( 'wc_price' ) ) {
                return esc_html( wc_price( $item['total_amount'], array( 'currency' => $item['currency'] ) ) );
            }
            return esc_html( $item['currency'] . ' ' . number_format_i18n( $item['total_amount'], 2 ) );
        }

        public function column_status( $item ) {
            return esc_html( ucfirst( $item['status'] ) );
        }

        public function column_created_at( $item ) {
            return esc_html( Nehtw_Admin_Subscriptions::format_date( $item['created_at'] ) );
        }

        public function no_items() {
            esc_html_e( 'No invoices found.', 'nehtw-gateway' );
        }
    }
}