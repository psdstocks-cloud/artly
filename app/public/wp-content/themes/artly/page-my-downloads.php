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
$page     = isset( $_GET['p'] ) ? max( 1, intval( wp_unslash( $_GET['p'] ) ) ) : 1;
$per_page = 20;

$history = array(
    'items'       => array(),
    'total'       => 0,
    'total_pages' => 0,
);

if ( function_exists( 'nehtw_get_user_download_history' ) ) {
    $history = nehtw_get_user_download_history( $user_id, $page, $per_page, 'all' );
}

$items       = isset( $history['items'] ) && is_array( $history['items'] ) ? $history['items'] : array();
$total       = isset( $history['total'] ) ? (int) $history['total'] : 0;
$total_pages = isset( $history['total_pages'] ) ? (int) $history['total_pages'] : 0;

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
                <div class="downloads-thumb">
                    <img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" />
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
                <?php if ( $identifier ) : ?>
                    <button
                        class="downloads-btn-primary"
                        type="button"
                        data-download-kind="<?php echo esc_attr( $kind ); ?>"
                        data-download-id="<?php echo esc_attr( $identifier ); ?>"
                        <?php if ( 'stock' === $kind && $history_id > 0 ) : ?>
                            data-history-id="<?php echo esc_attr( $history_id ); ?>"
                        <?php endif; ?>
                    >
                        <?php esc_html_e( 'Re-download', 'artly' ); ?>
                    </button>
                <?php endif; ?>
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
      <nav class="downloads-pagination" aria-label="<?php esc_attr_e( 'Downloads pagination', 'artly' ); ?>">
        <?php for ( $i = 1; $i <= $total_pages; $i++ ) : ?>
          <?php
          $url        = add_query_arg( 'p', $i, get_permalink() );
          $is_current = ( $i === $page );
          ?>
          <a class="downloads-page-link<?php echo $is_current ? ' is-current' : ''; ?>" href="<?php echo esc_url( $url ); ?>">
            <?php echo esc_html( $i ); ?>
          </a>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>

  </div>
</main>

<?php
get_footer();
