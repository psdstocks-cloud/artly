<?php
/**
 * My Account Navigation Template
 * Custom sidebar navigation for Artly account pages
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

$menu_items = wc_get_account_menu_items();
?>

<nav class="artly-acc-nav">
    <ul>
        <?php foreach ( $menu_items as $endpoint => $label ) : ?>
            <li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
                    <span class="icon icon-<?php echo esc_attr( $endpoint ); ?>"></span>
                    <span><?php echo esc_html( $label ); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

