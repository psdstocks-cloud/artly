<?php
/**
 * Template Name: Artly Login
 * Description: Branded login screen for Artly accounts.
 */

// If user is already logged in, redirect
if ( is_user_logged_in() ) {
    $redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( $_GET['redirect_to'] ) : home_url( '/my-downloads/' );
    wp_safe_redirect( $redirect_to );
    exit;
}

get_header();
?>

<main class="artly-login-page" role="main">
  <div class="artly-login-wrapper">

    <!-- Left: story / benefits -->
    <section class="artly-login-panel artly-login-panel--story">
      <div class="artly-login-kicker">Creative wallet · Single place</div>
      <h1 class="artly-login-title">Welcome back</h1>
      <p class="artly-login-subtitle">
        Sign in to your Artly account to continue downloading stock assets from one wallet.
      </p>

      <ul class="artly-login-benefits" aria-label="What you can do in Artly">
        <li>
          <span class="artly-login-badge">01</span>
          <div>
            <h3>Access your download history</h3>
            <p>See every asset you&apos;ve ever downloaded across all providers.</p>
          </div>
        </li>
        <li>
          <span class="artly-login-badge">02</span>
          <div>
            <h3>Manage your wallet &amp; points</h3>
            <p>Buy points once, use them on Shutterstock, Adobe Stock, Freepik and more.</p>
          </div>
        </li>
        <li>
          <span class="artly-login-badge">03</span>
          <div>
            <h3>Re-download past orders for free</h3>
            <p>Any stock file you bought through Artly stays available for re-download.</p>
          </div>
        </li>
      </ul>

      <div class="artly-login-footer-note">
        No long-term contracts. Upgrade or downgrade any time from your dashboard.
      </div>
    </section>

    <!-- Right: login form -->
    <section class="artly-login-panel artly-login-panel--form" aria-label="Sign in to Artly">
      <div class="artly-login-form-header">
        <p class="artly-login-chip">Already have an account?</p>
        <h2>Sign in to continue</h2>
        <p class="artly-login-form-subtitle">
          Use the email and password you registered with. We&apos;ll keep you signed in on this device.
        </p>
      </div>

      <?php if ( isset( $_GET['login'] ) && 'failed' === $_GET['login'] ) : ?>
        <div class="artly-login-alert artly-login-alert--error" role="alert">
          <strong>Couldn&apos;t sign you in.</strong>
          <span>Check your email and password, then try again.</span>
        </div>
      <?php endif; ?>

      <form class="artly-login-form" method="post" action="<?php echo esc_url( wp_login_url() ); ?>">
        <?php
        // Use wp_login_form() but with our own markup containers.
        $args = array(
          'echo'           => true,
          'remember'       => true,
          'value_remember' => true,
          'label_username' => __( 'Username or Email', 'artly' ),
          'label_password' => __( 'Password', 'artly' ),
          'label_log_in'   => __( 'Sign in', 'artly' ),
          'id_submit'      => 'artly-login-submit',
          'class_form'     => 'artly-login-form-inner',
          'class_input'    => 'artly-login-input',
          'class_button'   => 'artly-login-button',
        );

        wp_login_form( $args );
        ?>

        <div class="artly-login-form-meta">
          <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="artly-login-link">
            <?php esc_html_e( 'Forgot password?', 'artly' ); ?>
          </a>
        </div>
      </form>

      <div class="artly-login-divider">
        <span>New to Artly?</span>
      </div>

      <a href="<?php echo esc_url( home_url( '/signup/' ) ); ?>" class="artly-login-secondary-btn">
        <span>Create a free account</span>
        <span aria-hidden="true">→</span>
      </a>

      <p class="artly-login-muted">
        By continuing you agree to our
        <a href="<?php echo esc_url( home_url( '/terms/' ) ); ?>">Terms</a>
        and
        <a href="<?php echo esc_url( home_url( '/privacy/' ) ); ?>">Privacy Policy</a>.
      </p>
    </section>

  </div>
</main>

<?php get_footer(); ?>
