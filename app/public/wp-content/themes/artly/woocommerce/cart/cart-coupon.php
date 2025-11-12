<?php
/**
 * Cart Coupon Form Template
 * Minimal coupon form for Artly cart
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

if ( ! wc_coupons_enabled() ) {
    return;
}

?>
<div class="artly-coupon-form">
    <label for="coupon_code" class="screen-reader-text"><?php esc_html_e( 'Coupon:', 'woocommerce' ); ?></label>
    <input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Coupon code', 'woocommerce' ); ?>" />
    <button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="apply_coupon" value="<?php esc_attr_e( 'Apply coupon', 'woocommerce' ); ?>"><?php esc_html_e( 'Apply coupon', 'woocommerce' ); ?></button>
    <?php do_action( 'woocommerce_cart_coupon' ); ?>
</div>

