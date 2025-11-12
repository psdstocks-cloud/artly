<?php
/**
 * Cart Page Template
 * Custom Artly-branded cart for digital points/credits
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

if ( wc_get_cart_contents_count() === 0 ) {
    wc_get_template( 'cart/cart-empty.php' );
    return;
}
?>

<div class="artly-cart-wrap">
    <div class="artly-cart-progress" aria-label="<?php esc_attr_e( 'Checkout progress', 'artly' ); ?>">
        <ol class="progress">
            <li class="done">
                <span class="progress-step"><?php esc_html_e( 'Pricing', 'artly' ); ?></span>
            </li>
            <li class="current">
                <span class="progress-step"><?php esc_html_e( 'Cart', 'artly' ); ?></span>
            </li>
            <li>
                <span class="progress-step"><?php esc_html_e( 'Checkout', 'artly' ); ?></span>
            </li>
            <li>
                <span class="progress-step"><?php esc_html_e( 'Done', 'artly' ); ?></span>
            </li>
        </ol>
    </div>

    <div class="artly-cart-grid">
        <section class="artly-cart-items">
            <?php do_action( 'woocommerce_before_cart_table' ); ?>

            <?php
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                $_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

                if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) {
                    continue;
                }

                $is_points   = ! empty( $cart_item['nehtw_is_points'] );
                $points      = intval( $cart_item['nehtw_points_amount'] ?? 0 );
                $currency    = strtoupper( $cart_item['nehtw_currency'] ?? get_woocommerce_currency() );
                $product_id  = $_product->get_id();
                $product_permalink = $_product->is_visible() ? $_product->get_permalink() : '';
                $remove_url  = wc_get_cart_remove_url( $cart_item_key );
                $unit_price  = ! empty( $cart_item['nehtw_dynamic_unit_price'] ) ? floatval( $cart_item['nehtw_dynamic_unit_price'] ) : 0;
                ?>
                <article class="artly-cart-card glass">
                    <div class="card-main">
                        <div class="card-title">
                            <h3 class="item-title">
                                <?php
                                if ( $is_points && $points > 0 ) {
                                    echo esc_html( sprintf( _n( '%d point', '%d points', $points, 'artly' ), $points ) );
                                } else {
                                    echo wp_kses_post( $_product->get_name() );
                                }
                                ?>
                            </h3>
                            <div class="item-price">
                                <?php echo wp_kses_post( WC()->cart->get_product_price( $_product ) ); ?>
                            </div>
                        </div>

                        <ul class="item-meta">
                            <?php if ( $is_points ) : ?>
                                <li>
                                    <strong><?php esc_html_e( 'Points', 'artly' ); ?>:</strong>
                                    <span><?php echo esc_html( number_format_i18n( $points ) ); ?></span>
                                </li>
                                <li>
                                    <strong><?php esc_html_e( 'Currency', 'artly' ); ?>:</strong>
                                    <span><?php echo esc_html( $currency ); ?></span>
                                </li>
                                <?php if ( $unit_price > 0 ) : ?>
                                    <li>
                                        <strong><?php esc_html_e( 'Unit price', 'artly' ); ?>:</strong>
                                        <span><?php echo esc_html( number_format_i18n( $unit_price, 4 ) . ' ' . get_woocommerce_currency_symbol() ); ?></span>
                                    </li>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php echo wc_get_formatted_cart_item_data( $cart_item ); ?>
                            <?php endif; ?>
                        </ul>

                        <div class="item-actions">
                            <a class="btn-link" href="<?php echo esc_url( $remove_url ); ?>" aria-label="<?php esc_attr_e( 'Remove item', 'artly' ); ?>">
                                <?php esc_html_e( 'Remove item', 'artly' ); ?>
                            </a>
                            <?php if ( $is_points ) : ?>
                                <a class="btn-link" href="<?php echo esc_url( home_url( '/pricing' ) ); ?>">
                                    <?php esc_html_e( 'Change points', 'artly' ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
                <?php
            }
            ?>

            <?php do_action( 'woocommerce_after_cart_table' ); ?>
        </section>

        <aside class="artly-cart-summary">
            <div class="summary-card glass">
                <?php do_action( 'woocommerce_before_cart_totals' ); ?>

                <h3 class="summary-title"><?php esc_html_e( 'Order Summary', 'artly' ); ?></h3>

                <div class="summary-rows">
                    <div class="row">
                        <span><?php esc_html_e( 'Subtotal', 'artly' ); ?></span>
                        <span><?php wc_cart_totals_subtotal_html(); ?></span>
                    </div>

                    <?php if ( wc_coupons_enabled() ) : ?>
                        <details class="coupon-toggle">
                            <summary><?php esc_html_e( 'Add coupon', 'artly' ); ?></summary>
                            <div class="coupon-form-wrapper">
                                <?php wc_get_template( 'cart/cart-coupon.php' ); ?>
                            </div>
                        </details>
                    <?php endif; ?>

                    <?php
                    foreach ( WC()->cart->get_coupons() as $code => $coupon ) :
                        ?>
                        <div class="row coupon-row">
                            <span>
                                <?php
                                wc_cart_totals_coupon_label( $coupon );
                                ?>
                            </span>
                            <span data-title="<?php echo esc_attr( wc_cart_totals_coupon_label( $coupon, false ) ); ?>">
                                <?php wc_cart_totals_coupon_html( $coupon ); ?>
                            </span>
                        </div>
                        <?php
                    endforeach;
                    ?>

                    <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
                        <?php do_action( 'woocommerce_cart_totals_before_shipping' ); ?>
                        <?php wc_cart_totals_shipping_html(); ?>
                        <?php do_action( 'woocommerce_cart_totals_after_shipping' ); ?>
                    <?php endif; ?>

                    <?php
                    foreach ( WC()->cart->get_fees() as $fee ) :
                        ?>
                        <div class="row fee-row">
                            <span><?php echo esc_html( $fee->name ); ?></span>
                            <span data-title="<?php echo esc_attr( $fee->name ); ?>">
                                <?php wc_cart_totals_fee_html( $fee ); ?>
                            </span>
                        </div>
                        <?php
                    endforeach;

                    if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) {
                        $taxable_address = WC()->customer->get_taxable_address();
                        $estimated_text  = '';

                        if ( WC()->customer->is_customer_outside_base() && $taxable_address ) {
                            $estimated_text = sprintf( ' <small>' . esc_html__( '(estimated for %s)', 'woocommerce' ) . '</small>', WC()->countries->estimated_for_prefix( $taxable_address[0] ) . WC()->countries->countries[ $taxable_address[0] ] );
                        }

                        if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) {
                            foreach ( WC()->cart->get_tax_totals() as $code => $tax ) {
                                ?>
                                <div class="row tax-row">
                                    <span><?php echo esc_html( $tax->label ) . $estimated_text; ?></span>
                                    <span data-title="<?php echo esc_attr( $tax->label ); ?>"><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
                                </div>
                                <?php
                            }
                        } else {
                            ?>
                            <div class="row tax-row">
                                <span><?php echo esc_html( WC()->countries->tax_or_vat() ) . $estimated_text; ?></span>
                                <span data-title="<?php echo esc_attr( WC()->countries->tax_or_vat() ); ?>"><?php wc_cart_totals_taxes_total_html(); ?></span>
                            </div>
                            <?php
                        }
                    }
                    ?>

                    <div class="row total-row">
                        <span><?php esc_html_e( 'Total', 'artly' ); ?></span>
                        <span><?php wc_cart_totals_order_total_html(); ?></span>
                    </div>
                </div>

                <div class="summary-cta">
                    <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="btn-primary btn-glow">
                        <span><?php esc_html_e( 'Proceed to Checkout', 'artly' ); ?></span>
                    </a>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn-outline">
                        <?php esc_html_e( 'Continue browsing', 'artly' ); ?>
                    </a>
                </div>

                <ul class="summary-trust">
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

                <?php do_action( 'woocommerce_after_cart_totals' ); ?>
            </div>

            <div class="faq-mini glass">
                <details>
                    <summary><?php esc_html_e( 'When do points arrive?', 'artly' ); ?></summary>
                    <p><?php esc_html_e( 'Immediately after payment (processing/completed).', 'artly' ); ?></p>
                </details>
                <details>
                    <summary><?php esc_html_e( 'Can I get an invoice?', 'artly' ); ?></summary>
                    <p><?php esc_html_e( 'Yes—download from your account → Orders after checkout.', 'artly' ); ?></p>
                </details>
                <details>
                    <summary><?php esc_html_e( 'Refund policy for credits', 'artly' ); ?></summary>
                    <p><?php esc_html_e( 'Credits are non-refundable once delivered. Issues? Contact support.', 'artly' ); ?></p>
                </details>
            </div>
        </aside>
    </div>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>

