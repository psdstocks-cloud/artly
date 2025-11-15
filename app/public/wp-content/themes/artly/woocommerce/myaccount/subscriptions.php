<?php
/**
 * My Account Subscriptions Template
 * Display active subscriptions
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
$subs    = artly_get_subscriptions( $user_id );
?>

<section class="artly-shell">
    <header class="artly-pagehead">
        <h2><?php esc_html_e( 'Subscriptions', 'artly' ); ?></h2>
    </header>

    <?php if ( empty( $subs ) ) : ?>
        <p class="muted"><?php esc_html_e( 'No active subscriptions.', 'artly' ); ?></p>
    <?php else : ?>
        <div class="grid">
            <?php foreach ( $subs as $s ) : ?>
                <div class="card glass plan">
                    <div class="plan-top">
                        <h3><?php echo esc_html( $s['name'] ?? __( 'Subscription', 'artly' ) ); ?></h3>
                        <span class="badge <?php echo esc_attr( $s['status'] ?? 'active' ); ?>">
                            <?php echo esc_html( ucfirst( $s['status'] ?? 'active' ) ); ?>
                        </span>
                    </div>
                    <div class="plan-body">
                        <div class="row">
                            <?php esc_html_e( 'Renews at:', 'artly' ); ?>
                            <strong>
                                <?php
                                $renews_at = $s['renews_at'] ?? date( 'Y-m-d', strtotime( '+1 month' ) );
                                echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $renews_at ) ) );
                                ?>
                            </strong>
                        </div>
                        <div class="row">
                            <?php esc_html_e( 'Plan:', 'artly' ); ?>
                            <strong><?php echo esc_html( $s['plan'] ?? '' ); ?></strong>
                        </div>
                        <div class="row">
                            <?php esc_html_e( 'Amount:', 'artly' ); ?>
                            <strong>
                                <?php
                                echo esc_html( number_format_i18n( $s['amount'] ?? 0, 2 ) . ' ' . ( $s['currency'] ?? 'EGP' ) );
                                ?>
                            </strong>
                        </div>
                    </div>
                    <div class="card-actions">
                        <a class="btn ghost" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">
                            <?php esc_html_e( 'Change plan', 'artly' ); ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

