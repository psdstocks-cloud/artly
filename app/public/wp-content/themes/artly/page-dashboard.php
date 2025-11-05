<?php
/**
 * Template Name: Dashboard
 * Description: Central user dashboard for Artly.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    $redirect_to = get_permalink();
    $login_url   = home_url( '/login/' );
    $login_url   = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $login_url );
    wp_safe_redirect( $login_url );
    exit;
}

get_header();

$user_id      = get_current_user_id();
$current_user = wp_get_current_user();

// Check if onboarding should be shown
$show_onboarding = false;
if ( is_user_logged_in() ) {
    $completed = get_user_meta( get_current_user_id(), 'artly_onboarding_completed', true );
    $show_onboarding = empty( $completed );
}

$display_name = $current_user->display_name;
$email        = $current_user->user_email;

$local_balance = 0.0;
if ( function_exists( 'nehtw_gateway_get_user_points_balance' ) ) {
    $local_balance = nehtw_gateway_get_user_points_balance( $user_id );
}

$nehtw_username = '';
$nehtw_balance  = null;
if ( function_exists( 'nehtw_gateway_api_get_me' ) ) {
    $remote_profile = nehtw_gateway_api_get_me();
    if ( ! is_wp_error( $remote_profile ) && is_array( $remote_profile ) ) {
        if ( isset( $remote_profile['username'] ) ) {
            $nehtw_username = (string) $remote_profile['username'];
        }
        if ( isset( $remote_profile['balance'] ) && is_numeric( $remote_profile['balance'] ) ) {
            $nehtw_balance = floatval( $remote_profile['balance'] );
        }
    }
}

$recent_ai_jobs = array();
if ( function_exists( 'nehtw_gateway_get_table_name' ) ) {
    global $wpdb;
    $table_ai = nehtw_gateway_get_table_name( 'ai_jobs' );
    if ( $table_ai ) {
        $limit = 5;
        $query = $wpdb->prepare(
            "SELECT job_id, prompt, status, created_at FROM {$table_ai} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
            $user_id,
            $limit
        );
        $rows = $wpdb->get_results( $query, ARRAY_A );
        if ( is_array( $rows ) ) {
            foreach ( $rows as $row ) {
                $prompt    = isset( $row['prompt'] ) ? wp_strip_all_tags( (string) $row['prompt'] ) : '';
                $prompt    = $prompt ? wp_trim_words( $prompt, 16, '…' ) : __( 'Untitled prompt', 'artly' );
                $status    = isset( $row['status'] ) ? strtolower( (string) $row['status'] ) : '';
                $status    = $status ? ucwords( str_replace( '_', ' ', $status ) ) : __( 'Pending', 'artly' );
                $created   = isset( $row['created_at'] ) ? (string) $row['created_at'] : '';
                $timestamp = $created ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created ) : '';
                if ( ! $timestamp ) {
                    $timestamp = __( 'Just now', 'artly' );
                }

                $link = home_url( '/app/ai/' );
                if ( ! empty( $row['job_id'] ) ) {
                    $link = add_query_arg( 'job', rawurlencode( (string) $row['job_id'] ), $link );
                }

                $recent_ai_jobs[] = array(
                    'prompt'     => $prompt,
                    'status'     => $status,
                    'created_at' => $timestamp,
                    'link'       => $link,
                );
            }
        }
    }
}

$recent_stock_orders = array();
$stock_orders_source = array();
if ( class_exists( 'Nehtw_Gateway_Stock_Orders' ) ) {
    $stock_orders_source = Nehtw_Gateway_Stock_Orders::get_user_orders( $user_id, 5, 0 );
} elseif ( function_exists( 'nehtw_gateway_get_orders_for_user' ) ) {
    $stock_orders_source = nehtw_gateway_get_orders_for_user( $user_id, 5, 0 );
}

if ( is_array( $stock_orders_source ) ) {
    foreach ( $stock_orders_source as $order ) {
        $title = '';
        if ( isset( $order['file_name'] ) && '' !== $order['file_name'] ) {
            $title = (string) $order['file_name'];
        } elseif ( isset( $order['stock_id'] ) && '' !== $order['stock_id'] && null !== $order['stock_id'] ) {
            $title = (string) $order['stock_id'];
        } else {
            $title = __( 'Stock asset', 'artly' );
        }

        $site        = isset( $order['site'] ) ? (string) $order['site'] : '';
        $site_label  = $site ? ucwords( str_replace( array( '-', '_' ), ' ', $site ) ) : __( 'Stock', 'artly' );
        $status      = isset( $order['status'] ) ? strtolower( (string) $order['status'] ) : '';
        $status_text = $status ? ucwords( str_replace( '_', ' ', $status ) ) : __( 'Pending', 'artly' );
        $created     = isset( $order['created_at'] ) ? (string) $order['created_at'] : '';
        $created_at  = $created ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created ) : '';
        if ( ! $created_at ) {
            $created_at = __( 'Recently updated', 'artly' );
        }

        $link = '';
        if ( isset( $order['download_link'] ) && $order['download_link'] ) {
            $link = esc_url_raw( $order['download_link'] );
        }
        if ( '' === $link ) {
            $link = home_url( '/app/stock/' );
        }

        $recent_stock_orders[] = array(
            'title'      => $title,
            'site'       => $site_label,
            'status'     => $status_text,
            'created_at' => $created_at,
            'link'       => $link,
        );
    }
}
?>

<main id="primary" class="site-main artly-dashboard-page">
  <div class="artly-dashboard-inner">

    <section class="dashboard-hero">
      <p class="dashboard-kicker"><?php esc_html_e( 'Overview', 'artly' ); ?></p>
      <h1 class="dashboard-title"><?php esc_html_e( 'Dashboard', 'artly' ); ?></h1>
      <p class="dashboard-subtitle">
        <?php esc_html_e( 'Track your wallet, AI generations and stock downloads in one place.', 'artly' ); ?>
      </p>
    </section>

    <section class="dashboard-grid">
      <!-- PROFILE CARD -->
      <article class="dashboard-card dashboard-card--profile">
        <header class="dashboard-card-header">
          <span class="dashboard-card-label"><?php esc_html_e( 'Profile', 'artly' ); ?></span>
          <h2 class="dashboard-card-title"><?php esc_html_e( 'Account', 'artly' ); ?></h2>
        </header>
        <div class="dashboard-card-body">
          <div class="dashboard-profile-row">
            <div class="dashboard-avatar">
              <?php echo get_avatar( $user_id, 64 ); ?>
            </div>
            <div>
              <div class="dashboard-profile-name">
                <?php echo esc_html( $display_name ); ?>
              </div>
              <div class="dashboard-profile-meta">
                <?php echo esc_html( $email ); ?>
              </div>
              <?php
              $registered = $current_user && ! empty( $current_user->user_registered )
                  ? strtotime( $current_user->user_registered )
                  : 0;
              ?>
              <?php if ( $registered ) : ?>
                <div class="dashboard-profile-meta">
                  <?php
                  printf(
                      esc_html__( 'Member since %s', 'artly' ),
                      esc_html( date_i18n( get_option( 'date_format' ), $registered ) )
                  );
                  ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </article>

      <!-- WALLET CARD -->
      <article class="dashboard-card dashboard-card--wallet">
        <header class="dashboard-card-header">
          <span class="dashboard-card-label"><?php esc_html_e( 'Wallet', 'artly' ); ?></span>
          <h2 class="dashboard-card-title"><?php esc_html_e( 'Wallet Points', 'artly' ); ?></h2>
        </header>
        <div class="dashboard-card-body">
          <div class="dashboard-wallet-balance">
            <?php echo esc_html( number_format_i18n( $local_balance, 2 ) ); ?>
          </div>
          <div class="dashboard-wallet-compare">
            <span class="dashboard-chip">
              <?php esc_html_e( 'Artly wallet', 'artly' ); ?>
            </span>
          </div>
          <p class="dashboard-card-text">
            <?php esc_html_e( 'Use your points to download stock files and generate AI images from your dashboard.', 'artly' ); ?>
          </p>
          <div class="dashboard-actions">
            <a class="dashboard-btn-primary" href="#" data-artly-open-modal="wallet-topup">
              <?php esc_html_e( 'Add points', 'artly' ); ?>
            </a>
            <a class="dashboard-btn-secondary" href="<?php echo esc_url( home_url( '/my-points/' ) ); ?>">
              <?php esc_html_e( 'View details', 'artly' ); ?>
            </a>
          </div>
        </div>
      </article>

      <!-- AI JOBS CARD -->
      <article class="dashboard-card dashboard-card--ai">
        <header class="dashboard-card-header">
          <span class="dashboard-card-label"><?php esc_html_e( 'AI generation', 'artly' ); ?></span>
          <h2 class="dashboard-card-title"><?php esc_html_e( 'Recent AI Creations', 'artly' ); ?></h2>
        </header>
        <div class="dashboard-card-body">
          <?php if ( ! empty( $recent_ai_jobs ) ) : ?>
            <ul class="dashboard-list">
              <?php foreach ( $recent_ai_jobs as $job ) : ?>
                <li class="dashboard-list-item">
                  <div class="dashboard-list-main">
                    <div class="dashboard-list-title">
                      <?php echo esc_html( $job['prompt'] ); ?>
                    </div>
                    <div class="dashboard-list-meta">
                      <?php echo esc_html( $job['created_at'] ); ?>
                    </div>
                  </div>
                  <div>
                    <span class="dashboard-list-pill">
                      <?php echo esc_html( $job['status'] ); ?>
                    </span>
                    <?php if ( ! empty( $job['link'] ) ) : ?>
                      <a class="dashboard-list-link" href="<?php echo esc_url( $job['link'] ); ?>">
                        <?php esc_html_e( 'Open', 'artly' ); ?>
                      </a>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else : ?>
            <p class="dashboard-card-text">
              <?php esc_html_e( 'No AI jobs yet. Try generating your first image from the AI generator.', 'artly' ); ?>
            </p>
          <?php endif; ?>
          <div class="dashboard-actions">
            <a class="dashboard-btn-secondary" href="<?php echo esc_url( home_url( '/app/ai/' ) ); ?>">
              <?php esc_html_e( 'Open AI generator', 'artly' ); ?>
            </a>
          </div>
        </div>
      </article>

      <!-- STOCK DOWNLOADS CARD -->
      <article class="dashboard-card dashboard-card--stock">
        <header class="dashboard-card-header">
          <span class="dashboard-card-label"><?php esc_html_e( 'Stock downloads', 'artly' ); ?></span>
          <h2 class="dashboard-card-title"><?php esc_html_e( 'Stock Downloads History', 'artly' ); ?></h2>
        </header>
        <div class="dashboard-card-body">
          <?php if ( ! empty( $recent_stock_orders ) ) : ?>
            <ul class="dashboard-list">
              <?php foreach ( $recent_stock_orders as $order ) : ?>
                <li class="dashboard-list-item">
                  <div class="dashboard-list-main">
                    <div class="dashboard-list-title">
                      <?php echo esc_html( $order['title'] ); ?>
                    </div>
                    <div class="dashboard-list-meta">
                      <?php
                      echo esc_html( $order['site'] );
                      echo ' · ';
                      echo esc_html( $order['created_at'] );
                      ?>
                    </div>
                  </div>
                  <div>
                    <span class="dashboard-list-pill">
                      <?php echo esc_html( $order['status'] ); ?>
                    </span>
                    <?php if ( ! empty( $order['link'] ) ) : ?>
                      <a class="dashboard-list-link" href="<?php echo esc_url( $order['link'] ); ?>">
                        <?php esc_html_e( 'View', 'artly' ); ?>
                      </a>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else : ?>
            <p class="dashboard-card-text">
              <?php esc_html_e( 'No downloads yet. Browse stock files and download full-size assets with your points.', 'artly' ); ?>
            </p>
          <?php endif; ?>
          <div class="dashboard-actions">
            <a class="dashboard-btn-primary" href="<?php echo esc_url( home_url( '/stock-order/' ) ); ?>">
              <?php esc_html_e( 'Order stock images', 'artly' ); ?>
            </a>
            <a class="dashboard-btn-secondary" href="<?php echo esc_url( home_url( '/app/stock/' ) ); ?>">
              <?php esc_html_e( 'Go to stock', 'artly' ); ?>
            </a>
          </div>
        </div>
      </article>
    </section>

  </div>
</main>

<?php
get_template_part( 'parts/modal', 'wallet-topup' );
?>

<?php if ( $show_onboarding ) : ?>
  <div class="artly-onboarding" data-artly-onboarding>
    <div class="artly-onboarding-backdrop"></div>
    <div class="artly-onboarding-shell">
      <div class="artly-onboarding-card" data-onboarding-step="1">
        <p class="artly-onboarding-kicker"><?php esc_html_e( 'Welcome to Artly', 'artly' ); ?></p>
        <h2 class="artly-onboarding-title">
          <?php esc_html_e( 'Turn your ideas into AI images', 'artly' ); ?>
        </h2>
        <p class="artly-onboarding-body">
          <?php esc_html_e( 'Use the AI generator to create custom visuals from any prompt. Every generation uses your Artly points.', 'artly' ); ?>
        </p>
        <div class="artly-onboarding-footer">
          <button type="button" class="artly-onboarding-btn-primary" data-onboarding-next>
            <?php esc_html_e( 'Next: Stock downloads', 'artly' ); ?>
          </button>
          <button type="button" class="artly-onboarding-skip" data-onboarding-skip>
            <?php esc_html_e( 'Skip for now', 'artly' ); ?>
          </button>
        </div>
      </div>
      <div class="artly-onboarding-card" data-onboarding-step="2" hidden>
        <p class="artly-onboarding-kicker"><?php esc_html_e( 'Stock & assets', 'artly' ); ?></p>
        <h2 class="artly-onboarding-title">
          <?php esc_html_e( 'Download stock files with points', 'artly' ); ?>
        </h2>
        <p class="artly-onboarding-body">
          <?php esc_html_e( 'Browse stock sites inside Artly and pay using your wallet points instead of entering card details every time.', 'artly' ); ?>
        </p>
        <div class="artly-onboarding-footer">
          <button type="button" class="artly-onboarding-btn-primary" data-onboarding-next>
            <?php esc_html_e( 'Next: Wallet', 'artly' ); ?>
          </button>
          <button type="button" class="artly-onboarding-skip" data-onboarding-skip>
            <?php esc_html_e( 'Skip for now', 'artly' ); ?>
          </button>
        </div>
      </div>
      <div class="artly-onboarding-card" data-onboarding-step="3" hidden>
        <p class="artly-onboarding-kicker"><?php esc_html_e( 'Wallet & points', 'artly' ); ?></p>
        <h2 class="artly-onboarding-title">
          <?php esc_html_e( 'Keep track of your Artly wallet', 'artly' ); ?>
        </h2>
        <p class="artly-onboarding-body">
          <?php esc_html_e( 'Your wallet holds the points you use for AI generations and stock downloads. Top up anytime from the dashboard.', 'artly' ); ?>
        </p>
        <div class="artly-onboarding-footer">
          <button type="button" class="artly-onboarding-btn-primary" data-onboarding-finish>
            <?php esc_html_e( 'Got it, start creating', 'artly' ); ?>
          </button>
          <button type="button" class="artly-onboarding-skip" data-onboarding-skip>
            <?php esc_html_e( 'Skip for now', 'artly' ); ?>
          </button>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php
get_footer();
