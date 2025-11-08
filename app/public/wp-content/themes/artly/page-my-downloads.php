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
        <li class="downloads-item">
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
                        <button
                            class="downloads-btn-primary artly-download-button"
                            type="button"
                            data-download-kind="<?php echo esc_attr( $kind ); ?>"
                            data-download-id="<?php echo esc_attr( $identifier ); ?>"
                            <?php if ( 'stock' === $kind && $history_id > 0 ) : ?>
                                data-history-id="<?php echo esc_attr( $history_id ); ?>"
                            <?php endif; ?>
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

<main id="primary" class="site-main artly-downloads-page">
  <div class="artly-downloads-inner">

    <section class="downloads-hero">
      <p class="downloads-kicker"><?php esc_html_e( 'Library', 'artly' ); ?></p>
      <h1 class="downloads-title"><?php esc_html_e( 'My downloads', 'artly' ); ?></h1>
      <p class="downloads-subtitle">
        <?php esc_html_e( 'Browse your AI images and stock downloads in one place and re-download files anytime.', 'artly' ); ?>
      </p>
    </section>

    <section class="downloads-tabs" role="tablist">
      <button class="downloads-tab is-active" type="button" data-downloads-tab="all">
        <?php esc_html_e( 'All', 'artly' ); ?>
      </button>
      <button class="downloads-tab" type="button" data-downloads-tab="stock">
        <?php esc_html_e( 'Stock', 'artly' ); ?>
      </button>
      <button class="downloads-tab" type="button" data-downloads-tab="ai">
        <?php esc_html_e( 'AI images', 'artly' ); ?>
      </button>
    </section>

    <section class="downloads-section" data-downloads-list="all">
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

    <section class="downloads-section" data-downloads-list="stock" style="display: none;">
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

    <section class="downloads-section" data-downloads-list="ai" style="display: none;">
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