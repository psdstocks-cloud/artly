<?php
/**
 * Global footer for Artly theme.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

</div><!-- .artly-page-shell -->

<footer class="artly-site-footer">
  <div class="artly-footer-inner">
    <div class="artly-footer-brand">
      <div class="artly-footer-brand-title">
        <?php bloginfo( 'name' ); ?>
      </div>
      <p class="artly-footer-brand-text">
        <?php esc_html_e( 'A single wallet and dashboard for your AI art, stock downloads and creative tools, starting with Egypt and built for global teams.', 'artly' ); ?>
      </p>
    </div>

    <div class="artly-footer-links">
      <div class="artly-footer-col">
        <h4><?php esc_html_e( 'Product', 'artly' ); ?></h4>
        <ul>
          <li><a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>"><?php esc_html_e( 'Pricing', 'artly' ); ?></a></li>
          <li><a href="<?php echo esc_url( home_url( '/app/stock/' ) ); ?>"><?php esc_html_e( 'Stock downloads', 'artly' ); ?></a></li>
          <li><a href="<?php echo esc_url( home_url( '/login/' ) ); ?>"><?php esc_html_e( 'Dashboard', 'artly' ); ?></a></li>
        </ul>
      </div>

      <div class="artly-footer-col">
        <h4><?php esc_html_e( 'Company', 'artly' ); ?></h4>
        <ul>
          <li><a href="<?php echo esc_url( home_url( '/about/' ) ); ?>"><?php esc_html_e( 'About', 'artly' ); ?></a></li>
          <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Contact', 'artly' ); ?></a></li>
        </ul>
      </div>

      <div class="artly-footer-col">
        <h4><?php esc_html_e( 'Legal', 'artly' ); ?></h4>
        <ul>
          <li><a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>"><?php esc_html_e( 'Terms', 'artly' ); ?></a></li>
          <li><a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>"><?php esc_html_e( 'Privacy', 'artly' ); ?></a></li>
        </ul>
      </div>
    </div>
  </div>

  <div class="artly-footer-bottom">
    <p>
      &copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?>
      <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'artly' ); ?>
    </p>
    <div class="artly-footer-bottom-links">
      <a href="<?php echo esc_url( home_url( '/status/' ) ); ?>"><?php esc_html_e( 'Status', 'artly' ); ?></a>
      <a href="<?php echo esc_url( home_url( '/help/' ) ); ?>"><?php esc_html_e( 'Help center', 'artly' ); ?></a>
    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
