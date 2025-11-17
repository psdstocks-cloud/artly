<?php
/**
 * Template Name: Signup
 * Description: User registration page with form validation
 */

// Handle form submission
$errors = array();
$success = false;

if ( isset( $_POST['artly_signup_submit'] ) && wp_verify_nonce( $_POST['artly_signup_nonce'], 'artly_signup_action' ) ) {
    
    // Sanitize input
    $username = sanitize_user( $_POST['artly_username'] );
    $email = sanitize_email( $_POST['artly_email'] );
    $password = $_POST['artly_password'];
    $password_confirm = $_POST['artly_password_confirm'];
    $terms = isset( $_POST['artly_terms'] ) ? true : false;
    
    // Validation
    if ( empty( $username ) ) {
        $errors[] = 'Username is required.';
    } elseif ( strlen( $username ) < 4 ) {
        $errors[] = 'Username must be at least 4 characters.';
    } elseif ( username_exists( $username ) ) {
        $errors[] = 'This username is already taken.';
    }
    
    if ( empty( $email ) ) {
        $errors[] = 'Email is required.';
    } elseif ( ! is_email( $email ) ) {
        $errors[] = 'Please enter a valid email address.';
    } elseif ( email_exists( $email ) ) {
        $errors[] = 'An account with this email already exists.';
    }
    
    if ( empty( $password ) ) {
        $errors[] = 'Password is required.';
    } elseif ( strlen( $password ) < 12 ) {
        $errors[] = 'Password must be at least 12 characters long.';
    } elseif ( ! preg_match( '/[a-z]/', $password ) ) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    } elseif ( ! preg_match( '/[A-Z]/', $password ) ) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    } elseif ( ! preg_match( '/[0-9]/', $password ) ) {
        $errors[] = 'Password must contain at least one number.';
    } elseif ( ! preg_match( '/[^a-zA-Z0-9]/', $password ) ) {
        $errors[] = 'Password must contain at least one special character.';
    }
    
    if ( $password !== $password_confirm ) {
        $errors[] = 'Passwords do not match.';
    }
    
    if ( ! $terms ) {
        $errors[] = 'You must agree to the Terms & Conditions.';
    }
    
    // Create user if no errors
    if ( empty( $errors ) ) {
        $user_id = wp_create_user( $username, $password, $email );
        
        if ( ! is_wp_error( $user_id ) ) {
            // Auto-login the user
            wp_set_current_user( $user_id );
            wp_set_auth_cookie( $user_id );
            
            // Redirect to dashboard or home
            $redirect_url = home_url( '/my-downloads/' ); // Change to your dashboard URL
            if ( ! $redirect_url ) {
                $redirect_url = home_url();
            }
            
            wp_safe_redirect( $redirect_url );
            exit;
        } else {
            $errors[] = $user_id->get_error_message();
        }
    }
}

get_header();
?>

<main class="artly-signup-page">
    <div class="artly-container">
        <div class="artly-signup-wrapper">
            <!-- Left side: Branding/Info -->
            <div class="artly-signup-info">
                <div class="artly-signup-brand">
                    <h1 class="artly-signup-title">Join Artly</h1>
                    <p class="artly-signup-subtitle">
                        Start downloading stock assets from one wallet. No credit juggling, no scattered invoices.
                    </p>
                </div>
                
                <div class="artly-signup-features">
                    <div class="artly-signup-feature">
                        <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>One wallet for all stock sites</span>
                    </div>
                    <div class="artly-signup-feature">
                        <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>Free re-downloads for past orders</span>
                    </div>
                    <div class="artly-signup-feature">
                        <svg class="artly-icon" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M16.667 5L7.5 14.167 3.333 10" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>No long-term contracts</span>
                    </div>
                </div>
            </div>
            
            <!-- Right side: Registration Form -->
            <div class="artly-signup-form-wrapper">
                <form class="artly-signup-form" method="POST" action="" novalidate>
                    <?php wp_nonce_field( 'artly_signup_action', 'artly_signup_nonce' ); ?>
                    
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
                    
                    <!-- Username Field -->
                    <div class="artly-form-group">
                        <label for="artly_username" class="artly-form-label">
                            Username
                        </label>
                        <input
                            type="text"
                            id="artly_username"
                            name="artly_username"
                            class="artly-form-input"
                            value="<?php echo isset( $_POST['artly_username'] ) ? esc_attr( $_POST['artly_username'] ) : ''; ?>"
                            placeholder="Choose a username"
                            required
                            autocomplete="username"
                            aria-describedby="username-hint"
                        />
                        <p class="artly-form-hint" id="username-hint">
                            At least 4 characters. Letters, numbers, and underscores only.
                        </p>
                    </div>
                    
                    <!-- Email Field -->
                    <div class="artly-form-group">
                        <label for="artly_email" class="artly-form-label">
                            Email
                        </label>
                        <input
                            type="email"
                            id="artly_email"
                            name="artly_email"
                            class="artly-form-input"
                            value="<?php echo isset( $_POST['artly_email'] ) ? esc_attr( $_POST['artly_email'] ) : ''; ?>"
                            placeholder="your@email.com"
                            required
                            autocomplete="email"
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
                                placeholder="Create a strong password"
                                required
                                autocomplete="new-password"
                                aria-describedby="password-hint"
                                minlength="12"
                            />
                            <button type="button" class="artly-password-toggle" aria-label="Toggle password visibility">
                                <svg class="artly-icon" width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10 4C6 4 2.73 6.11 1 9.5C2.73 12.89 6 15 10 15C14 15 17.27 12.89 19 9.5C17.27 6.11 14 4 10 4Z" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="10" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </button>
                        </div>
                        <p class="artly-form-hint" id="password-hint">
                            At least 8 characters. Mix of letters, numbers, and symbols recommended.
                        </p>
                    </div>
                    
                    <!-- Confirm Password Field -->
                    <div class="artly-form-group">
                        <label for="artly_password_confirm" class="artly-form-label">
                            Confirm Password
                        </label>
                        <div class="artly-password-wrapper">
                            <input
                                type="password"
                                id="artly_password_confirm"
                                name="artly_password_confirm"
                                class="artly-form-input"
                                placeholder="Re-enter your password"
                                required
                                autocomplete="new-password"
                                minlength="12"
                            />
                            <button type="button" class="artly-password-toggle" aria-label="Toggle password visibility">
                                <svg class="artly-icon" width="16" height="16" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10 4C6 4 2.73 6.11 1 9.5C2.73 12.89 6 15 10 15C14 15 17.27 12.89 19 9.5C17.27 6.11 14 4 10 4Z" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="10" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.5"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Terms & Conditions -->
                    <div class="artly-form-group artly-form-group-checkbox">
                        <label class="artly-form-checkbox">
                            <input
                                type="checkbox"
                                id="artly_terms"
                                name="artly_terms"
                                required
                                <?php echo isset( $_POST['artly_terms'] ) ? 'checked' : ''; ?>
                            />
                            <span class="artly-form-checkbox-label">
                                I agree to the 
                                <a href="<?php echo esc_url( site_url( '/terms/' ) ); ?>" target="_blank">Terms & Conditions</a>
                                and 
                                <a href="<?php echo esc_url( site_url( '/privacy/' ) ); ?>" target="_blank">Privacy Policy</a>
                            </span>
                        </label>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" name="artly_signup_submit" class="artly-btn artly-btn-primary artly-btn-full">
                        Create Account
                    </button>
                    
                    <!-- Sign In Link -->
                    <p class="artly-signup-footer">
                        Already have an account? 
                        <a href="<?php echo esc_url( site_url( '/login/' ) ); ?>">Sign in</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</main>

<?php
get_footer();
