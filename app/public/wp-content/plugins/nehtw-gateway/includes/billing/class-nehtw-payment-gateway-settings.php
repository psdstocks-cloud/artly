<?php
/**
 * Payment Gateway Settings
 * 
 * Admin settings for payment gateway configuration
 * 
 * @package Nehtw_Gateway
 * @subpackage Billing
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Nehtw_Payment_Gateway_Settings {
    
    /**
     * Initialize settings
     */
    public function __construct() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        // Register with priority 20 to ensure main menu is registered first
        add_action( 'admin_menu', [ $this, 'add_settings_page' ], 20 );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Stripe settings
        register_setting( 'nehtw_payment_gateways', 'nehtw_stripe_secret_key' );
        register_setting( 'nehtw_payment_gateways', 'nehtw_stripe_publishable_key' );
        register_setting( 'nehtw_payment_gateways', 'nehtw_stripe_webhook_secret' );
        
        // PayPal settings
        register_setting( 'nehtw_payment_gateways', 'nehtw_paypal_client_id' );
        register_setting( 'nehtw_payment_gateways', 'nehtw_paypal_secret' );
        register_setting( 'nehtw_payment_gateways', 'nehtw_paypal_mode' );
        
        // Default gateway
        register_setting( 'nehtw_payment_gateways', 'nehtw_default_payment_gateway' );
    }
    
    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'nehtw-gateway',
            __( 'Payment Gateways', 'nehtw-gateway' ),
            __( 'Payment Gateways', 'nehtw-gateway' ),
            'manage_options',
            'nehtw-payment-gateways',
            [ $this, 'render_settings_page' ]
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'nehtw_payment_gateways' ); ?>
                
                <h2><?php _e( 'Stripe Settings', 'nehtw-gateway' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nehtw_stripe_secret_key"><?php _e( 'Secret Key', 'nehtw-gateway' ); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="nehtw_stripe_secret_key" 
                                   name="nehtw_stripe_secret_key" 
                                   value="<?php echo esc_attr( get_option( 'nehtw_stripe_secret_key' ) ); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e( 'Your Stripe secret key (starts with sk_)', 'nehtw-gateway' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nehtw_stripe_publishable_key"><?php _e( 'Publishable Key', 'nehtw-gateway' ); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="nehtw_stripe_publishable_key" 
                                   name="nehtw_stripe_publishable_key" 
                                   value="<?php echo esc_attr( get_option( 'nehtw_stripe_publishable_key' ) ); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e( 'Your Stripe publishable key (starts with pk_)', 'nehtw-gateway' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e( 'PayPal Settings', 'nehtw-gateway' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nehtw_paypal_client_id"><?php _e( 'Client ID', 'nehtw-gateway' ); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="nehtw_paypal_client_id" 
                                   name="nehtw_paypal_client_id" 
                                   value="<?php echo esc_attr( get_option( 'nehtw_paypal_client_id' ) ); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nehtw_paypal_secret"><?php _e( 'Secret', 'nehtw-gateway' ); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="nehtw_paypal_secret" 
                                   name="nehtw_paypal_secret" 
                                   value="<?php echo esc_attr( get_option( 'nehtw_paypal_secret' ) ); ?>" 
                                   class="regular-text" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nehtw_paypal_mode"><?php _e( 'Mode', 'nehtw-gateway' ); ?></label>
                        </th>
                        <td>
                            <select id="nehtw_paypal_mode" name="nehtw_paypal_mode">
                                <option value="sandbox" <?php selected( get_option( 'nehtw_paypal_mode' ), 'sandbox' ); ?>><?php _e( 'Sandbox', 'nehtw-gateway' ); ?></option>
                                <option value="live" <?php selected( get_option( 'nehtw_paypal_mode' ), 'live' ); ?>><?php _e( 'Live', 'nehtw-gateway' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e( 'Default Gateway', 'nehtw-gateway' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nehtw_default_payment_gateway"><?php _e( 'Default Payment Gateway', 'nehtw-gateway' ); ?></label>
                        </th>
                        <td>
                            <select id="nehtw_default_payment_gateway" name="nehtw_default_payment_gateway">
                                <option value="stripe" <?php selected( get_option( 'nehtw_default_payment_gateway' ), 'stripe' ); ?>>Stripe</option>
                                <option value="paypal" <?php selected( get_option( 'nehtw_default_payment_gateway' ), 'paypal' ); ?>>PayPal</option>
                                <option value="woocommerce" <?php selected( get_option( 'nehtw_default_payment_gateway' ), 'woocommerce' ); ?>>WooCommerce</option>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize
new Nehtw_Payment_Gateway_Settings();

