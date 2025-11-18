<?php
/**
 * Template Name: Artly – My AI
 * Description: AI history and library for the current user.
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
?>

<main id="primary" class="site-main artly-my-ai-page">
  <div class="artly-dashboard-inner">

    <section class="artly-ai-top">
      <div class="artly-ai-top-text">
        <p class="artly-ai-kicker"><?php esc_html_e( 'Library', 'artly' ); ?></p>
        <h1 class="artly-ai-title"><?php esc_html_e( 'My AI Creations', 'artly' ); ?></h1>
        <p class="artly-ai-subtitle"><?php esc_html_e( 'Browse all the images you’ve generated, varied, and upscaled.', 'artly' ); ?></p>
      </div>
    </section>

    <section class="artly-card artly-ai-filters" aria-label="<?php esc_attr_e( 'Filter AI history', 'artly' ); ?>">
      <div class="artly-ai-filter-group" role="tablist">
        <button type="button" class="artly-ai-filter is-active" data-filter="all" role="tab" aria-selected="true"><?php esc_html_e( 'All', 'artly' ); ?></button>
        <button type="button" class="artly-ai-filter" data-filter="imagine" role="tab" aria-selected="false"><?php esc_html_e( 'Generated', 'artly' ); ?></button>
        <button type="button" class="artly-ai-filter" data-filter="vary" role="tab" aria-selected="false"><?php esc_html_e( 'Variations', 'artly' ); ?></button>
        <button type="button" class="artly-ai-filter" data-filter="upscale" role="tab" aria-selected="false"><?php esc_html_e( 'Upscaled', 'artly' ); ?></button>
      </div>
    </section>

    <section class="artly-ai-history" aria-live="polite">
      <div id="artly-ai-history-list" class="artly-ai-history-grid"></div>

      <div class="artly-ai-pagination" id="artly-ai-history-pagination">
        <button class="artly-btn artly-btn-ghost artly-ai-page-btn artly-ai-page-prev" type="button" data-direction="prev" disabled>
          <?php esc_html_e( 'Previous', 'artly' ); ?>
        </button>
        <div class="artly-ai-page-info" id="artly-ai-page-info"></div>
        <button class="artly-btn artly-btn-ghost artly-ai-page-btn artly-ai-page-next" type="button" data-direction="next" disabled>
          <?php esc_html_e( 'Next', 'artly' ); ?>
        </button>
      </div>
    </section>

  </div>
</main>

<?php
get_footer();
