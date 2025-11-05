<?php
/**
 * Template Name: Stock Ordering
 * Description: Order stock downloads using Artly points (single or batch).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    $redirect_to = get_permalink();
    wp_safe_redirect( wp_login_url( $redirect_to ) );
    exit;
}

get_header();

$user_id = get_current_user_id();

// Get supported sites config from plugin.
$sites_config = function_exists( 'nehtw_gateway_get_stock_sites_config' )
    ? nehtw_gateway_get_stock_sites_config()
    : array();

?>

<main id="primary" class="site-main artly-stock-order-page">
  <div class="artly-stock-order-inner">
    <section class="stock-order-hero">
      <p class="stock-order-kicker"><?php esc_html_e( 'Stock downloads', 'artly' ); ?></p>
      <h1 class="stock-order-title">
        <?php esc_html_e( 'Order stock files with points', 'artly' ); ?>
      </h1>
      <p class="stock-order-subtitle">
        <?php esc_html_e( 'Paste your links from supported stock websites and use your Artly wallet to download them.', 'artly' ); ?>
      </p>
    </section>

    <div class="stock-order-layout">
      <section class="stock-order-main">
        <div class="stock-order-tabs" data-stock-order-tabs>
          <button type="button" class="stock-order-tab is-active" data-mode="single">
            <?php esc_html_e( 'Single link', 'artly' ); ?>
          </button>
          <button type="button" class="stock-order-tab" data-mode="batch">
            <?php esc_html_e( 'Batch (up to 5 links)', 'artly' ); ?>
          </button>
        </div>

        <div class="stock-order-card" data-stock-order-panel="single">
          <label for="stock-order-single-input" class="stock-order-label">
            <?php esc_html_e( 'Paste your stock link', 'artly' ); ?>
          </label>
          <input
            id="stock-order-single-input"
            type="url"
            class="stock-order-input"
            placeholder="<?php esc_attr_e( 'https://www.shutterstock.com/...', 'artly' ); ?>"
          />
          <div class="stock-order-single-meta" data-stock-order-single-meta>
            <!-- JS will fill detected site + points info here -->
          </div>
          <button type="button" class="stock-order-submit" data-stock-order-submit="single">
            <?php esc_html_e( 'Order download', 'artly' ); ?>
          </button>
        </div>

        <div class="stock-order-card" data-stock-order-panel="batch" hidden>
          <label for="stock-order-batch-input" class="stock-order-label">
            <?php esc_html_e( 'Paste up to 5 links (one per line)', 'artly' ); ?>
          </label>
          <textarea
            id="stock-order-batch-input"
            class="stock-order-textarea"
            rows="6"
            placeholder="<?php esc_attr_e( "https://www.shutterstock.com/...\nhttps://stock.adobe.com/...", 'artly' ); ?>"
          ></textarea>
          <button type="button" class="stock-order-scan" data-stock-order-scan>
            <?php esc_html_e( 'Scan links', 'artly' ); ?>
          </button>
          <div class="stock-order-batch-list" data-stock-order-batch-list>
            <!-- JS will render each parsed link with checkbox + site + points -->
          </div>
          <button type="button" class="stock-order-submit" data-stock-order-submit="batch">
            <?php esc_html_e( 'Order selected links', 'artly' ); ?>
          </button>
        </div>

        <div class="stock-order-results" data-stock-order-results>
          <!-- JS will show per-link status messages -->
        </div>
      </section>

      <aside class="stock-order-sidebar">
        <div class="stock-order-card stock-order-supported">
          <h2 class="stock-order-sidebar-title">
            <?php esc_html_e( 'Supported websites', 'artly' ); ?>
          </h2>
          <ul class="stock-order-supported-list">
            <?php foreach ( $sites_config as $site_key => $site ) : ?>
              <?php
              $enabled = isset( $site['enabled'] ) ? (bool) $site['enabled'] : true;
              $points  = isset( $site['points'] ) ? (float) $site['points'] : 0.0;
              $label   = isset( $site['label'] ) ? $site['label'] : $site_key;
              $url     = ! empty( $site['url'] ) ? $site['url'] : '';
              ?>
              <li class="stock-order-supported-item <?php echo $enabled ? '' : 'is-disabled'; ?>">
                <a
                  class="stock-order-supported-link"
                  <?php if ( $url && $enabled ) : ?>
                    href="<?php echo esc_url( $url ); ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                  <?php else : ?>
                    href="#"
                    onclick="return false;"
                  <?php endif; ?>
                >
                  <span class="stock-order-supported-name">
                    <?php echo esc_html( $label ); ?>
                  </span>
                  <span class="stock-order-supported-points">
                    <?php if ( $enabled && $points > 0 ) : ?>
                      <?php
                      printf(
                          esc_html__( '%s point(s)', 'artly' ),
                          esc_html( number_format_i18n( $points, 1 ) )
                      );
                      ?>
                    <?php else : ?>
                      <?php esc_html_e( 'Off', 'artly' ); ?>
                    <?php endif; ?>
                  </span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
          <a class="stock-order-history-link" href="<?php echo esc_url( home_url( '/my-downloads/' ) ); ?>">
            <?php esc_html_e( 'View download history', 'artly' ); ?>
          </a>
        </div>
      </aside>
    </div>
  </div>
</main>

<?php
get_footer();

