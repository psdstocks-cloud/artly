<?php
/**
 * Template Name: My Points
 * Description: Shows the current user wallet points and recent activity.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// If the user is not logged in, send them to the login page and then back here.
if ( ! is_user_logged_in() ) {
    $redirect_to = get_permalink();
    wp_safe_redirect( wp_login_url( $redirect_to ) );
    exit;
}

get_header();

// Prepare data
$user_id       = get_current_user_id();
$has_gateway   = function_exists( 'nehtw_gateway_get_balance' ) && function_exists( 'nehtw_gateway_get_transactions' );
$balance       = 0.0;
$transactions  = array();

if ( $has_gateway ) {
    $balance      = nehtw_gateway_get_balance( $user_id );
    $transactions = nehtw_gateway_get_transactions( $user_id, 10, 0 ); // last 10
}
?>

<main id="primary" class="site-main artly-points-page">
  <div class="artly-points-wrapper">

    <!-- HERO -->
    <section class="points-hero">
      <p class="points-kicker"><?php esc_html_e( 'Wallet', 'artly' ); ?></p>
      <h1><?php esc_html_e( 'My Points', 'artly' ); ?></h1>
      <p class="points-subtitle">
        <?php esc_html_e( 'Track your available points and recent activity across Artly downloads.', 'artly' ); ?>
      </p>
    </section>

    <?php if ( ! $has_gateway ) : ?>

      <section class="points-grid">
        <div class="points-card">
          <div class="points-card-header">
            <span class="points-label"><?php esc_html_e( 'Points unavailable', 'artly' ); ?></span>
          </div>
          <p class="points-text-muted">
            <?php esc_html_e( 'The Nehtw Gateway plugin is not active, so your points balance cannot be displayed right now.', 'artly' ); ?>
          </p>
        </div>
      </section>

    <?php else : ?>

      <!-- GRID: LEFT balance card, RIGHT history card -->
      <section class="points-grid">

        <!-- BALANCE CARD -->
        <div class="points-card points-card--balance">
          <div class="points-card-header">
            <span class="points-label"><?php esc_html_e( 'Available points', 'artly' ); ?></span>
          </div>

          <div class="points-balance">
            <?php echo esc_html( number_format_i18n( $balance, 2 ) ); ?>
          </div>

          <p class="points-text-muted">
            <?php esc_html_e( 'Use your points to download stock files and generate AI images directly from your Artly dashboard.', 'artly' ); ?>
          </p>

          <div class="points-card-actions">
            <a class="points-btn" href="#" data-artly-open-modal="wallet-topup">
              <?php esc_html_e( 'Add points', 'artly' ); ?>
            </a>

            <a class="points-link" href="<?php echo esc_url( home_url( '/my-downloads/' ) ); ?>">
              <?php esc_html_e( 'Go to downloads', 'artly' ); ?>
            </a>
          </div>
        </div>

        <!-- HISTORY CARD -->
        <div class="points-card points-card--history">
          <div class="points-card-header">
            <span class="points-label"><?php esc_html_e( 'Recent activity', 'artly' ); ?></span>
          </div>

          <?php if ( ! empty( $transactions ) ) : ?>
            <ul class="points-history-list">
              <?php foreach ( $transactions as $txn ) :
                  $points       = isset( $txn['points'] ) ? (float) $txn['points'] : 0.0;
                  $is_positive  = $points >= 0;
                  $amount_class = $is_positive ? 'points-amount--plus' : 'points-amount--minus';

                  $meta = array();
                  if ( ! empty( $txn['meta'] ) ) {
                      $decoded = json_decode( $txn['meta'], true );
                      if ( is_array( $decoded ) ) {
                          $meta = $decoded;
                      }
                  }

                  // Prefer meta["note"], fall back to type
                  $raw_label = ! empty( $meta['note'] ) ? $meta['note'] : ( $txn['type'] ?? '' );
                  if ( empty( $raw_label ) ) {
                      $raw_label = __( 'Wallet transaction', 'artly' );
                  }

                  $label = ucwords( str_replace( '_', ' ', $raw_label ) );

                  // Handle date
                  $created_at = ! empty( $txn['created_at'] ) ? $txn['created_at'] : '';
                  $timestamp  = $created_at ? strtotime( $created_at ) : false;
              ?>
                <li class="points-history-row">
                  <div class="points-history-main">
                    <span class="points-history-title">
                      <?php echo esc_html( $label ); ?>
                    </span>
                    <?php if ( $timestamp ) : ?>
                      <span class="points-history-meta">
                        <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) ); ?>
                      </span>
                    <?php endif; ?>
                  </div>

                  <div class="points-history-amount <?php echo esc_attr( $amount_class ); ?>">
                    <?php echo $is_positive ? '+' : ''; ?>
                    <?php echo esc_html( number_format_i18n( $points, 2 ) ); ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else : ?>
            <p class="points-empty">
              <?php esc_html_e( 'No wallet activity yet. Once you top up points or make downloads, your history will appear here.', 'artly' ); ?>
            </p>
          <?php endif; ?>
        </div>

      </section>

    <?php endif; ?>

  </div>
</main>

<?php
get_template_part( 'parts/modal', 'wallet-topup' );
get_footer();
?>
