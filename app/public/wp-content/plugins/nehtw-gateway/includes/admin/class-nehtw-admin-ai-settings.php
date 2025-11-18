<?php
/**
 * Admin settings for AI pricing (points model).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Admin_AI_Settings {

    public static function init() {
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
    }

    public static function register_settings() {
        register_setting( 'nehtw_ai_settings', 'artly_ai_generate_cost_points', array( 'type' => 'integer', 'default' => 5 ) );
        register_setting( 'nehtw_ai_settings', 'artly_ai_vary_cost_points', array( 'type' => 'integer', 'default' => 2 ) );
        register_setting( 'nehtw_ai_settings', 'artly_ai_upscale_cost_points', array( 'type' => 'integer', 'default' => 3 ) );
    }

    public static function add_menu_page() {
        add_submenu_page(
            'nehtw-gateway',
            __( 'AI Image Generation', 'nehtw-gateway' ),
            __( 'AI Image Generation', 'nehtw-gateway' ),
            'manage_options',
            'nehtw-ai-settings',
            array( __CLASS__, 'render_settings_page' )
        );
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'AI Image Generation', 'nehtw-gateway' ); ?></h1>
            <p><?php esc_html_e( 'Configure the wallet point costs for AI operations. These values are charged per operation.', 'nehtw-gateway' ); ?></p>

            <form method="post" action="options.php">
                <?php settings_fields( 'nehtw_ai_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="artly_ai_generate_cost_points"><?php esc_html_e( 'Points cost: Generate (Imagine)', 'nehtw-gateway' ); ?></label>
                            </th>
                            <td>
                                <input type="number" min="0" step="1" id="artly_ai_generate_cost_points" name="artly_ai_generate_cost_points" value="<?php echo esc_attr( get_option( 'artly_ai_generate_cost_points', 5 ) ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="artly_ai_vary_cost_points"><?php esc_html_e( 'Points cost: Vary', 'nehtw-gateway' ); ?></label>
                            </th>
                            <td>
                                <input type="number" min="0" step="1" id="artly_ai_vary_cost_points" name="artly_ai_vary_cost_points" value="<?php echo esc_attr( get_option( 'artly_ai_vary_cost_points', 2 ) ); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="artly_ai_upscale_cost_points"><?php esc_html_e( 'Points cost: Upscale', 'nehtw-gateway' ); ?></label>
                            </th>
                            <td>
                                <input type="number" min="0" step="1" id="artly_ai_upscale_cost_points" name="artly_ai_upscale_cost_points" value="<?php echo esc_attr( get_option( 'artly_ai_upscale_cost_points', 3 ) ); ?>" />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

Nehtw_Admin_AI_Settings::init();
