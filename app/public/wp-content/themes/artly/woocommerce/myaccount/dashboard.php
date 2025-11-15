<?php
/**
 * My Account Dashboard Template
 * Custom Artly-branded SaaS dashboard
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

$current_user = wp_get_current_user();
$points       = artly_get_wallet_points( $current_user->ID );
$downloads    = artly_get_recent_downloads( $current_user->ID, 5 );
?>

<section class="artly-shell">
    <header class="artly-pagehead">
        <h1><?php esc_html_e( 'My account', 'artly' ); ?></h1>
        <p class="muted">
            <?php
            printf(
                esc_html__( 'Hello %s', 'artly' ),
                '<strong>' . esc_html( $current_user->display_name ?: $current_user->user_email ) . '</strong>'
            );
            ?>
        </p>
    </header>

    <div class="grid">
        <!-- Wallet card -->
        <div class="card glass kpi">
            <div class="card-head">
                <span class="kpi-label"><?php esc_html_e( 'Wallet Balance', 'artly' ); ?></span>
                <span class="kpi-value"><?php echo esc_html( number_format_i18n( $points ) ); ?> pts</span>
            </div>
            <div class="card-actions">
                <a class="btn primary" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">
                    <?php esc_html_e( 'Add Points', 'artly' ); ?>
                </a>
                <a class="btn ghost" href="<?php echo esc_url( wc_get_account_endpoint_url( 'wallet' ) ); ?>">
                    <?php esc_html_e( 'View Wallet', 'artly' ); ?>
                </a>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="card glass qa">
            <h3><?php esc_html_e( 'Quick actions', 'artly' ); ?></h3>
            <div class="qa-grid">
                <a class="pill" href="<?php echo esc_url( home_url( '/app/stock/' ) ); ?>">
                    <?php esc_html_e( 'Stock Downloader', 'artly' ); ?>
                </a>
                <a class="pill" href="<?php echo esc_url( home_url( '/app/ai/' ) ); ?>">
                    <?php esc_html_e( 'Generate Image (AI)', 'artly' ); ?>
                </a>
                <a class="pill" href="<?php echo esc_url( wc_get_account_endpoint_url( 'downloads' ) ); ?>">
                    <?php esc_html_e( 'My Downloads', 'artly' ); ?>
                </a>
            </div>
        </div>

        <!-- Recent downloads -->
        <div class="card glass table">
            <div class="card-head">
                <h3><?php esc_html_e( 'Recent downloads', 'artly' ); ?></h3>
                <a class="link" href="<?php echo esc_url( wc_get_account_endpoint_url( 'downloads' ) ); ?>">
                    <?php esc_html_e( 'See all', 'artly' ); ?>
                </a>
            </div>
            <?php if ( empty( $downloads ) ) : ?>
                <p class="muted"><?php esc_html_e( 'No downloads yet.', 'artly' ); ?></p>
            <?php else : ?>
                <ul class="list">
                    <?php foreach ( $downloads as $i ) : ?>
                        <li class="row">
                            <?php if ( ! empty( $i['thumb'] ) ) : ?>
                                <img class="thumb" src="<?php echo esc_url( $i['thumb'] ); ?>" alt="" />
                            <?php endif; ?>
                            <div class="col">
                                <div class="title"><?php echo esc_html( $i['title'] ?: __( 'Untitled', 'artly' ) ); ?></div>
                                <div class="meta">
                                    <?php echo esc_html( date_i18n( get_option( 'date_format' ), (int) $i['created_at'] ) ); ?>
                                </div>
                            </div>
                            <?php if ( ! empty( $i['url'] ) ) : ?>
                                <a class="btn small" href="<?php echo esc_url( $i['url'] ); ?>" target="_blank" rel="noopener">
                                    <?php esc_html_e( 'Download', 'artly' ); ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</section>

