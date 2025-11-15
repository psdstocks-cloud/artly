<?php
/**
 * Lost Password Template
 * Custom Artly-branded password recovery page
 *
 * @package Artly
 */

defined( 'ABSPATH' ) || exit;

// If user is already logged in, redirect to dashboard
if ( is_user_logged_in() ) {
    wp_safe_redirect( home_url( '/dashboard/' ) );
    exit;
}

$lost_password_sent = isset( $_GET['reset-link-sent'] );

// Ensure header is loaded (in case this template is loaded standalone)
if ( ! did_action( 'get_header' ) ) {
    get_header();
}
?>

<main id="primary" class="artly-login" aria-labelledby="lostPasswordHeading">
    <div class="artly-login__wrap" <?php language_attributes(); ?>>
        <section class="artly-login__left" aria-hidden="true">
            <div class="artly-login__intro">
                <p class="eyebrow"><?php echo esc_html__( 'SECURE ACCOUNT RECOVERY', 'artly' ); ?></p>
                <h1 id="lostPasswordHeading"><?php echo esc_html__( 'Reset your password', 'artly' ); ?></h1>
                <ul class="benefits" role="list">
                    <li>
                        <strong>01</strong>
                        <?php echo esc_html__( 'Enter your email address', 'artly' ); ?>
                    </li>
                    <li>
                        <strong>02</strong>
                        <?php echo esc_html__( 'Check your inbox for reset link', 'artly' ); ?>
                    </li>
                    <li>
                        <strong>03</strong>
                        <?php echo esc_html__( 'Create a new secure password', 'artly' ); ?>
                    </li>
                </ul>
            </div>
        </section>

        <section class="artly-login__card" role="region" aria-label="<?php echo esc_attr__( 'Password reset form', 'artly' ); ?>">
            <?php if ( $lost_password_sent ) : ?>
                <header class="card__hd">
                    <p class="muted"><?php echo esc_html__( 'CHECK YOUR EMAIL', 'artly' ); ?></p>
                    <h2><?php echo esc_html__( 'Reset link sent', 'artly' ); ?></h2>
                </header>

                <div class="artly-lost-password-success">
                    <p><?php echo esc_html__( 'We\'ve sent a password reset link to your email address.', 'artly' ); ?></p>
                    <p class="muted"><?php echo esc_html__( 'Please check your inbox and click the link to reset your password. The link will expire in 24 hours.', 'artly' ); ?></p>
                </div>

                <div class="actions">
                    <a class="btn btn--primary" href="<?php echo esc_url( home_url( '/login/' ) ); ?>">
                        <?php echo esc_html__( 'Back to Sign in', 'artly' ); ?>
                    </a>
                </div>
            <?php else : ?>
                <header class="card__hd">
                    <p class="muted"><?php echo esc_html__( 'FORGOT YOUR PASSWORD?', 'artly' ); ?></p>
                    <h2><?php echo esc_html__( 'Reset password', 'artly' ); ?></h2>
                </header>

                <p class="artly-lost-password-intro">
                    <?php echo esc_html__( 'Lost your password? Please enter your username or email address. You will receive a link to create a new password via email.', 'artly' ); ?>
                </p>

                <?php do_action( 'woocommerce_before_lost_password_form' ); ?>

                <form class="artly-form" method="post" action="<?php echo esc_url( wc_lostpassword_url() ); ?>">
                    <!-- Honeypot field -->
                    <input type="text" name="company" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true" style="position:absolute;left:-9999px;" />

                    <div class="field">
                        <label for="user_login">
                            <?php echo esc_html__( 'Username or email', 'woocommerce' ); ?>
                            <span class="required" aria-label="<?php echo esc_attr__( 'required', 'artly' ); ?>">*</span>
                        </label>
                        <input
                            id="user_login"
                            name="user_login"
                            type="text"
                            autocomplete="username"
                            required
                            aria-required="true"
                            placeholder="<?php echo esc_attr__( 'Enter your username or email', 'artly' ); ?>"
                        />
                    </div>

                    <?php do_action( 'woocommerce_lostpassword_form' ); ?>

                    <div class="actions">
                        <input type="hidden" name="wc_reset_password" value="true" />
                        <button id="artlyResetBtn" type="submit" class="btn btn--primary" disabled>
                            <span class="btn__label"><?php echo esc_html__( 'Reset password', 'woocommerce' ); ?></span>
                            <span class="btn__spinner" aria-hidden="true"></span>
                        </button>
                    </div>

                    <?php wp_nonce_field( 'lost_password', 'woocommerce-lost-password-nonce' ); ?>
                </form>

                <?php do_action( 'woocommerce_after_lost_password_form' ); ?>

                <div class="separator" role="separator" aria-hidden="true"></div>

                <div class="artly-lost-password-links">
                    <a class="link" href="<?php echo esc_url( home_url( '/login/' ) ); ?>">
                        <?php echo esc_html__( '← Back to Sign in', 'artly' ); ?>
                    </a>
                    <span class="separator-text"><?php echo esc_html__( 'or', 'artly' ); ?></span>
                    <a class="link" href="<?php echo esc_url( home_url( '/signup/' ) ); ?>">
                        <?php echo esc_html__( 'Create a free account', 'artly' ); ?>
                    </a>
                </div>

                <p class="microcopy" aria-live="polite">
                    <?php echo esc_html__( 'Your email address is secure and will only be used to send the reset link.', 'artly' ); ?>
                </p>
                <div id="artlyLostPasswordErrors" class="errors" role="status" aria-live="assertive"></div>

                <?php
                // Display WooCommerce notices if any
                if ( function_exists( 'wc_print_notices' ) ) {
                    wc_print_notices();
                }
                ?>
            <?php endif; ?>
        </section>
    </div>
</main>

<?php
get_footer();

