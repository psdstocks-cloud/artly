<?php
/**
 * Template Name: My Downloads
 * Description: Shows AI and stock download history for the current user.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    $redirect_to = get_permalink();
    $login_url   = wp_login_url( $redirect_to );
    wp_safe_redirect( $login_url );
    exit;
}

get_header();

$user_id  = get_current_user_id();

// Normalize the current page using WordPress query vars with fallback to GET.
$current_page = get_query_var( 'paged' );
if ( ! $current_page ) {
    $current_page = get_query_var( 'page' ); // For static pages with pagination.
}
if ( ! $current_page && isset( $_GET['p'] ) ) {
    $current_page = (int) $_GET['p'];
}
if ( $current_page < 1 ) {
    $current_page = 1;
}

$per_page = 20;

$history = array(
    'items'        => array(),
    'total_items'  => 0,
    'total'        => 0,
    'per_page'     => $per_page,
    'current_page' => $current_page,
    'total_pages'  => 0,
);

if ( function_exists( 'nehtw_get_user_download_history' ) ) {
    $history = nehtw_get_user_download_history( $user_id, $current_page, $per_page, 'all' );
}

$items       = isset( $history['items'] ) && is_array( $history['items'] ) ? $history['items'] : array();
$total_items = isset( $history['total_items'] ) ? (int) $history['total_items'] : ( isset( $history['total'] ) ? (int) $history['total'] : 0 );
$per_page    = ! empty( $history['per_page'] ) ? (int) $history['per_page'] : $per_page;
$current_page = ! empty( $history['current_page'] ) ? (int) $history['current_page'] : $current_page;
$total_pages  = ! empty( $history['total_pages'] ) ? (int) $history['total_pages'] : ( $per_page > 0 ? (int) max( 1, ceil( $total_items / $per_page ) ) : 0 );

// Compute "Showing X–Y of Z" values.
if ( $total_items > 0 ) {
    $from = ( ( $current_page - 1 ) * $per_page ) + 1;
    $to   = min( $from + $per_page - 1, $total_items );
} else {
    $from = 0;
    $to   = 0;
}

$stock_items = array();
$ai_items    = array();

foreach ( $items as $item ) {
    if ( isset( $item['kind'] ) && 'ai' === $item['kind'] ) {
        $ai_items[] = $item;
    } else {
        $stock_items[] = $item;
    }
}

// Fetch stats for the current user with optional date range filter
$date_from = isset( $_GET['from'] ) ? sanitize_text_field( $_GET['from'] ) : '';
$date_to   = isset( $_GET['to'] ) ? sanitize_text_field( $_GET['to'] ) : '';

$stats = array(
    'total_downloads' => 0,
    'total_points'    => 0.0,
    'providers'       => array(),
);

if ( function_exists( 'nehtw_gateway_get_user_download_stats' ) ) {
    $stats = nehtw_gateway_get_user_download_stats(
        $user_id,
        array(
            'type'      => 'all',
            'date_from' => $date_from,
            'date_to'   => $date_to,
        )
    );
}

// Compute "top provider"
$top_provider_label = '';
$top_provider_count = 0;
if ( ! empty( $stats['providers'] ) ) {
    $first = reset( $stats['providers'] );
    $top_provider_label = $first['label'];
    $top_provider_count = $first['count'];
}

if ( ! function_exists( 'artly_downloads_render_items' ) ) {
    function artly_downloads_render_items( $items, $default_kind = 'stock' ) {
        if ( empty( $items ) ) {
            return;
        }

        foreach ( $items as $item ) {
        $kind       = isset( $item['kind'] ) ? $item['kind'] : $default_kind;
        $title      = isset( $item['title'] ) ? $item['title'] : '';
        $site       = isset( $item['site'] ) ? $item['site'] : '';
        $status     = isset( $item['status'] ) ? $item['status'] : '';
        $points     = isset( $item['points'] ) ? floatval( $item['points'] ) : 0.0;
        $created_at = isset( $item['created_at'] ) ? intval( $item['created_at'] ) : 0;
        $updated_at = isset( $item['updated_at'] ) ? intval( $item['updated_at'] ) : $created_at;
        $thumb      = isset( $item['thumbnail'] ) ? $item['thumbnail'] : '';
        $task_id    = isset( $item['task_id'] ) ? $item['task_id'] : '';
        $job_id     = isset( $item['job_id'] ) ? $item['job_id'] : '';
        $identifier = 'ai' === $kind ? $job_id : $task_id;
        
        // Stock-specific fields
        $history_id = isset( $item['history_id'] ) ? intval( $item['history_id'] ) : 0;
        $provider_label = isset( $item['provider_label'] ) ? $item['provider_label'] : '';
        $remote_id = isset( $item['remote_id'] ) ? $item['remote_id'] : '';
        $stock_url = isset( $item['stock_url'] ) ? $item['stock_url'] : '';
        
        // Use provider_label if available, otherwise format site
        $display_provider = ! empty( $provider_label ) ? $provider_label : ( $site ? ucwords( str_replace( array( '-', '_' ), ' ', $site ) ) : '' );
        
        // Format date/time - use updated_at for stock, created_at for AI
        $display_date = $updated_at > 0 ? $updated_at : $created_at;
        
        // Normalize status for comparison
        $status_raw = isset( $item['status'] ) ? $item['status'] : '';
        $status_normalized = strtolower( trim( $status_raw ) );
        
        // Normalize provider/site for search/filter
        $provider = strtolower( trim( $site ) );
        
        // Prepare data attributes for client-side filtering/sorting
        $created_ts = $created_at > 0 ? $created_at : 0;
        
        // Determine which statuses allow re-download
        $status_can_redownload = in_array(
            $status_normalized,
            array(
                'completed',
                'ready',
                'downloaded',
                'success',
                'complete',
            ),
            true
        );
        
        // Determine which statuses indicate failure/error (no re-download available)
        $status_is_failed = in_array(
            $status_normalized,
            array(
                'failed',
                'error',
                'cancelled',
                'refunded',
                'timeout',
            ),
            true
        );
        
        // Status class for styling
        $status_class = 'downloads-item-status-pill';
        if ( $status ) {
            $status_lower = strtolower( $status );
            if ( in_array( $status_lower, array( 'ready', 'completed', 'complete' ), true ) ) {
                $status_class .= ' downloads-item-status-pill--completed';
            } elseif ( in_array( $status_lower, array( 'processing', 'pending', 'queued' ), true ) ) {
                $status_class .= ' downloads-item-status-pill--processing';
            } elseif ( in_array( $status_lower, array( 'failed', 'error' ), true ) ) {
                $status_class .= ' downloads-item-status-pill--error';
            }
        }
        ?>
        <li class="downloads-item artly-download-item"
            data-download-kind="<?php echo esc_attr( $kind ); ?>"
            data-download-provider="<?php echo esc_attr( $provider ); ?>"
            data-download-status="<?php echo esc_attr( $status_normalized ); ?>"
            data-download-remote-id="<?php echo esc_attr( $remote_id ); ?>"
            data-download-title="<?php echo esc_attr( $title ); ?>"
            data-download-url="<?php echo esc_url( $stock_url ); ?>"
            data-download-points="<?php echo esc_attr( $points ); ?>"
            data-download-date-ts="<?php echo esc_attr( $created_ts ); ?>"
        >
            <?php if ( $thumb ) : ?>
                <div class="downloads-thumb is-loading">
                    <img
                        src="<?php echo esc_url( $thumb ); ?>"
                        alt="<?php echo esc_attr( $title ? $title : __( 'Download preview', 'artly' ) ); ?>"
                        loading="lazy"
                        decoding="async"
                        onload="this.parentElement.classList.remove('is-loading');"
                        onerror="this.parentElement.classList.add('downloads-thumb-placeholder'); this.parentElement.classList.remove('is-loading'); this.style.display='none';"
                    />
                </div>
            <?php else : ?>
                <div class="downloads-thumb downloads-thumb-placeholder"></div>
            <?php endif; ?>

            <div class="downloads-item-main">
                <div class="downloads-item-title">
                    <?php if ( 'stock' === $kind && $remote_id ) : ?>
                        <?php echo esc_html( $remote_id ); ?>
                    <?php else : ?>
                        <?php echo esc_html( $title ); ?>
                    <?php endif; ?>
                </div>
                <div class="downloads-item-meta">
                    <?php if ( $display_provider ) : ?>
                        <?php if ( 'stock' === $kind && $stock_url ) : ?>
                            <a href="<?php echo esc_url( $stock_url ); ?>" target="_blank" rel="noopener" class="downloads-item-kind-pill">
                                <?php echo esc_html( $display_provider ); ?>
                            </a>
                        <?php else : ?>
                            <span class="downloads-item-kind-pill">
                                <?php echo esc_html( 'ai' === $kind ? __( 'AI', 'artly' ) : $display_provider ); ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ( $display_date ) : ?>
                        <span>
                            <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $display_date ) ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="downloads-item-actions">
                <?php if ( $status ) : ?>
                    <div class="<?php echo esc_attr( $status_class ); ?>">
                        <?php echo esc_html( $status ); ?>
                    </div>
                <?php endif; ?>
                <div class="downloads-item-points">
                    <?php printf( esc_html__( '%s pts', 'artly' ), esc_html( number_format_i18n( $points, 2 ) ) ); ?>
                </div>
                <div class="artly-download-actions">
                    <?php if ( $identifier && $status_can_redownload ) : ?>
                        <?php
                        // Build accessible label for re-download button
                        $download_title = '';
                        if ( 'stock' === $kind && $remote_id ) {
                            $download_title = $remote_id;
                        } elseif ( ! empty( $title ) ) {
                            $download_title = $title;
                        } else {
                            $download_title = __( 'this file', 'artly' );
                        }
                        $aria_label = sprintf(
                            /* translators: %s = download title or identifier */
                            __( 'Re-download %s', 'artly' ),
                            $download_title
                        );
                        ?>
                    <button
                            class="downloads-btn-primary artly-download-button"
                        type="button"
                        data-download-kind="<?php echo esc_attr( $kind ); ?>"
                        data-download-id="<?php echo esc_attr( $identifier ); ?>"
                        <?php if ( 'stock' === $kind && $history_id > 0 ) : ?>
                            data-history-id="<?php echo esc_attr( $history_id ); ?>"
                        <?php endif; ?>
                            aria-label="<?php echo esc_attr( $aria_label ); ?>"
                    >
                        <?php esc_html_e( 'Re-download', 'artly' ); ?>
                    </button>
                    <?php elseif ( $status_is_failed ) : ?>
                        <div class="artly-download-status-pill artly-download-status-pill--unavailable">
                            <?php esc_html_e( 'Re-download unavailable', 'artly' ); ?>
                        </div>
                    <?php elseif ( $status_normalized && ! $status_can_redownload && ! $status_is_failed ) : ?>
                        <div class="artly-download-status-pill artly-download-status-pill--intermediate">
                            <?php echo esc_html( ucfirst( $status_normalized ) ); ?>
                        </div>
                <?php endif; ?>
                </div>
            </div>
        </li>
        <?php
        }
    }
}
?>

<main id="primary" class="site-main artly-downloads-page" role="main" aria-label="<?php esc_attr_e( 'Your downloads', 'artly' ); ?>">
  <div class="artly-downloads-inner">

    <!-- Live region for screen reader announcements -->
    <div
      class="downloads-status-region"
      aria-live="polite"
      aria-atomic="true"
      role="status"
      style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0;"
    >
      <!-- JS will update this with status text -->
    </div>

    <section class="downloads-hero">
      <p class="downloads-kicker"><?php esc_html_e( 'Library', 'artly' ); ?></p>
      <h1 class="downloads-title"><?php esc_html_e( 'My downloads', 'artly' ); ?></h1>
      <p class="downloads-subtitle">
        <?php esc_html_e( 'Browse your AI images and stock downloads in one place and re-download files anytime.', 'artly' ); ?>
      </p>
    </section>

    <div class="downloads-summary-card">
      <div class="downloads-summary-header">
        <div class="downloads-summary-title-group">
          <h2 class="downloads-summary-title">
            <?php esc_html_e( 'Downloads Overview', 'artly' ); ?>
          </h2>
          <p class="downloads-summary-subtitle">
            <?php esc_html_e( 'Quick snapshot of your usage and points spent.', 'artly' ); ?>
          </p>
        </div>
        <form class="downloads-summary-range" method="get">
          <?php
          // Preserve existing query args except date filters
          foreach ( $_GET as $key => $value ) {
              if ( in_array( $key, array( 'from', 'to', 'paged' ), true ) ) {
                  continue;
              }
              printf(
                  '<input type="hidden" name="%s" value="%s" />',
                  esc_attr( $key ),
                  esc_attr( $value )
              );
          }
          ?>
          <div class="downloads-range-fields">
            <label class="downloads-range-field">
              <span><?php esc_html_e( 'From', 'artly' ); ?></span>
              <input type="date" name="from" value="<?php echo esc_attr( $date_from ); ?>" />
            </label>
            <label class="downloads-range-field">
              <span><?php esc_html_e( 'To', 'artly' ); ?></span>
              <input type="date" name="to" value="<?php echo esc_attr( $date_to ); ?>" />
            </label>
            <button type="submit" class="downloads-range-apply">
              <?php esc_html_e( 'Apply', 'artly' ); ?>
            </button>
          </div>
        </form>
      </div>
      <div class="downloads-summary-grid">
        <div class="downloads-summary-metric">
          <div class="metric-label"><?php esc_html_e( 'Total downloads', 'artly' ); ?></div>
          <div class="metric-value">
            <?php echo number_format_i18n( (int) $stats['total_downloads'] ); ?>
          </div>
          <div class="metric-caption">
            <?php esc_html_e( 'Across the selected date range', 'artly' ); ?>
          </div>
        </div>
        <div class="downloads-summary-metric">
          <div class="metric-label"><?php esc_html_e( 'Points spent', 'artly' ); ?></div>
          <div class="metric-value metric-value--accent">
            <?php echo number_format_i18n( (float) $stats['total_points'], 1 ); ?>
            <span class="metric-unit"><?php esc_html_e( 'points', 'artly' ); ?></span>
          </div>
          <div class="metric-caption">
            <?php esc_html_e( 'Total points used on downloads', 'artly' ); ?>
          </div>
        </div>
        <div class="downloads-summary-metric">
          <div class="metric-label"><?php esc_html_e( 'Top provider', 'artly' ); ?></div>
          <?php if ( $top_provider_label ) : ?>
            <div class="metric-value">
              <?php echo esc_html( $top_provider_label ); ?>
            </div>
            <div class="metric-caption">
              <?php
              printf(
                  /* translators: %d: downloads count */
                  esc_html__( '%d downloads', 'artly' ),
                  (int) $top_provider_count
              );
              ?>
            </div>
          <?php else : ?>
            <div class="metric-value metric-value--muted">
              <?php esc_html_e( 'No data yet', 'artly' ); ?>
            </div>
            <div class="metric-caption">
              <?php esc_html_e( 'Download a file to see stats.', 'artly' ); ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="downloads-summary-actions">
          <button type="button" class="downloads-export-btn" data-downloads-export>
            <?php esc_html_e( 'Export CSV', 'artly' ); ?>
          </button>
        </div>
      </div>
      <?php if ( ! empty( $stats['providers'] ) ) : ?>
        <div class="downloads-providers-breakdown">
          <div class="providers-header">
            <span class="providers-title"><?php esc_html_e( 'By provider', 'artly' ); ?></span>
            <span class="providers-caption"><?php esc_html_e( 'Share of downloads & points spent', 'artly' ); ?></span>
          </div>
          <div class="providers-list">
            <?php
            // Compute total for relative percentages
            $total_points = max( (float) $stats['total_points'], 1 );
            foreach ( $stats['providers'] as $key => $provider ) :
                $ratio = $provider['points'] > 0 ? ( $provider['points'] / $total_points ) * 100 : 0;
                ?>
                <div class="provider-row">
                  <div class="provider-main">
                    <span class="provider-name"><?php echo esc_html( $provider['label'] ); ?></span>
                    <span class="provider-count">
                      <?php
                      printf(
                          /* translators: 1: downloads count, 2: points */
                          esc_html__( '%1$d downloads · %2$s pts', 'artly' ),
                          (int) $provider['count'],
                          number_format_i18n( (float) $provider['points'], 1 )
                      );
                      ?>
                    </span>
                  </div>
                  <div class="provider-bar">
                    <div class="provider-bar-fill" style="width: <?php echo esc_attr( min( 100, $ratio ) ); ?>%;"></div>
                  </div>
                </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <section class="downloads-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter downloads by type', 'artly' ); ?>">
      <button
        class="downloads-tab is-active"
        type="button"
        data-downloads-tab="all"
        role="tab"
        aria-selected="true"
        aria-controls="downloads-panel-all"
        id="downloads-tab-all"
        tabindex="0"
      >
        <?php esc_html_e( 'All', 'artly' ); ?>
      </button>
      <button
        class="downloads-tab"
        type="button"
        data-downloads-tab="stock"
        role="tab"
        aria-selected="false"
        aria-controls="downloads-panel-stock"
        id="downloads-tab-stock"
        tabindex="-1"
      >
        <?php esc_html_e( 'Stock', 'artly' ); ?>
      </button>
      <button
        class="downloads-tab"
        type="button"
        data-downloads-tab="ai"
        role="tab"
        aria-selected="false"
        aria-controls="downloads-panel-ai"
        id="downloads-tab-ai"
        tabindex="-1"
      >
        <?php esc_html_e( 'AI images', 'artly' ); ?>
      </button>
    </section>

    <div class="artly-downloads-toolbar" data-downloads-toolbar>
      <div class="artly-downloads-toolbar-left">
        <div class="artly-downloads-search">
          <input
            type="text"
            class="artly-downloads-search-input"
            data-downloads-search
            placeholder="<?php esc_attr_e( 'Search by name, provider, ID or link…', 'artly' ); ?>"
          />
        </div>
      </div>
      <div class="artly-downloads-toolbar-right">
        <div class="artly-downloads-filter-group">
          <label class="artly-downloads-filter-label" for="downloads-status-filter">
            <?php esc_html_e( 'Status', 'artly' ); ?>
          </label>
          <select id="downloads-status-filter" class="artly-downloads-select" data-downloads-status-filter>
            <option value=""><?php esc_html_e( 'All statuses', 'artly' ); ?></option>
            <option value="completed"><?php esc_html_e( 'Completed', 'artly' ); ?></option>
            <option value="ready"><?php esc_html_e( 'Ready', 'artly' ); ?></option>
            <option value="processing"><?php esc_html_e( 'Processing', 'artly' ); ?></option>
            <option value="pending"><?php esc_html_e( 'Pending', 'artly' ); ?></option>
            <option value="queued"><?php esc_html_e( 'Queued', 'artly' ); ?></option>
            <option value="failed"><?php esc_html_e( 'Failed', 'artly' ); ?></option>
            <option value="error"><?php esc_html_e( 'Error', 'artly' ); ?></option>
          </select>
        </div>
        <div class="artly-downloads-filter-group">
          <label class="artly-downloads-filter-label" for="downloads-sort">
            <?php esc_html_e( 'Sort', 'artly' ); ?>
          </label>
          <select id="downloads-sort" class="artly-downloads-select" data-downloads-sort>
            <option value="date_desc"><?php esc_html_e( 'Newest first', 'artly' ); ?></option>
            <option value="date_asc"><?php esc_html_e( 'Oldest first', 'artly' ); ?></option>
            <option value="provider_asc"><?php esc_html_e( 'Provider A–Z', 'artly' ); ?></option>
            <option value="provider_desc"><?php esc_html_e( 'Provider Z–A', 'artly' ); ?></option>
            <option value="status_asc"><?php esc_html_e( 'Status A–Z', 'artly' ); ?></option>
            <option value="status_desc"><?php esc_html_e( 'Status Z–A', 'artly' ); ?></option>
            <option value="points_asc"><?php esc_html_e( 'Points low → high', 'artly' ); ?></option>
            <option value="points_desc"><?php esc_html_e( 'Points high → low', 'artly' ); ?></option>
          </select>
        </div>
      </div>
    </div>

    <section
      class="downloads-section"
      data-downloads-list="all"
      id="downloads-panel-all"
      role="tabpanel"
      aria-labelledby="downloads-tab-all"
    >
      <?php if ( ! empty( $items ) ) : ?>
        <ul class="downloads-list">
          <?php artly_downloads_render_items( $items ); ?>
        </ul>
      <?php else : ?>
        <p class="downloads-empty">
          <?php esc_html_e( 'No downloads yet. Start by generating an AI image or downloading a stock file.', 'artly' ); ?>
        </p>
      <?php endif; ?>
    </section>

    <section
      class="downloads-section"
      data-downloads-list="stock"
      id="downloads-panel-stock"
      role="tabpanel"
      aria-labelledby="downloads-tab-stock"
      hidden
    >
      <?php if ( ! empty( $stock_items ) ) : ?>
        <ul class="downloads-list">
          <?php artly_downloads_render_items( $stock_items, 'stock' ); ?>
        </ul>
      <?php else : ?>
        <p class="downloads-empty">
          <?php esc_html_e( 'No stock downloads yet.', 'artly' ); ?>
        </p>
      <?php endif; ?>
    </section>

    <section
      class="downloads-section"
      data-downloads-list="ai"
      id="downloads-panel-ai"
      role="tabpanel"
      aria-labelledby="downloads-tab-ai"
      hidden
    >
      <?php if ( ! empty( $ai_items ) ) : ?>
        <ul class="downloads-list">
          <?php artly_downloads_render_items( $ai_items, 'ai' ); ?>
        </ul>
      <?php else : ?>
        <p class="downloads-empty">
          <?php esc_html_e( 'No AI images downloaded yet.', 'artly' ); ?>
        </p>
      <?php endif; ?>
    </section>

    <?php if ( $total_pages > 1 ) : ?>
      <nav class="artly-pagination" aria-label="<?php esc_attr_e( 'Download history pagination', 'artly' ); ?>">
        <div class="artly-pagination-info">
          <?php
          printf(
              esc_html__( 'Showing %1$d–%2$d of %3$d downloads', 'artly' ),
              (int) $from,
              (int) $to,
              (int) $total_items
          );
          ?>
        </div>
        <div class="artly-pagination-controls">
          <?php
          // Base URL for pagination - use get_permalink() for static pages.
          $base_url = get_permalink();
          $base_url = trailingslashit( $base_url );

          // Determine format based on permalink structure.
          $format = '';
          if ( get_option( 'permalink_structure' ) ) {
              // Pretty permalinks: /my-downloads/page/2/
              $format = 'page/%#%/';
          } else {
              // Plain permalinks: /my-downloads/?paged=2
              $format = '?paged=%#%';
          }

          $paginate_links = paginate_links(
              array(
                  'base'      => $base_url . '%_%',
                  'format'    => $format,
                  'current'   => max( 1, $current_page ),
                  'total'     => max( 1, $total_pages ),
                  'type'      => 'array',
                  'prev_text' => '&larr; ' . esc_html__( 'Previous', 'artly' ),
                  'next_text' => esc_html__( 'Next', 'artly' ) . ' &rarr;',
              )
          );

          if ( ! empty( $paginate_links ) && is_array( $paginate_links ) ) :
              ?>
              <ul class="artly-pagination-list">
                  <?php foreach ( $paginate_links as $link ) : ?>
                      <li class="artly-pagination-item">
                          <?php echo $link; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                      </li>
                  <?php endforeach; ?>
              </ul>
          <?php endif; ?>
        </div>
      </nav>
    <?php endif; ?>

  </div>
</main>

<?php
get_footer();