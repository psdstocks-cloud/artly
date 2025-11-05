<?php
/**
 * Template Name: Subscriptions
 * Description: Shows available subscription plans and the user's current plan.
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

$plans = function_exists( 'nehtw_gateway_get_subscription_plans' )
    ? nehtw_gateway_get_subscription_plans()
    : array();

$current_sub = function_exists( 'nehtw_gateway_get_user_active_subscription' )
    ? nehtw_gateway_get_user_active_subscription( $user_id )
    : null;

?>

<main id="primary" class="site-main artly-subscriptions-page">
  <div class="artly-subscriptions-inner">
    <section class="subs-hero">
      <p class="subs-kicker"><?php esc_html_e( 'Plans', 'artly' ); ?></p>
      <h1 class="subs-title"><?php esc_html_e( 'Subscriptions & auto top-up', 'artly' ); ?></h1>
      <p class="subs-subtitle">
        <?php esc_html_e( 'Pick a plan and keep your wallet topped up automatically with points for AI generation and stock downloads.', 'artly' ); ?>
      </p>
    </section>

    <?php if ( $current_sub ) : ?>
      <section class="subs-status-card">
        <div class="subs-status-label">
          <?php esc_html_e( 'Current plan', 'artly' ); ?>
        </div>
        <div class="subs-status-value">
          <?php echo esc_html( isset( $current_sub['plan_key'] ) ? $current_sub['plan_key'] : '' ); ?>
        </div>
        <div class="subs-status-meta">
          <?php
          $next = isset( $current_sub['next_renewal_at'] ) ? $current_sub['next_renewal_at'] : '';
          if ( $next ) {
              $next_formatted = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $next ) );
              printf(
                  esc_html__( 'Next renewal: %s', 'artly' ),
                  esc_html( $next_formatted )
              );
          }
          ?>
        </div>
      </section>
    <?php endif; ?>

    <section class="subs-grid">
      <?php if ( ! empty( $plans ) ) : ?>
        <?php foreach ( $plans as $plan ) : ?>
          <?php
          $key        = isset( $plan['key'] ) ? $plan['key'] : '';
          $name       = isset( $plan['name'] ) ? $plan['name'] : '';
          $points     = isset( $plan['points'] ) ? (float) $plan['points'] : 0.0;
          $price      = isset( $plan['price_label'] ) ? $plan['price_label'] : '';
          $desc       = isset( $plan['description'] ) ? $plan['description'] : '';
          $highlight  = ! empty( $plan['highlight'] );
          $card_class = $highlight ? 'subs-card subs-card--highlight' : 'subs-card';
          $product_id = isset( $plan['product_id'] ) ? (int) $plan['product_id'] : 0;
          $product_url = $product_id > 0 ? get_permalink( $product_id ) : '';
          ?>
          <article class="<?php echo esc_attr( $card_class ); ?>">
            <h2 class="subs-plan-name"><?php echo esc_html( $name ); ?></h2>
            <?php if ( $price ) : ?>
              <div class="subs-plan-price">
                <?php echo esc_html( $price ); ?>
              </div>
            <?php endif; ?>
            <div class="subs-plan-points">
              <?php
              printf(
                  esc_html__( '%.0f points / month', 'artly' ),
                  $points
              );
              ?>
            </div>
            <?php if ( $desc ) : ?>
              <p class="subs-plan-desc">
                <?php echo esc_html( $desc ); ?>
              </p>
            <?php endif; ?>
            <div class="subs-plan-meta">
              <?php esc_html_e( 'Automatic wallet top-up with points each billing cycle.', 'artly' ); ?>
            </div>
            <?php if ( $product_url ) : ?>
              <a class="subs-plan-btn" href="<?php echo esc_url( $product_url ); ?>">
                <?php esc_html_e( 'Subscribe', 'artly' ); ?>
              </a>
            <?php else : ?>
              <span class="subs-plan-meta">
                <?php esc_html_e( 'Plan not available yet. Please contact support.', 'artly' ); ?>
              </span>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      <?php else : ?>
        <p>
          <?php esc_html_e( 'No subscription plans defined yet. Please contact the site admin.', 'artly' ); ?>
        </p>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php
get_footer();

