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

    <div class="stock-order-wallet-enhanced" data-stock-order-wallet>
      <div class="wallet-card-inner">
        <!-- Balance Section -->
        <div class="wallet-balance-section">
          <div class="wallet-balance-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M21 18V19C21 20.1 20.1 21 19 21H5C3.89 21 3 20.1 3 19V5C3 3.9 3.89 3 5 3H19C20.1 3 21 3.9 21 5V6H12C10.89 6 10 6.9 10 8V16C10 17.1 10.89 18 12 18H21ZM12 16H22V8H12V16ZM16 13.5C15.17 13.5 14.5 12.83 14.5 12C14.5 11.17 15.17 10.5 16 10.5C16.83 10.5 17.5 11.17 17.5 12C17.5 12.83 16.83 13.5 16 13.5Z" fill="currentColor"/>
            </svg>
          </div>
          <div class="wallet-balance-content">
            <span class="wallet-label-small"><?php esc_html_e( 'Your Balance', 'artly' ); ?></span>
            <div class="wallet-balance-display">
              <span class="wallet-balance-amount" data-wallet-balance>--</span>
              <span class="wallet-balance-unit"><?php esc_html_e( 'points', 'artly' ); ?></span>
            </div>
          </div>
        </div>

        <!-- Divider -->
        <div class="wallet-divider"></div>

        <!-- Next Billing Section -->
        <div class="wallet-billing-section">
          <div class="wallet-billing-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M9 11H7V13H9V11ZM13 11H11V13H13V11ZM17 11H15V13H17V11ZM19 4H18V2H16V4H8V2H6V4H5C3.89 4 3.01 4.9 3.01 6L3 20C3 21.1 3.89 22 5 22H19C20.1 22 21 21.1 21 20V6C21 4.9 20.1 4 19 4ZM19 20H5V9H19V20Z" fill="currentColor"/>
            </svg>
          </div>
          <div class="wallet-billing-content">
            <span class="wallet-label-small"><?php esc_html_e( 'Next Billing', 'artly' ); ?></span>
            <span class="wallet-billing-date" data-wallet-next-billing>--</span>
          </div>
        </div>

        <!-- Top-up Action -->
        <div class="wallet-action">
          <a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" class="wallet-topup-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M19 13H13V19H11V13H5V11H11V5H13V11H19V13Z" fill="currentColor"/>
            </svg>
            <?php esc_html_e( 'Top Up', 'artly' ); ?>
          </a>
        </div>
      </div>
    </div>

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
          <div class="stock-order-preview-inline" data-stock-order-preview></div>
        </div>

        <div class="stock-order-card" data-stock-order-panel="batch" hidden>
          <label for="stock-order-batch-input" class="stock-order-label">
            <?php esc_html_e( 'Paste up to 5 unique links (one per line). Duplicate links will be ignored.', 'artly' ); ?>
          </label>
          <textarea
            id="stock-order-batch-input"
            class="stock-order-textarea"
            rows="6"
            placeholder="<?php esc_attr_e( "https://www.shutterstock.com/...\nhttps://stock.adobe.com/...", 'artly' ); ?>"
          ></textarea>
          
          <div class="stock-order-batch-preview-container" data-stock-order-batch-preview-container>
            <!-- JS renders preview cards here -->
          </div>

          <div class="stock-order-batch-summary" data-stock-order-batch-summary hidden>
            <div class="batch-summary-stats">
              <span class="batch-stat">
                <span class="batch-stat-label"><?php esc_html_e( 'Selected:', 'artly' ); ?></span>
                <span class="batch-stat-value" data-selected-count>0</span>
                <span class="batch-stat-unit"><?php esc_html_e( 'links', 'artly' ); ?></span>
              </span>
              <span class="batch-stat-divider">•</span>
              <span class="batch-stat">
                <span class="batch-stat-label"><?php esc_html_e( 'Total cost:', 'artly' ); ?></span>
                <span class="batch-stat-value" data-total-cost>0</span>
                <span class="batch-stat-unit"><?php esc_html_e( 'points', 'artly' ); ?></span>
              </span>
            </div>
            <button type="button" class="stock-order-submit-batch" data-stock-order-submit-batch>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9 16.17L4.83 12L3.41 13.41L9 19L21 7L19.59 5.59L9 16.17Z" fill="currentColor"/>
              </svg>
              <?php esc_html_e( 'Order Selected Links', 'artly' ); ?>
            </button>
          </div>
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
          <div class="stock-order-supported-fade" data-stock-order-supported-fade></div>
          <?php if ( count( $sites_config ) > 8 ) : ?>
            <button
              type="button"
              class="stock-order-supported-toggle"
              data-stock-order-supported-toggle
            >
              <?php esc_html_e( 'Show all websites', 'artly' ); ?>
            </button>
          <?php endif; ?>
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

