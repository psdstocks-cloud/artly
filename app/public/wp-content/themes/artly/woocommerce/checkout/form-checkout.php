<?php
/**
 * Checkout Form Template
 * Custom Artly-branded checkout for digital points/credits
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_checkout_form', WC()->checkout() );

if ( ! is_user_logged_in() && ! WC()->checkout()->is_registration_enabled() ) {
    echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
    return;
}

// Check if cart has subscription products
$has_subscription = false;
$next_billing_date = null;
$subscription_plan_name = '';

if ( WC()->cart && ! WC()->cart->is_empty() ) {
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $product_id = $cart_item['product_id'];
        $product = wc_get_product( $product_id );
        
        if ( ! $product ) {
            continue;
        }
        
        // Check if this is a subscription product by SKU pattern, product meta, or subscription plans
        $is_subscription = false;
        
        // Method 1: Check SKU pattern
        $sku = $product->get_sku();
        if ( $sku && ( strpos( $sku, 'ARTLY-SUB-' ) === 0 || strpos( $sku, 'SUBSCRIPTION' ) !== false ) ) {
            $is_subscription = true;
        }
        
        // Method 2: Check subscription plans
        if ( ! $is_subscription && function_exists( 'nehtw_gateway_get_subscription_plans' ) ) {
            $plans = nehtw_gateway_get_subscription_plans();
            foreach ( $plans as $plan ) {
                $plan_product_id = isset( $plan['product_id'] ) ? intval( $plan['product_id'] ) : 0;
                if ( $plan_product_id === $product_id ) {
                    $is_subscription = true;
                    $subscription_plan_name = isset( $plan['name'] ) ? $plan['name'] : '';
                    break;
                }
            }
        }
        
        // Method 3: Check product meta/categories
        if ( ! $is_subscription ) {
            $product_categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'slugs' ) );
            if ( in_array( 'subscription', $product_categories, true ) ) {
                $is_subscription = true;
            }
        }
        
        if ( $is_subscription ) {
            $has_subscription = true;
            
            // Get subscription plan name if not already set
            if ( empty( $subscription_plan_name ) && function_exists( 'nehtw_gateway_get_subscription_plans' ) ) {
                $plans = nehtw_gateway_get_subscription_plans();
                foreach ( $plans as $plan ) {
                    $plan_product_id = isset( $plan['product_id'] ) ? intval( $plan['product_id'] ) : 0;
                    if ( $plan_product_id === $product_id ) {
                        $subscription_plan_name = isset( $plan['name'] ) ? $plan['name'] : $product->get_name();
                        break;
                    }
                }
            }
            
            if ( empty( $subscription_plan_name ) ) {
                $subscription_plan_name = $product->get_name();
            }
            
            // Calculate next billing date (1 month from today, same day of month)
            $today = current_time( 'timestamp' );
            $next_billing = strtotime( '+1 month', $today );
            $next_billing_date = date_i18n( get_option( 'date_format' ), $next_billing );
            break;
        }
    }
}
?>

<div class="artly-checkout-wrap">
    <div class="artly-checkout-progress" aria-label="<?php esc_attr_e( 'Checkout progress', 'artly' ); ?>">
        <ol class="progress">
            <li class="done">
                <span class="progress-step"><?php esc_html_e( 'Pricing', 'artly' ); ?></span>
            </li>
            <li class="done">
                <span class="progress-step"><?php esc_html_e( 'Cart', 'artly' ); ?></span>
            </li>
            <li class="current">
                <span class="progress-step"><?php esc_html_e( 'Checkout', 'artly' ); ?></span>
            </li>
            <li>
                <span class="progress-step"><?php esc_html_e( 'Done', 'artly' ); ?></span>
            </li>
        </ol>
    </div>

    <form name="checkout" method="post" class="checkout woocommerce-checkout artly-checkout-form" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">
        <div class="artly-checkout-grid">
            <div class="artly-checkout-main">
                <?php if ( WC()->checkout->get_checkout_fields() ) : ?>
                    <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

                    <div class="artly-checkout-sections">
                        <?php if ( ! is_user_logged_in() && WC()->checkout->is_registration_enabled() ) : ?>
                            <section class="artly-checkout-section glass">
                                <h2 class="section-title"><?php esc_html_e( 'Account', 'artly' ); ?></h2>
                                <?php
                                $checkout = WC()->checkout();
                                foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) {
                                    woocommerce_form_field( $key, $field, $checkout->get_value( $key ) );
                                }
                                ?>
                            </section>
                        <?php endif; ?>

                        <section class="artly-checkout-section glass">
                            <h2 class="section-title"><?php esc_html_e( 'Billing Details', 'artly' ); ?></h2>
                            <?php do_action( 'woocommerce_checkout_billing' ); ?>
                        </section>

                        <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
                            <section class="artly-checkout-section glass">
                                <h2 class="section-title"><?php esc_html_e( 'Shipping Details', 'artly' ); ?></h2>
                                <?php do_action( 'woocommerce_checkout_shipping' ); ?>
                            </section>
                        <?php endif; ?>

                        <?php if ( WC()->checkout->get_checkout_fields( 'order' ) ) : ?>
                            <section class="artly-checkout-section glass">
                                <h2 class="section-title"><?php esc_html_e( 'Additional Information', 'artly' ); ?></h2>
                                <?php do_action( 'woocommerce_checkout_order' ); ?>
                            </section>
                        <?php endif; ?>

                        <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
                    </div>
                <?php endif; ?>

                <?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
            </div>

            <aside class="artly-checkout-sidebar">
                <div class="artly-checkout-order-review glass">
                    <h3 class="order-review-title"><?php esc_html_e( 'Your Order', 'artly' ); ?></h3>

                    <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

                    <div id="order_review" class="woocommerce-checkout-review-order">
                        <?php do_action( 'woocommerce_checkout_order_review' ); ?>
                    </div>

                    <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
                </div>

                <?php if ( $has_subscription && $next_billing_date ) : ?>
                    <div class="artly-billing-info glass">
                        <div class="billing-info-header">
                            <span class="billing-icon">📅</span>
                            <h4 class="billing-title"><?php esc_html_e( 'Subscription Details', 'artly' ); ?></h4>
                        </div>
                        <div class="billing-info-content">
                            <?php if ( $subscription_plan_name ) : ?>
                                <div class="billing-row">
                                    <span class="billing-label"><?php esc_html_e( 'Plan', 'artly' ); ?>:</span>
                                    <span class="billing-value"><?php echo esc_html( $subscription_plan_name ); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="billing-row billing-row-highlight">
                                <span class="billing-label"><?php esc_html_e( 'Next billing date', 'artly' ); ?>:</span>
                                <span class="billing-value billing-value-emphasis"><?php echo esc_html( $next_billing_date ); ?></span>
                            </div>
                            <p class="billing-note">
                                <?php esc_html_e( 'Your subscription will automatically renew and points will be credited to your wallet.', 'artly' ); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="artly-checkout-trust glass">
                    <ul class="trust-list">
                        <li>
                            <span class="trust-icon">🛡️</span>
                            <span><?php esc_html_e( 'Secure payment', 'artly' ); ?></span>
                        </li>
                        <li>
                            <span class="trust-icon">⚡</span>
                            <span><?php esc_html_e( 'Instant credit after payment', 'artly' ); ?></span>
                        </li>
                        <li>
                            <span class="trust-icon">💬</span>
                            <span><?php esc_html_e( 'Support in MENA hours', 'artly' ); ?></span>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>
    </form>
</div>

<?php do_action( 'woocommerce_after_checkout_form', WC()->checkout() ); ?>

