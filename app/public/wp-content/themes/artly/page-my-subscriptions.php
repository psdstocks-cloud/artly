<?php
/**
 * Template Name: My Subscriptions
 * Description: Manage your active subscription, view history, and cancel if needed.
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

$user_id = get_current_user_id();

// Get active subscription
$active_subscription = function_exists( 'nehtw_gateway_get_user_active_subscription' )
    ? nehtw_gateway_get_user_active_subscription( $user_id )
    : null;

// Get all subscriptions (for history)
$all_subscriptions = function_exists( 'nehtw_gateway_get_user_subscriptions' )
    ? nehtw_gateway_get_user_subscriptions( $user_id )
    : array();

// Get plan details if subscription exists
$plan_details = null;
if ( $active_subscription && function_exists( 'nehtw_gateway_get_subscription_plans' ) ) {
    $plans = nehtw_gateway_get_subscription_plans();
    $plan_key = isset( $active_subscription['plan_key'] ) ? $active_subscription['plan_key'] : '';
    foreach ( $plans as $plan ) {
        if ( isset( $plan['key'] ) && $plan['key'] === $plan_key ) {
            $plan_details = $plan;
            break;
        }
    }
}

// Format dates
$next_renewal = null;
if ( $active_subscription && isset( $active_subscription['next_renewal_at'] ) ) {
    $next_renewal_ts = strtotime( $active_subscription['next_renewal_at'] );
    if ( $next_renewal_ts ) {
        $next_renewal = $next_renewal_ts;
    }
}

$created_at = null;
if ( $active_subscription && isset( $active_subscription['created_at'] ) ) {
    $created_ts = strtotime( $active_subscription['created_at'] );
    if ( $created_ts ) {
        $created_at = $created_ts;
    }
}

?>

<main id="primary" class="site-main artly-my-subscriptions-page">
    <div class="artly-my-subscriptions-inner">
        <section class="subscriptions-hero">
            <p class="subscriptions-kicker"><?php esc_html_e( 'Account', 'artly' ); ?></p>
            <h1 class="subscriptions-title"><?php esc_html_e( 'My Subscriptions', 'artly' ); ?></h1>
            <p class="subscriptions-subtitle">
                <?php esc_html_e( 'Manage your subscription, view renewal dates, and track your usage.', 'artly' ); ?>
            </p>
        </section>

        <?php if ( $active_subscription ) : ?>
            <!-- Active Subscription Card -->
            <section class="subscription-active-card">
                <div class="subscription-card-header">
                    <div>
                        <h2 class="subscription-plan-name">
                            <?php
                            if ( $plan_details && isset( $plan_details['name'] ) ) {
                                echo esc_html( $plan_details['name'] . ' Plan' );
                            } else {
                                echo esc_html( isset( $active_subscription['plan_key'] ) ? ucwords( str_replace( array( '-', '_' ), ' ', $active_subscription['plan_key'] ) ) . ' Plan' : __( 'Active Subscription', 'artly' ) );
                            }
                            ?>
                        </h2>
                        <p class="subscription-plan-points">
                            <?php
                            $points = isset( $active_subscription['points_per_interval'] ) ? floatval( $active_subscription['points_per_interval'] ) : 0.0;
                            printf(
                                esc_html__( '%.0f points per %s', 'artly' ),
                                $points,
                                esc_html( isset( $active_subscription['interval'] ) ? $active_subscription['interval'] : 'month' )
                            );
                            ?>
                        </p>
                    </div>
                    <div class="subscription-status-badge subscription-status-active">
                        <?php esc_html_e( 'Active', 'artly' ); ?>
                    </div>
                </div>

                <div class="subscription-details-grid">
                    <div class="subscription-detail-item">
                        <dt><?php esc_html_e( 'Next Renewal', 'artly' ); ?></dt>
                        <dd>
                            <?php if ( $next_renewal ) : ?>
                                <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_renewal ) ); ?>
                                <?php
                                $days_until = floor( ( $next_renewal - time() ) / DAY_IN_SECONDS );
                                if ( $days_until > 0 ) {
                                    printf( ' <span class="subscription-days-until">(%d %s)</span>', $days_until, _n( 'day', 'days', $days_until, 'artly' ) );
                                }
                                ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Not scheduled', 'artly' ); ?>
                            <?php endif; ?>
                        </dd>
                    </div>

                    <div class="subscription-detail-item">
                        <dt><?php esc_html_e( 'Started', 'artly' ); ?></dt>
                        <dd>
                            <?php if ( $created_at ) : ?>
                                <?php echo esc_html( date_i18n( get_option( 'date_format' ), $created_at ) ); ?>
                            <?php else : ?>
                                <?php esc_html_e( 'Unknown', 'artly' ); ?>
                            <?php endif; ?>
                        </dd>
                    </div>

                    <div class="subscription-detail-item">
                        <dt><?php esc_html_e( 'Billing Cycle', 'artly' ); ?></dt>
                        <dd>
                            <?php
                            $interval = isset( $active_subscription['interval'] ) ? $active_subscription['interval'] : 'month';
                            echo esc_html( ucfirst( $interval ) . 'ly' );
                            ?>
                        </dd>
                    </div>

                    <?php if ( $plan_details && isset( $plan_details['price_label'] ) ) : ?>
                        <div class="subscription-detail-item">
                            <dt><?php esc_html_e( 'Price', 'artly' ); ?></dt>
                            <dd><?php echo esc_html( $plan_details['price_label'] ); ?></dd>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="subscription-actions">
                    <button 
                        type="button" 
                        class="subscription-btn-cancel" 
                        data-subscription-id="<?php echo esc_attr( $active_subscription['id'] ); ?>"
                        data-action="cancel"
                    >
                        <?php esc_html_e( 'Cancel Subscription', 'artly' ); ?>
                    </button>
                    <a 
                        href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" 
                        class="subscription-btn-change"
                    >
                        <?php esc_html_e( 'Change Plan', 'artly' ); ?>
                    </a>
                </div>
            </section>
        <?php else : ?>
            <!-- No Active Subscription -->
            <section class="subscription-empty-state">
                <div class="subscription-empty-icon">📦</div>
                <h2><?php esc_html_e( 'No Active Subscription', 'artly' ); ?></h2>
                <p><?php esc_html_e( 'You don\'t have an active subscription. Subscribe to a plan to get automatic point top-ups each month.', 'artly' ); ?></p>
                <a href="<?php echo esc_url( home_url( '/pricing/' ) ); ?>" class="subscription-btn-primary">
                    <?php esc_html_e( 'View Plans', 'artly' ); ?>
                </a>
            </section>
        <?php endif; ?>

        <!-- Subscription History -->
        <?php if ( ! empty( $all_subscriptions ) ) : ?>
            <section class="subscription-history">
                <h2 class="subscription-section-title"><?php esc_html_e( 'Subscription History', 'artly' ); ?></h2>
                <ul class="subscription-history-list">
                    <?php foreach ( $all_subscriptions as $sub ) : ?>
                        <?php
                        $sub_plan_key = isset( $sub['plan_key'] ) ? $sub['plan_key'] : '';
                        $sub_plan_name = $sub_plan_key;
                        if ( function_exists( 'nehtw_gateway_get_subscription_plans' ) ) {
                            $plans = nehtw_gateway_get_subscription_plans();
                            foreach ( $plans as $plan ) {
                                if ( isset( $plan['key'] ) && $plan['key'] === $sub_plan_key ) {
                                    $sub_plan_name = isset( $plan['name'] ) ? $plan['name'] : $sub_plan_key;
                                    break;
                                }
                            }
                        }
                        $sub_status = isset( $sub['status'] ) ? $sub['status'] : '';
                        $sub_points = isset( $sub['points_per_interval'] ) ? floatval( $sub['points_per_interval'] ) : 0.0;
                        $sub_created = isset( $sub['created_at'] ) ? strtotime( $sub['created_at'] ) : 0;
                        ?>
                        <li class="subscription-history-item">
                            <div class="subscription-history-main">
                                <h3 class="subscription-history-plan"><?php echo esc_html( $sub_plan_name ); ?></h3>
                                <p class="subscription-history-details">
                                    <?php
                                    printf(
                                        esc_html__( '%.0f points / %s', 'artly' ),
                                        $sub_points,
                                        esc_html( isset( $sub['interval'] ) ? $sub['interval'] : 'month' )
                                    );
                                    ?>
                                </p>
                                <?php if ( $sub_created > 0 ) : ?>
                                    <p class="subscription-history-date">
                                        <?php echo esc_html( date_i18n( get_option( 'date_format' ), $sub_created ) ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="subscription-history-status">
                                <span class="subscription-status-badge subscription-status-<?php echo esc_attr( strtolower( $sub_status ) ); ?>">
                                    <?php echo esc_html( ucfirst( $sub_status ) ); ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php
get_footer();

