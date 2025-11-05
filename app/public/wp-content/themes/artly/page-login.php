<?php
/**
 * Template Name: Login
 * Description: User login page with form validation
 */

// Handle form submission
$errors = array();
$success = false;
$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( $_GET['redirect_to'] ) : home_url( '/my-downloads/' );

if ( isset( $_POST['artly_login_submit'] ) && wp_verify_nonce( $_POST['artly_login_nonce'], 'artly_login_action' ) ) {
    
    // Sanitize input
    $username = sanitize_user( $_POST['artly_username'] );
    $password = $_POST['artly_password'];
    $remember = isset( $_POST['artly_remember'] ) ? true : false;
    
    // Validation
    if ( empty( $username ) ) {
        $errors[] = 'Username or email is required.';
    }
    
    if ( empty( $password ) ) {
        $errors[] = 'Password is required.';
    }
    
    // Attempt login if no errors
    if ( empty( $errors ) ) {
        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember
        );
        
        $user = wp_signon( $creds, false );
        
        if ( is_wp_error( $user ) ) {
            $errors[] = $user->get_error_message();
        } else {
            // Success - redirect
            wp_safe_redirect( $redirect_to );
            exit;
        }
    }
}

// If user is already logged in, redirect
if ( is_user_logged_in() ) {
    wp_safe_redirect( $redirect_to );
    exit;
}

get_header();
?>

<main class="artly-login-page">
    <div class="artly-container">
        <div class="artly-login-wrapper">
            <!-- Left side: Branding/Info -->
            <div class="artly-login-info">
                <div class="artly-login-brand">
                    <h1 class="artly-login-title">Welcome back</h1>
                    <p class="artly-login-subtitle">
                        Sign in to your Artly account to continue downloading stock assets from one wallet.
                    </p>
                </div>
                
                <div class="artly-login-features">
                    <div class="artly-login-feature">
                        <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Access your download history</span>
                    </div>
                    <div class="artly-login-feature">
                        <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Manage your wallet & points</span>
                    </div>
                    <div class="artly-login-feature">
                        <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Re-download past orders</span>
                    </div>
                </div>
            </div>
            
            <!-- Right side: Login Form -->
            <div class="artly-login-form-wrapper">
                <form class="artly-login-form" method="POST" action="" novalidate>
                    <?php wp_nonce_field( 'artly_login_action', 'artly_login_nonce' ); ?>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
                    
                    <!-- Error Messages -->
                    <?php if ( ! empty( $errors ) ) : ?>
                        <div class="artly-form-errors" role="alert">
                            <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="2"/>
                                <path d="M10 6v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            </svg>
                            <div class="artly-form-errors-list">
                                <ul>
                                    <?php foreach ( $errors as $error ) : ?>
                                        <li><?php echo esc_html( $error ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Username/Email Field -->
                    <div class="artly-form-group">
                        <label for="artly_username" class="artly-form-label">
                            Username or Email
                        </label>
                        <input
                            type="text"
                            id="artly_username"
                            name="artly_username"
                            class="artly-form-input"
                            value="<?php echo isset( $_POST['artly_username'] ) ? esc_attr( $_POST['artly_username'] ) : ''; ?>"
                            placeholder="Enter your username or email"
                            required
                            autocomplete="username"
                        />
                    </div>
                    
                    <!-- Password Field -->
                    <div class="artly-form-group">
                        <label for="artly_password" class="artly-form-label">
                            Password
                        </label>
                        <div class="artly-password-wrapper">
                            <input
                                type="password"
                                id="artly_password"
                                name="artly_password"
                                class="artly-form-input"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            />
                            <button type="button" class="artly-password-toggle" aria-label="Toggle password visibility">
                                <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10 4C6 4 2.73 6.11 1 9.5C2.73 12.89 6 15 10 15C14 15 17.27 12.89 19 9.5C17.27 6.11 14 4 10 4Z" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="10" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Remember Me & Forgot Password -->
                    <div class="artly-form-group artly-form-group-row">
                        <label class="artly-form-checkbox">
                            <input
                                type="checkbox"
                                id="artly_remember"
                                name="artly_remember"
                                value="1"
                                <?php echo isset( $_POST['artly_remember'] ) ? 'checked' : ''; ?>
                            />
                            <span class="artly-form-checkbox-label">Remember me</span>
                        </label>
                        <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="artly-forgot-password">
                            Forgot password?
                        </a>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" name="artly_login_submit" class="artly-btn artly-btn-primary artly-btn-full">
                        Sign in
                    </button>
                    
                    <!-- Sign Up Link -->
                    <p class="artly-login-footer">
                        Don't have an account? 
                        <a href="<?php echo esc_url( site_url( '/signup/' ) ); ?>">Create one</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
