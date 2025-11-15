<?php
/**
 * My Account Wallet Template
 * Custom wallet page showing balance and transaction history
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

$user_id = get_current_user_id();
$points  = artly_get_wallet_points( $user_id );

// Get wallet history
$history = apply_filters( 'artly_wallet_history', null, $user_id );

// Fallback to Nehtw Gateway transactions if filter not implemented
if ( ! is_array( $history ) && function_exists( 'nehtw_gateway_get_transactions' ) ) {
    $transactions = nehtw_gateway_get_transactions( $user_id, 20, 0 );
    $history      = array();
    foreach ( $transactions as $t ) {
        $points_delta = isset( $t['points'] ) ? (float) $t['points'] : 0.0;
        $type         = isset( $t['type'] ) ? $t['type'] : 'unknown';
        $created_at   = isset( $t['created_at'] ) ? strtotime( $t['created_at'] ) : time();

        // Format type for display
        $type_label = ucfirst( str_replace( array( '_', '-' ), ' ', $type ) );

        // Calculate running balance (simplified - would need proper calculation in production)
        $balance = $points; // This is simplified; proper implementation would calculate running balance

        $history[] = array(
            'type'    => $type_label,
            'delta'   => $points_delta > 0 ? '+' . number_format_i18n( $points_delta, 0 ) : number_format_i18n( $points_delta, 0 ),
            'balance' => number_format_i18n( $balance, 0 ),
            'note'    => isset( $t['meta'] ) && is_string( $t['meta'] ) ? json_decode( $t['meta'], true )['note'] ?? '' : '',
            'ts'      => $created_at,
        );
    }
}
?>

<section class="artly-shell">
    <header class="artly-pagehead">
        <h2><?php esc_html_e( 'My Wallet', 'artly' ); ?></h2>
    </header>

    <div class="card glass kpi">
        <div class="kpi-label"><?php esc_html_e( 'Current Balance', 'artly' ); ?></div>
        <div class="kpi-value"><?php echo esc_html( number_format_i18n( $points ) ); ?> pts</div>
        <div class="card-actions">
            <a class="btn primary" href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>">
                <?php esc_html_e( 'Add Points', 'artly' ); ?>
            </a>
            <a class="btn ghost" href="<?php echo esc_url( home_url( '/pricing/#packages' ) ); ?>">
                <?php esc_html_e( 'View Packages', 'artly' ); ?>
            </a>
        </div>
    </div>

    <?php if ( ! empty( $history ) ) : ?>
        <div class="card glass table">
            <h3><?php esc_html_e( 'Wallet History', 'artly' ); ?></h3>
            <ul class="list">
                <?php foreach ( $history as $h ) : ?>
                    <li class="row">
                        <div class="col"><?php echo esc_html( $h['type'] ?? '' ); ?></div>
                        <div class="col"><?php echo esc_html( $h['delta'] ?? '' ); ?> pts</div>
                        <div class="col muted"><?php echo esc_html( date_i18n( get_option( 'date_format' ), (int) ( $h['ts'] ?? time() ) ) ); ?></div>
                        <div class="col"><?php echo esc_html( $h['note'] ?? '' ); ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</section>

