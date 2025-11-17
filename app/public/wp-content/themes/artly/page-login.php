<?php
/**
 * Template Name: Artly Login
 * Description: Custom glassmorphism login page.
 */

defined( 'ABSPATH' ) || exit;

// Process login form submission (non-AJAX fallback)
if ( isset( $_POST['artly_login_submission'] ) && isset( $_POST['username'] ) && isset( $_POST['password'] ) ) {
    $username = sanitize_user( $_POST['username'] );
    $password = $_POST['password'];
    $remember = isset( $_POST['rememberme'] );
    $redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
    
    if ( empty( $redirect_to ) ) {
        $redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
    }
    
    if ( empty( $redirect_to ) ) {
        $redirect_to = home_url( '/dashboard/' );
    }
    
    $creds = array(
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => $remember
    );
    
    $user = wp_signon( $creds, false );
    
    if ( ! is_wp_error( $user ) ) {
        wp_safe_redirect( $redirect_to );
        exit;
    } else {
        // Redirect back to login page with error
        $error_code = $user->get_error_code();
        $error_message = $user->get_error_message();
        
        // Make error messages more user-friendly
        if ( strpos( $error_message, 'incorrect password' ) !== false || strpos( $error_message, 'Invalid' ) !== false ) {
            $error_message = 'Invalid username or password.';
        }
        
        $login_url = home_url( '/login/' );
        if ( ! empty( $_GET['redirect_to'] ) ) {
            $login_url = add_query_arg( 'redirect_to', urlencode( $_GET['redirect_to'] ), $login_url );
        }
        $login_url = add_query_arg( 'login', 'failed', $login_url );
        wp_safe_redirect( $login_url );
        exit;
    }
}

if ( is_user_logged_in() ) {
    $redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
    if ( ! $redirect_to ) {
        $redirect_to = home_url( '/dashboard/' );
    }
    wp_safe_redirect( $redirect_to );
    exit;
}

get_header();

do_action( 'woocommerce_before_customer_login_form' );

// Get error message from URL parameter or WooCommerce notices
$error_message = '';
if ( isset( $_GET['login'] ) && $_GET['login'] === 'failed' ) {
    $error_message = 'Invalid username or password. Please try again.';
} else {
    $error_message = function_exists( 'artly_login_get_notice_message' ) ? artly_login_get_notice_message() : '';
}

$username_value = isset( $_POST['username'] ) ? sanitize_text_field( wp_unslash( $_POST['username'] ) ) : '';
$remember_checked = ! empty( $_POST['rememberme'] );
$account_page    = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
?>
<main id="primary" class="artly-login" aria-labelledby="loginHeading">
  <div class="artly-login__glow" aria-hidden="true"></div>
  <div class="artly-login__wrap">
    <section class="artly-login__left" aria-hidden="true">
      <div class="artly-login__intro">
        <p class="eyebrow"><?php echo esc_html__( 'CREATIVE WALLET, SINGLE PLACE', 'artly' ); ?></p>
        <h1 id="loginHeading"><?php echo esc_html__( 'Welcome back', 'artly' ); ?></h1>
        <ul class="benefits" role="list">
          <li><strong>01</strong> <?php echo esc_html__( 'Access your download history', 'artly' ); ?></li>
          <li><strong>02</strong> <?php echo esc_html__( 'Manage your wallet & points', 'artly' ); ?></li>
          <li><strong>03</strong> <?php echo esc_html__( 'Re-download past orders for free', 'artly' ); ?></li>
        </ul>
      </div>
    </section>

    <section class="artly-login__card" role="region" aria-label="<?php echo esc_attr__( 'Sign in form', 'artly' ); ?>">
      <header class="card__hd">
        <p class="muted"><?php echo esc_html__( 'ALREADY HAVE AN ACCOUNT?', 'artly' ); ?></p>
        <h2><?php echo esc_html__( 'Sign in to continue', 'artly' ); ?></h2>
      </header>

      <form class="artly-form artly-login-form" method="post" action="<?php echo esc_url( home_url( '/login/' ) ); ?>" novalidate>
        <?php wp_nonce_field( 'artly_login' ); ?>
        <?php if ( function_exists( 'wp_nonce_field' ) ) : ?>
          <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
        <?php endif; ?>
        <input type="hidden" name="artly_login_submission" value="1" />
        <input type="text" name="artly_login_hp" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true" />
        <?php
        $redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : '';
        if ( $redirect_to ) :
        ?>
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
        <?php endif; ?>

        <?php do_action( 'woocommerce_login_form_start' ); ?>

        <div class="field">
          <label for="username"><?php echo esc_html__( 'Username or Email', 'artly' ); ?></label>
          <input id="username" name="username" type="text" autocomplete="username" required aria-required="true" value="<?php echo esc_attr( $username_value ); ?>" />
        </div>

        <div class="field field--password">
          <label for="password"><?php echo esc_html__( 'Password', 'artly' ); ?></label>
          <input id="password" name="password" type="password" autocomplete="current-password" required aria-required="true" />
          <button type="button" class="toggle-pass" aria-label="<?php echo esc_attr__( 'Show password', 'artly' ); ?>" aria-pressed="false">
            <span class="toggle-pass__label"><?php echo esc_html__( 'Show', 'artly' ); ?></span>
          </button>
        </div>

        <div class="row">
          <label class="remember">
            <input type="checkbox" name="rememberme" value="forever" <?php checked( $remember_checked ); ?> />
            <span><?php echo esc_html__( 'Remember Me', 'artly' ); ?></span>
          </label>
          <a class="link" href="<?php echo esc_url( wp_lostpassword_url() ); ?>">
            <?php echo esc_html__( 'Forgot password?', 'artly' ); ?>
          </a>
        </div>

        <?php do_action( 'woocommerce_login_form' ); ?>

        <div class="actions">
          <button id="artlyLoginBtn" type="submit" class="btn btn--primary" disabled aria-disabled="true">
            <span class="btn__label"><?php echo esc_html__( 'Sign in', 'artly' ); ?></span>
            <span class="btn__spinner" aria-hidden="true"></span>
          </button>
        </div>

        <input type="hidden" name="login" value="1" />
        <?php do_action( 'woocommerce_login_form_end' ); ?>
      </form>

      <?php do_action( 'woocommerce_after_customer_login_form' ); ?>

      <div class="separator" role="separator" aria-hidden="true"></div>

      <a class="btn btn--ghost" href="<?php echo esc_url( $account_page . '#register' ); ?>">
        <?php echo esc_html__( 'Create a free account', 'artly' ); ?>
        <span class="arrow" aria-hidden="true">→</span>
      </a>

      <p class="microcopy"><?php echo esc_html__( 'Your credentials are securely encrypted.', 'artly' ); ?></p>
      <div id="artlyLoginErrors" class="errors<?php echo $error_message ? ' is-visible' : ''; ?>" role="status" aria-live="assertive" tabindex="-1">
        <?php echo esc_html( $error_message ); ?>
      </div>
    </section>
  </div>
</main>
<?php get_footer(); ?>
