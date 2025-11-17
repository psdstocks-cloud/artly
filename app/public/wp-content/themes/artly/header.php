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

<!-- Announcement bar -->
<div class="artly-announcement-bar" id="artly-announcement">
  <div class="artly-announcement-inner">
    <span class="artly-announcement-badge">NEW</span>
    <span>Get 20% bonus points on your first purchase</span>
    <a href="<?php echo esc_url( home_url( '/signup/' ) ); ?>" class="artly-announcement-cta">Claim Offer →</a>
  </div>
  <button class="artly-announcement-close" aria-label="Dismiss" onclick="document.getElementById('artly-announcement').style.display='none'">×</button>
</div>

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
        <ul class="artly-header-menu">
          <li>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
              <?php esc_html_e( 'Home', 'artly' ); ?>
            </a>
          </li>
          <li>
            <a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">
              <?php esc_html_e( 'Pricing', 'artly' ); ?>
            </a>
          </li>
          <?php if ( is_user_logged_in() ) : ?>
            <li>
              <a href="<?php echo esc_url( home_url( '/stock-order/' ) ); ?>">
                <?php esc_html_e( 'Order Stock', 'artly' ); ?>
              </a>
            </li>
            <li>
              <a href="<?php echo esc_url( home_url( '/my-downloads/' ) ); ?>">
                <?php esc_html_e( 'My Downloads', 'artly' ); ?>
              </a>
            </li>
          <?php endif; ?>
          <li class="artly-header-menu-item--lang-switcher">
            <button 
              type="button" 
              class="artly-header-lang-toggle"
              id="artly-lang-toggle"
              aria-label="<?php esc_attr_e( 'Switch language', 'artly' ); ?>"
              title="<?php esc_attr_e( 'Switch language', 'artly' ); ?>"
            >
              <?php
              $current_lang = function_exists( 'artly_get_current_language' ) ? artly_get_current_language() : 'en';
              $other_lang = ( $current_lang === 'en' ) ? 'ar' : 'en';
              $current_label = ( $current_lang === 'en' ) ? 'EN' : 'العربية';
              $other_label = ( $other_lang === 'en' ) ? 'EN' : 'العربية';
              ?>
              <span class="artly-lang-current"><?php echo esc_html( $current_label ); ?></span>
              <span class="artly-lang-separator">/</span>
              <span class="artly-lang-other"><?php echo esc_html( $other_label ); ?></span>
            </button>
          </li>
        </ul>
        
        <!-- Mobile CTA buttons -->
        <div class="artly-header-cta-mobile">
          <?php if ( is_user_logged_in() ) : ?>
            <a class="artly-header-link" href="<?php echo esc_url( home_url( '/stock-order/' ) ); ?>">
              <?php esc_html_e( 'Order Stock', 'artly' ); ?>
            </a>
            <a class="artly-header-link" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">
              <?php esc_html_e( 'My Account', 'artly' ); ?>
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
      </nav>

      <div class="artly-header-cta">
        <?php if ( is_user_logged_in() ) : ?>
          <?php
          $current_user = wp_get_current_user();
          $wallet_balance = function_exists( 'nehtw_gateway_get_user_points_balance' ) 
            ? nehtw_gateway_get_user_points_balance( $current_user->ID ) 
            : 0;
          ?>
          <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) . 'wallet/' ); ?>" class="artly-header-wallet-badge" title="<?php esc_attr_e( 'Your wallet balance', 'artly' ); ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M21 18V19C21 20.1 20.1 21 19 21H5C3.89 21 3 20.1 3 19V5C3 3.9 3.89 3 5 3H19C20.1 3 21 3.9 21 5V6H12C10.89 6 10 6.9 10 8V16C10 17.1 10.89 18 12 18H21ZM12 16H22V8H12V16ZM16 13.5C15.17 13.5 14.5 12.83 14.5 12C14.5 11.17 15.17 10.5 16 10.5C16.83 10.5 17.5 11.17 17.5 12C17.5 12.83 16.83 13.5 16 13.5Z" fill="currentColor"/>
            </svg>
            <span class="artly-header-wallet-balance">
              <?php echo esc_html( number_format_i18n( $wallet_balance, 0 ) ); ?>
            </span>
            <span class="artly-header-wallet-label"><?php esc_html_e( 'points', 'artly' ); ?></span>
          </a>
          <a class="artly-header-link" href="<?php echo esc_url( home_url( '/dashboard/' ) ); ?>">
            <?php esc_html_e( 'My Account', 'artly' ); ?>
          </a>
          <a class="artly-header-button artly-header-button--ghost" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
            <?php esc_html_e( 'Log out', 'artly' ); ?>
          </a>
        <?php else : ?>
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

<div class="artly-menu-backdrop"></div>

<div class="artly-page-shell">