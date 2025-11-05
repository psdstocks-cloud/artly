<?php
/**
 * Global header for Artly theme.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="artly-site-header">
  <div class="artly-header-inner">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="artly-header-logo">
      <span class="artly-header-logo-mark" aria-hidden="true"></span>
      <span class="artly-header-logo-text">
        <?php bloginfo( 'name' ); ?>
      </span>
    </a>

    <div class="artly-header-main">
      <nav class="artly-header-nav" aria-label="<?php esc_attr_e( 'Primary', 'artly' ); ?>">
        <?php
        wp_nav_menu(
            array(
                'theme_location' => 'primary',
                'menu_class'     => 'artly-header-menu',
                'container'      => false,
                'fallback_cb'    => false,
            )
        );
        ?>
      </nav>

      <div class="artly-header-cta">
        <?php if ( is_user_logged_in() ) : ?>
          <a class="artly-header-link" href="<?php echo esc_url( home_url( '/app/stock/' ) ); ?>">
            <?php esc_html_e( 'Dashboard', 'artly' ); ?>
          </a>
          <a class="artly-header-link" href="<?php echo esc_url( home_url( '/my-points/' ) ); ?>">
            <?php esc_html_e( 'My Points', 'artly' ); ?>
          </a>
          <a class="artly-header-button artly-header-button--ghost" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
            <?php esc_html_e( 'Log out', 'artly' ); ?>
          </a>
        <?php else : ?>
          <a class="artly-header-link" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">
            <?php esc_html_e( 'Pricing', 'artly' ); ?>
          </a>
          <a class="artly-header-link" href="<?php echo esc_url( home_url( '/login/' ) ); ?>">
            <?php esc_html_e( 'Sign in', 'artly' ); ?>
          </a>
          <a class="artly-header-button" href="<?php echo esc_url( home_url( '/signup/' ) ); ?>">
            <?php esc_html_e( 'Get started', 'artly' ); ?>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <button class="artly-header-toggle" type="button" aria-label="<?php esc_attr_e( 'Toggle navigation', 'artly' ); ?>">
      <span></span>
      <span></span>
    </button>
  </div>
</header>

<div class="artly-page-shell">
