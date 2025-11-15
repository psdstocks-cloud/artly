<?php
/**
 * My Account Downloads Template
 * Unified downloads page for stock + AI outputs
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
$items   = artly_get_recent_downloads( $user_id, 50 );
?>

<section class="artly-shell">
    <header class="artly-pagehead">
        <h2><?php esc_html_e( 'Downloads', 'artly' ); ?></h2>
    </header>

    <?php if ( empty( $items ) ) : ?>
        <p class="muted"><?php esc_html_e( 'No downloads yet.', 'artly' ); ?></p>
    <?php else : ?>
        <div class="masonry">
            <?php foreach ( $items as $i ) : ?>
                <article class="file-card glass">
                    <?php if ( ! empty( $i['thumb'] ) ) : ?>
                        <img src="<?php echo esc_url( $i['thumb'] ); ?>" class="file-thumb" alt="<?php echo esc_attr( $i['title'] ?? '' ); ?>">
                    <?php endif; ?>
                    <h4 class="file-title"><?php echo esc_html( $i['title'] ?: __( 'Untitled', 'artly' ) ); ?></h4>
                    <div class="file-meta muted">
                        <?php echo esc_html( date_i18n( get_option( 'date_format' ), (int) ( $i['created_at'] ?? time() ) ) ); ?>
                        <?php if ( ! empty( $i['size'] ) ) : ?>
                            <?php echo ' • ' . esc_html( $i['size'] ); ?>
                        <?php endif; ?>
                    </div>
                    <?php if ( ! empty( $i['url'] ) ) : ?>
                        <a class="btn small" href="<?php echo esc_url( $i['url'] ); ?>" target="_blank" rel="noopener">
                            <?php esc_html_e( 'Download', 'artly' ); ?>
                        </a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

