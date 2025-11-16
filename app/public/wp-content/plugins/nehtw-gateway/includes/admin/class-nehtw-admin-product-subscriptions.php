<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Admin_Product_Subscriptions {

    public static function init() {
        if ( ! is_admin() ) {
            return;
        }

        add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_box' ) );
        add_action( 'save_post_product', array( __CLASS__, 'save_meta_box' ), 10, 2 );
    }

    public static function register_meta_box() {
        add_meta_box(
            'nehtw-product-subscriptions',
            __( 'Artly / Nehtw Subscription Options', 'nehtw-gateway' ),
            array( __CLASS__, 'render_meta_box' ),
            'product',
            'normal',
            'default'
        );
    }

    public static function render_meta_box( $post ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            echo '<p>' . esc_html__( 'WooCommerce must be active to configure subscriptions.', 'nehtw-gateway' ) . '</p>';
            return;
        }

        $product = wc_get_product( $post->ID );
        if ( ! $product || ! $product->is_type( 'simple' ) ) {
            echo '<p>' . esc_html__( 'Subscription options are available for simple products only.', 'nehtw-gateway' ) . '</p>';
            return;
        }

        wp_nonce_field( 'nehtw_save_product_subscription', 'nehtw_product_subscription_nonce' );

        $config = Nehtw_Subscription_Product_Helper::get_subscription_config( $post->ID );
        $is_subscription = $config['is_subscription'];
        $points          = $config['points'];
        $interval        = $config['interval'];
        $interval_count  = $config['interval_count'];
        $description     = get_post_meta( $post->ID, Nehtw_Subscription_Product_Helper::META_DESCRIPTION, true );
        ?>
        <table class="form-table nehtw-product-subscription-options">
            <tr>
                <th scope="row">
                    <label for="nehtw_is_subscription"><?php esc_html_e( 'Enable subscription', 'nehtw-gateway' ); ?></label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" id="nehtw_is_subscription" name="nehtw_is_subscription" value="1" <?php checked( $is_subscription ); ?> />
                        <?php esc_html_e( 'This product sells an Artly / Nehtw subscription plan.', 'nehtw-gateway' ); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="nehtw_subscription_points"><?php esc_html_e( 'Points per interval', 'nehtw-gateway' ); ?></label></th>
                <td>
                    <input type="number" class="small-text" min="1" step="1" id="nehtw_subscription_points" name="nehtw_subscription_points" value="<?php echo esc_attr( $points ); ?>" />
                    <p class="description"><?php esc_html_e( 'Wallet points that will be credited on every billing cycle.', 'nehtw-gateway' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="nehtw_subscription_interval_count"><?php esc_html_e( 'Billing frequency', 'nehtw-gateway' ); ?></label></th>
                <td>
                    <input type="number" class="small-text" min="1" step="1" id="nehtw_subscription_interval_count" name="nehtw_subscription_interval_count" value="<?php echo esc_attr( $interval_count ); ?>" />
                    <select name="nehtw_subscription_interval" id="nehtw_subscription_interval">
                        <?php foreach ( array( 'day', 'week', 'month', 'year' ) as $option ) : ?>
                            <option value="<?php echo esc_attr( $option ); ?>" <?php selected( $interval, $option ); ?>><?php echo esc_html( ucfirst( $option ) ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Example: Every 1 month, or every 3 months.', 'nehtw-gateway' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="nehtw_subscription_description"><?php esc_html_e( 'Internal notes', 'nehtw-gateway' ); ?></label></th>
                <td>
                    <textarea id="nehtw_subscription_description" name="nehtw_subscription_description" rows="3" class="large-text"><?php echo esc_textarea( $description ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Optional description for support or future display.', 'nehtw-gateway' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function save_meta_box( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['nehtw_product_subscription_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nehtw_product_subscription_nonce'] ) ), 'nehtw_save_product_subscription' ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_product', $post_id ) ) {
            return;
        }

        $product = function_exists( 'wc_get_product' ) ? wc_get_product( $post_id ) : null;
        if ( ! $product || ! $product->is_type( 'simple' ) ) {
            return;
        }

        $is_subscription = isset( $_POST['nehtw_is_subscription'] ) ? 1 : 0;
        update_post_meta( $post_id, Nehtw_Subscription_Product_Helper::META_IS_SUBSCRIPTION, $is_subscription );

        if ( ! $is_subscription ) {
            delete_post_meta( $post_id, Nehtw_Subscription_Product_Helper::META_POINTS );
            delete_post_meta( $post_id, Nehtw_Subscription_Product_Helper::META_INTERVAL );
            delete_post_meta( $post_id, Nehtw_Subscription_Product_Helper::META_INTERVAL_COUNT );
            delete_post_meta( $post_id, Nehtw_Subscription_Product_Helper::META_DESCRIPTION );
            return;
        }

        $points = isset( $_POST['nehtw_subscription_points'] ) ? absint( wp_unslash( $_POST['nehtw_subscription_points'] ) ) : 0;
        $interval = isset( $_POST['nehtw_subscription_interval'] ) ? sanitize_key( wp_unslash( $_POST['nehtw_subscription_interval'] ) ) : 'month';
        $interval = in_array( $interval, array( 'day', 'week', 'month', 'year' ), true ) ? $interval : 'month';
        $interval_count = isset( $_POST['nehtw_subscription_interval_count'] ) ? absint( wp_unslash( $_POST['nehtw_subscription_interval_count'] ) ) : 1;
        $interval_count = max( 1, $interval_count );
        $description = isset( $_POST['nehtw_subscription_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['nehtw_subscription_description'] ) ) : '';

        update_post_meta( $post_id, Nehtw_Subscription_Product_Helper::META_POINTS, $points );
        update_post_meta( $post_id, Nehtw_Subscription_Product_Helper::META_INTERVAL, $interval );
        update_post_meta( $post_id, Nehtw_Subscription_Product_Helper::META_INTERVAL_COUNT, $interval_count );
        update_post_meta( $post_id, Nehtw_Subscription_Product_Helper::META_DESCRIPTION, $description );
    }
}
