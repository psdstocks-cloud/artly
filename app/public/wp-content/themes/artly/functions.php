<?php
/**
 * Artly theme functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Basic theme setup (optional, but useful)
 */
function artly_theme_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );

    register_nav_menus(
        array(
            'primary' => __( 'Primary Menu', 'artly' ),
        )
    );
}
add_action( 'after_setup_theme', 'artly_theme_setup' );

/**
 * Add canonical meta tag for dashboard page to avoid duplicate content
 */
add_action( 'wp_head', function() {
    if ( is_page( 'dashboard' ) ) {
        echo '<link rel="canonical" href="' . esc_url( home_url( '/dashboard/' ) ) . '" />' . "\n";
    }
}, 5 );

/**
 * Add security headers to all responses.
 */
function artly_security_headers() {
    // Prevent MIME type sniffing
    header( 'X-Content-Type-Options: nosniff' );
    
    // Prevent clickjacking
    header( 'X-Frame-Options: SAMEORIGIN' );
    
    // Enable XSS protection (legacy browsers)
    header( 'X-XSS-Protection: 1; mode=block' );
    
    // Control referrer information
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    
    // Content Security Policy - basic policy
    // Note: Adjust CSP based on your actual needs (external scripts, styles, etc.)
    $csp = "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self' https:; frame-ancestors 'self';";
    header( 'Content-Security-Policy: ' . $csp );
}
add_action( 'send_headers', 'artly_security_headers' );

/**
 * Get all supported websites with their URLs for header navigation
 *
 * @return array Array of sites with label and URL
 */
function artly_get_supported_websites() {
    $sites_config = function_exists( 'nehtw_gateway_get_stock_sites_config' )
        ? nehtw_gateway_get_stock_sites_config()
        : array();

    $websites = array();
    foreach ( $sites_config as $site_key => $site ) {
        if ( ! empty( $site['url'] ) && ! empty( $site['label'] ) ) {
            $websites[] = array(
                'label' => $site['label'],
                'url'   => $site['url'],
                'key'   => $site_key,
            );
        }
    }

    // Sort alphabetically by label
    usort( $websites, function( $a, $b ) {
        return strcasecmp( $a['label'], $b['label'] );
    } );

    return $websites;
}

/**
 * Enqueue styles & scripts
 */
function artly_enqueue_assets() {

    // Main theme stylesheet (style.css in the theme root)
    wp_enqueue_style(
        'artly-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get( 'Version' )
    );

    // Global layout styling for header and footer.
    wp_enqueue_style(
        'artly-layout',
        get_template_directory_uri() . '/assets/css/layout.css',
        array( 'artly-style' ),
        wp_get_theme()->get( 'Version' )
    );

    // Only load the pricing assets on the Pricing page (slug: pricing)
    if ( is_page( 'pricing' ) ) {

        // /wp-content/themes/artly/assets/css/pricing.css
        wp_enqueue_style(
            'artly-pricing',
            get_template_directory_uri() . '/assets/css/pricing.css',
            array( 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        // GSAP Core for pricing page animations
        wp_enqueue_script(
            'gsap',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
            array(),
            null,
            true
        );

        // GSAP ScrollTrigger for pricing page animations
        wp_enqueue_script(
            'gsap-scrolltrigger',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js',
            array( 'gsap' ),
            null,
            true
        );

        // Enhanced pricing script with currency, animations, and theme toggle
        wp_enqueue_script(
            'artly-pricing',
            get_template_directory_uri() . '/assets/js/artly-pricing-v2.js',
            array( 'gsap', 'gsap-scrolltrigger' ),
            wp_get_theme()->get( 'Version' ),
            true
        );

        // Get WooCommerce product ID for wallet top-up (if WooCommerce is active)
        $woocommerce_product_id = 0;
        if ( class_exists( 'WooCommerce' ) ) {
            // Get product ID from option, default to 25 (the "Wallet points top-up" product)
            $woocommerce_product_id = get_option( 'artly_woocommerce_product_id', 25 );
            
            // Auto-set product ID if not already configured (one-time setup)
            if ( get_option( 'artly_woocommerce_product_id' ) === false ) {
                update_option( 'artly_woocommerce_product_id', 25 );
                $woocommerce_product_id = 25;
            }
        }

        // Get currency conversion rate
        $usd_egp_rate = get_option( 'nehtw_usd_egp_rate', 50 );
        
        // Localize script with PHP data
        wp_localize_script( 'artly-pricing', 'artlyPricingSettings', array(
            'isLoggedIn' => is_user_logged_in(),
            'userCurrency' => artly_detect_currency(),
            'homeUrl' => home_url(),
            'woocommerceProductId' => $woocommerce_product_id,
            'woocommerceActive' => class_exists( 'WooCommerce' ),
            'conversionRate' => floatval( $usd_egp_rate ), // EGP per USD (e.g., 50)
        ) );

        wp_enqueue_script(
            'artly-pricing-woo',
            get_template_directory_uri() . '/assets/js/pricing-woo.js',
            array( 'artly-pricing' ),
            wp_get_theme()->get( 'Version' ),
            true
        );

        wp_localize_script(
            'artly-pricing-woo',
            'artlyWoo',
            array(
                'restBase' => esc_url_raw( rest_url( 'artly/v1/' ) ),
                'nonce'    => wp_create_nonce( 'wp_rest' ),
            )
        );
    }

    // Header script for mobile navigation toggle.
    wp_enqueue_script(
        'artly-header',
        get_template_directory_uri() . '/assets/js/header.js',
        array(),
        wp_get_theme()->get( 'Version' ),
        true
    );

    // Dashboard assets for the user dashboard template.
    if ( is_page_template( 'page-dashboard.php' ) || is_page( 'dashboard' ) ) {
        wp_enqueue_style(
            'artly-dashboard',
            get_template_directory_uri() . '/assets/css/dashboard.css',
            array( 'artly-layout', 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-dashboard',
            get_template_directory_uri() . '/assets/js/dashboard.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );
    }

    if ( is_page_template( 'page-my-downloads.php' ) || is_page( 'my-downloads' ) ) {
        wp_enqueue_style(
            'artly-downloads',
            get_template_directory_uri() . '/assets/css/downloads.css',
            array( 'artly-layout', 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-downloads',
            get_template_directory_uri() . '/assets/js/downloads.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );

        wp_localize_script(
            'artly-downloads',
            'artlyDownloadsSettings',
            array(
                'restUrl'        => esc_url_raw( rest_url( 'nehtw/v1/' ) ),
                'redownloadUrl'  => esc_url_raw( rest_url( 'artly/v1/download-redownload' ) ),
                'exportUrl'      => esc_url_raw( rest_url( 'artly/v1/downloads-export' ) ),
                'statusEndpoint' => esc_url_raw( rest_url( 'artly/v1/stock-order-status' ) ),
                'nonce'          => wp_create_nonce( 'wp_rest' ),
            )
        );
    }

    if ( is_page_template( 'page-transactions.php' ) || is_page( 'transactions' ) || is_page( 'wallet-history' ) ) {
        wp_enqueue_style(
            'artly-transactions',
            get_template_directory_uri() . '/assets/css/transactions.css',
            array( 'artly-layout', 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-transactions',
            get_template_directory_uri() . '/assets/js/transactions.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );
    }

    if ( is_page_template( 'page-subscriptions.php' ) || is_page( 'subscriptions' ) ) {
        wp_enqueue_style(
            'artly-subscriptions',
            get_template_directory_uri() . '/assets/css/subscriptions.css',
            array( 'artly-layout', 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );
    }

    // My Subscriptions page assets
    if ( is_page_template( 'page-my-subscriptions.php' ) || is_page( 'my-subscriptions' ) ) {
        wp_enqueue_style(
            'artly-my-subscriptions',
            get_template_directory_uri() . '/assets/css/subscriptions.css',
            array( 'artly-layout', 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-my-subscriptions',
            get_template_directory_uri() . '/assets/js/my-subscriptions.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );

        wp_localize_script(
            'artly-my-subscriptions',
            'artlyMySubscriptionsSettings',
            array(
                'restUrl' => esc_url_raw( rest_url( 'artly/v1/' ) ),
                'nonce'   => wp_create_nonce( 'wp_rest' ),
            )
        );
    }

    // WooCommerce Cart page assets
    if ( function_exists( 'is_cart' ) && is_cart() ) {
        wp_enqueue_style(
            'artly-cart',
            get_template_directory_uri() . '/assets/css/cart.css',
            array( 'artly-layout', 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-cart',
            get_template_directory_uri() . '/assets/js/cart.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );
    }

    // WooCommerce Checkout page assets
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        wp_enqueue_style(
            'artly-checkout',
            get_template_directory_uri() . '/assets/css/checkout.css',
            array( 'artly-layout', 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-checkout',
            get_template_directory_uri() . '/assets/js/checkout.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );
    }

    if ( is_page_template( 'page-dashboard.php' ) || is_page_template( 'page-my-points.php' ) || is_page( 'dashboard' ) || is_page( 'my-points' ) ) {
        wp_enqueue_style(
            'artly-modal-wallet',
            get_template_directory_uri() . '/assets/css/modal-wallet.css',
            array( 'artly-layout', 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-modal-wallet',
            get_template_directory_uri() . '/assets/js/modal-wallet.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );
    }

    // Onboarding assets (Dashboard only)
    if ( is_page_template( 'page-dashboard.php' ) || is_page( 'dashboard' ) ) {
        wp_enqueue_style(
            'artly-onboarding',
            get_template_directory_uri() . '/assets/css/onboarding.css',
            array( 'artly-layout', 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-onboarding',
            get_template_directory_uri() . '/assets/js/onboarding.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );

        // Localize AJAX + nonce
        wp_localize_script(
            'artly-onboarding',
            'artlyOnboarding',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'artly_onboarding' ),
            )
        );
    }

    // Stock Ordering assets
    if ( is_page_template( 'page-stock-order.php' ) || is_page( 'stock-order' ) ) {
        wp_enqueue_style(
            'artly-stock-order',
            get_template_directory_uri() . '/assets/css/stock-order.css',
            array( 'artly-layout', 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-stock-order',
            get_template_directory_uri() . '/assets/js/stock-order.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );

        // Get sites config from plugin
        $sites_config = function_exists( 'nehtw_gateway_get_stock_sites_config' )
            ? nehtw_gateway_get_stock_sites_config()
            : array();

        $dashboard_page = get_page_by_path( 'dashboard' );
        $history_url     = home_url( '/my-downloads/' );

        wp_localize_script(
            'artly-stock-order',
            'artlyStockOrder',
            array(
                'endpoint'        => esc_url_raw( rest_url( 'artly/v1/stock-order' ) ),
                'statusEndpoint'  => esc_url_raw( rest_url( 'artly/v1/stock-order-status' ) ),
                'previewEndpoint' => esc_url_raw( rest_url( 'artly/v1/stock-order-preview' ) ),
                'walletEndpoint'  => esc_url_raw( rest_url( 'artly/v1/wallet-info' ) ),
                'restNonce'       => wp_create_nonce( 'wp_rest' ),
                'sites'           => $sites_config,
                'historyUrl'      => esc_url_raw( $history_url ),
            )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'artly_enqueue_assets' );

/**
 * Home / front-page styles.
 */
function artly_enqueue_home_styles() {
    if ( is_front_page() ) {
        wp_enqueue_style(
            'artly-home',
            get_template_directory_uri() . '/assets/css/home.css',
            array(),
            wp_get_theme()->get( 'Version' )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'artly_enqueue_home_styles' );

/**
 * Enqueue login template assets only when needed.
 */
function artly_enqueue_login_assets() {
    if ( ! ( is_page_template( 'page-login.php' ) || is_page( 'login' ) ) ) {
        return;
    }

    $version = wp_get_theme()->get( 'Version' );

    wp_enqueue_style(
        'artly-login',
        get_template_directory_uri() . '/assets/css/login.css',
        array(),
        $version
    );

    wp_enqueue_script(
        'gsap',
        'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
        array(),
        null,
        true
    );

    wp_enqueue_script(
        'artly-login',
        get_template_directory_uri() . '/assets/js/login.js',
        array( 'gsap' ),
        $version,
        true
    );

    $localized = array(
        'i18n' => array(
            'show'     => __( 'Show', 'artly' ),
            'hide'     => __( 'Hide', 'artly' ),
            'showPassword' => __( 'Show password', 'artly' ),
            'hidePassword' => __( 'Hide password', 'artly' ),
            'required' => __( 'Please fill all required fields.', 'artly' ),
            'invalid'  => __( 'Invalid email or password.', 'artly' ),
        ),
    );

    wp_localize_script( 'artly-login', 'ARTLY_LOGIN', $localized );
}
add_action( 'wp_enqueue_scripts', 'artly_enqueue_login_assets', 20 );

/**
 * Cinematic GSAP + Lottie setup for Artly home
 */
function artly_enqueue_cinematic_scripts() {
    if ( is_front_page() ) {
        $theme_uri = get_template_directory_uri();
        $version   = wp_get_theme()->get( 'Version' );

        wp_enqueue_script(
            'gsap',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
            array(),
            '3.12.5',
            true
        );

        wp_enqueue_script(
            'gsap-scrolltrigger',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js',
            array( 'gsap' ),
            '3.12.5',
            true
        );

        wp_enqueue_script(
            'artly-home-animations',
            $theme_uri . '/assets/js/home-animations.js',
            array( 'gsap', 'gsap-scrolltrigger' ),
            $version,
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'artly_enqueue_cinematic_scripts' );

/**
 * WooCommerce Cart Optimizations for Digital Products
 * Hide cross-sells and optimize cart for points/credits
 */
if ( class_exists( 'WooCommerce' ) ) {
    // Hide cross-sells on cart page (not relevant for digital points)
    add_filter( 'woocommerce_cart_cross_sells_products', '__return_empty_array' );

    // Remove cross-sells section entirely
    remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );

    // Ensure points product is sold individually (already set in product, but double-check)
    add_filter( 'woocommerce_is_sold_individually', function( $sold_individually, $product ) {
        $wallet_product_id = get_option( 'artly_woocommerce_product_id', 0 );
        if ( $product->get_id() == $wallet_product_id ) {
            return true;
        }
        return $sold_individually;
    }, 10, 2 );
}

/**
 * Enqueue signup page assets
 */
function artly_enqueue_signup_assets() {
    if ( is_page_template( 'page-signup.php' ) || is_page( 'signup' ) ) {
        wp_enqueue_style(
            'artly-signup',
            get_template_directory_uri() . '/assets/css/signup.css',
            array( 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-signup-form',
            get_template_directory_uri() . '/assets/js/signup-form.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'artly_enqueue_signup_assets' );

/**
 * Localize signup script with AJAX URL
 */
function artly_localize_signup_script() {
    if ( is_page_template( 'page-signup.php' ) || is_page( 'signup' ) ) {
        wp_localize_script( 'artly-signup-form', 'artlySignup', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'artly_signup_ajax_nonce' )
        ) );
    }
}
add_action( 'wp_enqueue_scripts', 'artly_localize_signup_script' );

/**
 * Handle AJAX signup submission
 */
function artly_ajax_signup() {
    // Verify nonce
    check_ajax_referer( 'artly_signup_ajax_nonce', 'nonce' );
    
    $errors = array();
    $success = false;
    
    // Sanitize input
    $username = sanitize_user( $_POST['artly_username'] ?? '' );
    $email = sanitize_email( $_POST['artly_email'] ?? '' );
    $password = $_POST['artly_password'] ?? '';
    $password_confirm = $_POST['artly_password_confirm'] ?? '';
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
            
            wp_send_json_success( array(
                'message' => 'Account created successfully! Redirecting...',
                'redirect' => home_url( '/my-downloads/' )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => $user_id->get_error_message(),
                'errors' => array( $user_id->get_error_message() )
            ) );
        }
    } else {
        wp_send_json_error( array(
            'message' => 'Please fix the errors below.',
            'errors' => $errors
        ) );
    }
}
add_action( 'wp_ajax_artly_signup_ajax', 'artly_ajax_signup' );
add_action( 'wp_ajax_nopriv_artly_signup_ajax', 'artly_ajax_signup' );

/**
 * Enqueue My Points page assets
 */
function artly_enqueue_points_assets() {
    // Load on the dedicated My Points template or slug "my-points"
    if ( is_page_template( 'page-my-points.php' ) || is_page( 'my-points' ) ) {
        wp_enqueue_style(
            'artly-points',
            get_template_directory_uri() . '/assets/css/points.css',
            array( 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'artly_enqueue_points_assets' );

/**
 * Handle AJAX login submission
 */
function artly_ajax_login() {
    // Verify nonce
    check_ajax_referer( 'artly_login_ajax_nonce', 'nonce' );
    
    $errors = array();
    
    // Sanitize input
    $username = sanitize_user( $_POST['artly_username'] ?? '' );
    $password = $_POST['artly_password'] ?? '';
    $remember = isset( $_POST['artly_remember'] ) ? true : false;
    $redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( $_POST['redirect_to'] ) : home_url( '/dashboard/' );
    
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
            $error_message = $user->get_error_message();
            // Make error messages more user-friendly
            if ( strpos( $error_message, 'incorrect password' ) !== false || strpos( $error_message, 'Invalid' ) !== false ) {
                $error_message = 'Invalid username or password.';
            }
            wp_send_json_error( array(
                'message' => $error_message,
                'errors' => array( $error_message )
            ) );
        } else {
            wp_send_json_success( array(
                'message' => 'Login successful!',
                'redirect' => $redirect_to
            ) );
        }
    } else {
        wp_send_json_error( array(
            'message' => 'Please fix the errors below.',
            'errors' => $errors
        ) );
    }
}
add_action( 'wp_ajax_artly_login_ajax', 'artly_ajax_login' );
add_action( 'wp_ajax_nopriv_artly_login_ajax', 'artly_ajax_login' );

/**
 * Handle AJAX onboarding completion
 */
function artly_ajax_onboarding_complete() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( array( 'message' => 'Unauthorized' ), 401 );
    }

    $user_id = get_current_user_id();
    update_user_meta( $user_id, 'artly_onboarding_completed', 1 );

    wp_send_json_success();
}
add_action( 'wp_ajax_artly_onboarding_complete', 'artly_ajax_onboarding_complete' );

/**
 * Reset onboarding (for testing only - remove this in production)
 * Add ?reset_onboarding=1 to the dashboard URL to reset your onboarding status
 */
function artly_reset_onboarding_for_testing() {
    if ( isset( $_GET['reset_onboarding'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) {
        delete_user_meta( get_current_user_id(), 'artly_onboarding_completed' );
        wp_safe_redirect( home_url( '/dashboard/' ) );
        exit;
    }
}
add_action( 'template_redirect', 'artly_reset_onboarding_for_testing' );

/**
 * Filter login URL to use custom login page
 * But preserve wp-admin redirects so admin can still be accessed
 */
function artly_custom_login_url( $login_url, $redirect, $force_reauth ) {
    // If redirect is to wp-admin, preserve the original login URL behavior
    // This allows WordPress core to handle admin authentication properly
    if ( $redirect && false !== strpos( $redirect, admin_url() ) ) {
        // For admin redirects, use the custom login page but ensure redirect_to is preserved
        $login_page = get_page_by_path( 'login' );
        if ( $login_page ) {
            $login_url = get_permalink( $login_page->ID );
            $login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $login_url );
            return $login_url;
        }
    }
    
    // For all other cases, use custom login page
    $login_page = get_page_by_path( 'login' );
    if ( $login_page ) {
        $login_url = get_permalink( $login_page->ID );
        if ( $redirect ) {
            $login_url = add_query_arg( 'redirect_to', urlencode( $redirect ), $login_url );
        }
    }
    return $login_url;
}
add_filter( 'login_url', 'artly_custom_login_url', 10, 3 );

/**
 * Redirect direct visits to wp-login.php to the branded /login/ page.
 */
function artly_force_branded_login() {
    // Only for GET requests, so we don't break core form posts.
    if ( 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
        return;
    }

    // Don't redirect if we're processing a login action (like logout, lostpassword, etc.)
    $action = isset( $_GET['action'] ) ? $_GET['action'] : '';
    if ( in_array( $action, array( 'logout', 'lostpassword', 'resetpass', 'rp', 'register', 'login' ), true ) ) {
        // Allow logout and password reset to work normally
        if ( 'logout' === $action || 'lostpassword' === $action || 'resetpass' === $action || 'rp' === $action || 'register' === $action ) {
            return;
        }
        // For 'login' action or no action, redirect to custom page
    }

    $login_slug = 'login'; // the page you created
    $login_url  = home_url( '/' . $login_slug . '/' );

    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    // If we're on wp-login.php and not already on /login/, redirect.
    if ( false !== strpos( $request_uri, 'wp-login.php' ) && ! is_page( $login_slug ) ) {
        // Preserve any error parameters
        if ( isset( $_GET['login'] ) ) {
            $login_url = add_query_arg( 'login', sanitize_text_field( $_GET['login'] ), $login_url );
        }
        wp_safe_redirect( $login_url );
        exit;
    }
}
add_action( 'login_init', 'artly_force_branded_login' );

/**
 * Redirect failed logins back to custom login page
 */
function artly_redirect_failed_login( $username ) {
    $login_page = get_page_by_path( 'login' );
    if ( $login_page ) {
        $login_url = get_permalink( $login_page->ID );
        $login_url = add_query_arg( 'login', 'failed', $login_url );
        wp_safe_redirect( $login_url );
        exit;
    }
}
add_filter( 'wp_login_failed', 'artly_redirect_failed_login' );

/**
 * Capture WooCommerce error notices for the custom login experience.
 */
function artly_login_collect_wc_notices() {
    static $cached = null;

    if ( null !== $cached ) {
        return $cached;
    }

    if ( ! function_exists( 'wc_print_notices' ) ) {
        $cached = '';
        return $cached;
    }

    if ( ! is_page_template( 'page-login.php' ) ) {
        wc_print_notices();
        $cached = '';
        return $cached;
    }

    ob_start();
    wc_print_notices();
    $markup = ob_get_clean();

    if ( empty( $markup ) ) {
        $cached = '';
        return $cached;
    }

    $cached = trim( wp_strip_all_tags( $markup ) );

    return $cached;
}

if ( function_exists( 'wc_print_notices' ) ) {
    add_action( 'woocommerce_before_customer_login_form', 'artly_login_collect_wc_notices', 5 );
}

/**
 * Helper accessor for login error message.
 */
function artly_login_get_notice_message() {
    return artly_login_collect_wc_notices();
}

/**
 * Track login attempt for brute force protection.
 *
 * @param string $username The username or email attempted
 * @param string $ip The IP address of the attempt
 */
function artly_track_login_attempt( $username, $ip ) {
    $username_key = sanitize_key( $username );
    $ip_key = sanitize_key( $ip );
    
    // Track by both username and IP for better security
    $key_username = 'artly_login_attempts_' . $username_key;
    $key_ip = 'artly_login_attempts_ip_' . $ip_key;
    $key_combined = 'artly_login_attempts_' . md5( $username_key . '|' . $ip_key );
    
    $attempts_username = (int) get_transient( $key_username );
    $attempts_ip = (int) get_transient( $key_ip );
    $attempts_combined = (int) get_transient( $key_combined );
    
    $attempts_username++;
    $attempts_ip++;
    $attempts_combined++;
    
    // Store attempts for 1 hour
    set_transient( $key_username, $attempts_username, HOUR_IN_SECONDS );
    set_transient( $key_ip, $attempts_ip, HOUR_IN_SECONDS );
    set_transient( $key_combined, $attempts_combined, HOUR_IN_SECONDS );
    
    // Log excessive attempts
    if ( $attempts_combined >= 10 ) {
        error_log( sprintf( 'Artly: Excessive login attempts detected. Username: %s, IP: %s, Attempts: %d', sanitize_text_field( $username ), $ip, $attempts_combined ) );
    }
}

/**
 * Check if login rate limit has been exceeded.
 *
 * @param string $username The username or email
 * @param string $ip The IP address
 * @return WP_Error|true Returns WP_Error if rate limited, true otherwise
 */
function artly_check_login_rate_limit( $username, $ip ) {
    $username_key = sanitize_key( $username );
    $ip_key = sanitize_key( $ip );
    $key_combined = 'artly_login_attempts_' . md5( $username_key . '|' . $ip_key );
    
    $attempts = (int) get_transient( $key_combined );
    
    // Exponential backoff: 5 attempts = 1 min, 10 attempts = 5 min, 15 attempts = 15 min
    if ( $attempts >= 15 ) {
        $lockout_time = 15 * MINUTE_IN_SECONDS;
        $lockout_key = 'artly_login_lockout_' . md5( $username_key . '|' . $ip_key );
        set_transient( $lockout_key, true, $lockout_time );
        return new WP_Error(
            'artly_rate_limited',
            __( 'Too many login attempts. Please try again in 15 minutes.', 'artly' )
        );
    } elseif ( $attempts >= 10 ) {
        $lockout_time = 5 * MINUTE_IN_SECONDS;
        $lockout_key = 'artly_login_lockout_' . md5( $username_key . '|' . $ip_key );
        $is_locked = get_transient( $lockout_key );
        if ( $is_locked ) {
            return new WP_Error(
                'artly_rate_limited',
                __( 'Too many login attempts. Please try again in 5 minutes.', 'artly' )
            );
        }
        set_transient( $lockout_key, true, $lockout_time );
    } elseif ( $attempts >= 5 ) {
        $lockout_time = 1 * MINUTE_IN_SECONDS;
        $lockout_key = 'artly_login_lockout_' . md5( $username_key . '|' . $ip_key );
        $is_locked = get_transient( $lockout_key );
        if ( $is_locked ) {
            return new WP_Error(
                'artly_rate_limited',
                __( 'Too many login attempts. Please try again in 1 minute.', 'artly' )
            );
        }
        set_transient( $lockout_key, true, $lockout_time );
    }
    
    return true;
}

/**
 * Clear login attempts on successful login.
 *
 * @param string $username The username or email
 * @param string $ip The IP address
 */
function artly_clear_login_attempts( $username, $ip ) {
    $username_key = sanitize_key( $username );
    $ip_key = sanitize_key( $ip );
    
    delete_transient( 'artly_login_attempts_' . $username_key );
    delete_transient( 'artly_login_attempts_ip_' . $ip_key );
    delete_transient( 'artly_login_attempts_' . md5( $username_key . '|' . $ip_key ) );
    delete_transient( 'artly_login_lockout_' . md5( $username_key . '|' . $ip_key ) );
}

/**
 * Honeypot and nonce validation for the custom login form.
 */
function artly_login_security_checks( $user, $username, $password ) {
    if ( empty( $_POST['artly_login_submission'] ) ) {
        return $user;
    }

    // Get IP address
    $ip = artly_get_client_ip();
    
    // Check rate limiting before other checks
    $rate_limit_check = artly_check_login_rate_limit( $username, $ip );
    if ( is_wp_error( $rate_limit_check ) ) {
        return $rate_limit_check;
    }

    $honeypot = isset( $_POST['artly_login_hp'] ) ? trim( wp_unslash( $_POST['artly_login_hp'] ) ) : '';
    if ( '' !== $honeypot ) {
        artly_track_login_attempt( $username, $ip );
        return new WP_Error( 'artly_spam', __( 'Invalid email or password.', 'artly' ) );
    }

    $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'artly_login' ) ) {
        artly_track_login_attempt( $username, $ip );
        return new WP_Error( 'artly_nonce', __( 'Security check failed. Please try again.', 'artly' ) );
    }

    return $user;
}
add_filter( 'authenticate', 'artly_login_security_checks', 5, 3 );

/**
 * Track failed login attempts and clear on success.
 */
function artly_track_login_result( $user, $username, $password ) {
    if ( empty( $_POST['artly_login_submission'] ) ) {
        return $user;
    }
    
    $ip = artly_get_client_ip();
    
    if ( is_wp_error( $user ) ) {
        // Failed login - track attempt
        artly_track_login_attempt( $username, $ip );
    } else {
        // Successful login - clear attempts
        artly_clear_login_attempts( $username, $ip );
    }
    
    return $user;
}
add_filter( 'authenticate', 'artly_track_login_result', 99, 3 );

/**
 * Implement secure session management.
 */
function artly_secure_session_settings() {
    // Set secure cookie flags via WordPress filters
    if ( ! headers_sent() ) {
        // Force secure cookies on HTTPS
        if ( is_ssl() ) {
            ini_set( 'session.cookie_secure', '1' );
        }
        // HttpOnly flag (WordPress handles this by default)
        ini_set( 'session.cookie_httponly', '1' );
        // SameSite attribute
        ini_set( 'session.cookie_samesite', 'Strict' );
    }
}
add_action( 'init', 'artly_secure_session_settings', 1 );

/**
 * Regenerate session ID on login for security.
 */
function artly_regenerate_session_on_login( $user_login, $user ) {
    if ( $user && ! is_wp_error( $user ) ) {
        wp_set_current_user( $user->ID );
        // WordPress doesn't expose session regeneration directly, but we can clear auth cookie and reset
        wp_clear_auth_cookie();
        wp_set_auth_cookie( $user->ID, true );
    }
}
add_action( 'wp_login', 'artly_regenerate_session_on_login', 10, 2 );

/**
 * Implement session timeout (30 minutes inactivity).
 */
function artly_check_session_timeout() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    $last_activity = get_user_meta( get_current_user_id(), 'artly_last_activity', true );
    $timeout = 30 * MINUTE_IN_SECONDS; // 30 minutes

    if ( ! empty( $last_activity ) && ( time() - (int) $last_activity ) > $timeout ) {
        // Session expired, log out user
        wp_logout();
        wp_safe_redirect( home_url( '/login/?session_expired=1' ) );
        exit;
    }

    // Update last activity timestamp
    update_user_meta( get_current_user_id(), 'artly_last_activity', time() );
}
add_action( 'init', 'artly_check_session_timeout', 1 );

/**
 * Get client IP address in a safe way (X-Forwarded-For aware)
 */
function artly_get_client_ip() {
    $keys = array(
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    );

    foreach ( $keys as $key ) {
        if ( ! empty( $_SERVER[ $key ] ) ) {
            $ip_list = explode( ',', $_SERVER[ $key ] );
            // First IP in list is usually the real one
            $ip = trim( $ip_list[0] );
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                return $ip;
            }
        }
    }

    return '127.0.0.1'; // Fallback (likely local dev)
}

/**
 * Detect currency based on IP using a free geo API
 *
 * For production traffic you'll probably want to:
 * - Register for a free API key (higher limits, more reliability)
 * - Extend the mapping (EUR, GBP, etc.) if you add more currencies
 */
function artly_detect_currency() {
    // 1) Check for user override cookie first (user preference takes priority)
    if ( isset( $_COOKIE['artly_currency'] ) ) {
        $cookie_currency = strtoupper( sanitize_text_field( $_COOKIE['artly_currency'] ) );
        if ( in_array( $cookie_currency, array( 'EGP', 'USD' ), true ) ) {
            return $cookie_currency;
        }
    }

    $ip = artly_get_client_ip();

    // Local dev / private network -> default to EGP
    if ( $ip === '127.0.0.1' || str_starts_with( $ip, '192.168.' ) || str_starts_with( $ip, '10.' ) ) {
        return 'EGP';
    }

    // 2) Use a transient to avoid calling API on every request
    $cache_key = 'artly_geo_currency_' . md5( $ip );
    $cached    = get_transient( $cache_key );

    if ( $cached ) {
        return $cached;
    }

    // 3) Free IP API — no key needed for light usage
    // You can swap this to another provider later if you want
    $url = 'https://ipapi.co/' . rawurlencode( $ip ) . '/json/';

    $response = wp_remote_get(
        $url,
        array(
            'timeout' => 3,
        )
    );

    if ( is_wp_error( $response ) ) {
        // On any error, fallback to EGP
        $currency = 'EGP';
        set_transient( $cache_key, $currency, HOUR_IN_SECONDS );
        return $currency;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    // Default fallback
    $currency = 'EGP';

    if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
        // ipapi.co returns `country_code` and `currency` usually
        if ( ! empty( $data['currency'] ) ) {
            // Example: "EGP", "USD", "EUR", ...
            $detected_currency = strtoupper( $data['currency'] );
            // Only allow EGP or USD for now (can be extended)
            if ( in_array( $detected_currency, array( 'EGP', 'USD' ), true ) ) {
                $currency = $detected_currency;
            } else {
                // If currency is not EGP/USD, check country code
                if ( ! empty( $data['country_code'] ) ) {
                    $country = strtoupper( $data['country_code'] );
                    if ( 'EG' === $country ) {
                        $currency = 'EGP';
                    } else {
                        $currency = 'USD';
                    }
                }
            }
        } elseif ( ! empty( $data['country_code'] ) ) {
            $country = strtoupper( $data['country_code'] );
            // Minimal mapping. You can expand this list if needed
            if ( 'EG' === $country ) {
                $currency = 'EGP';
            } elseif ( in_array( $country, array( 'US', 'CA', 'MX', 'AE', 'SA', 'QA', 'KW' ), true ) ) {
                $currency = 'USD';
            } else {
                // For now default to USD for non-Egypt
                $currency = 'USD';
            }
        }
    }

    // 4) Cache result for 6 hours to avoid API spam
    set_transient( $cache_key, $currency, 6 * HOUR_IN_SECONDS );

    return $currency;
}

// ===== Artly My Account (SaaS Dashboard) =====

// === 1. Add SaaS endpoints (wallet, subscriptions) ===
add_action( 'init', function () {
    add_rewrite_endpoint( 'wallet', EP_ROOT | EP_PAGES );
    add_rewrite_endpoint( 'subscriptions', EP_ROOT | EP_PAGES );
} );

// === 2. Replace My Account menu items ===
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
    // Remove irrelevant for digital-only SaaS
    unset( $items['edit-address'], $items['downloads'] ); // we re-add custom downloads

    // Rebuild in desired order
    return array(
        'dashboard'       => __( 'Dashboard', 'artly' ),
        'wallet'          => __( 'My Wallet', 'artly' ),
        'downloads'       => __( 'Downloads', 'artly' ),
        'subscriptions'   => __( 'Subscriptions', 'artly' ),
        'edit-account'    => __( 'Profile Settings', 'artly' ),
        'customer-logout' => __( 'Log out', 'artly' ),
    );
} );

// === 2.5. Remove WooCommerce navigation and default output on edit-account page ===
add_action( 'template_redirect', function() {
    if ( function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'WC' ) ) {
        $endpoint = WC()->query->get_current_endpoint();
        if ( $endpoint === 'edit-account' ) {
            // Remove WooCommerce navigation
            remove_action( 'woocommerce_account_navigation', 'woocommerce_account_navigation', 10 );
            remove_action( 'woocommerce_before_account_navigation', 'woocommerce_output_account_navigation', 10 );
        }
    }
}, 5 );

// === 2.7. Remove WooCommerce default "My account" title and navigation output ===
// WooCommerce outputs title/nav through woocommerce_account_content, but we need the template
// So we'll remove the default function and add our own that only loads the template
add_action( 'template_redirect', function() {
    if ( function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'WC' ) ) {
        $endpoint = WC()->query->get_current_endpoint();
        if ( $endpoint === 'edit-account' ) {
            // Remove WooCommerce default account content function (outputs title + nav + template)
            remove_action( 'woocommerce_account_content', 'woocommerce_account_content', 10 );
            
            // Add our own that only loads the template (no title/nav)
            add_action( 'woocommerce_account_content', function() {
                $endpoint = WC()->query->get_current_endpoint();
                if ( $endpoint === 'edit-account' ) {
                    wc_get_template( 'myaccount/form-edit-account.php' );
                }
            }, 10 );
            
            // Remove WooCommerce default notices output (we handle it in my-account.php)
            remove_action( 'woocommerce_before_my_account', 'woocommerce_output_all_notices', 5 );
        }
    }
}, 1 );

// === 2.6. Remove WooCommerce default page title on edit-account ===
add_filter( 'woocommerce_page_title', function( $title ) {
    if ( function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'WC' ) ) {
        $endpoint = WC()->query->get_current_endpoint();
        if ( $endpoint === 'edit-account' ) {
            return ''; // Remove WooCommerce title
        }
    }
    return $title;
}, 20 );

// === 3. Load assets for Dashboard and WooCommerce account endpoints ===
add_action( 'wp_enqueue_scripts', function () {
    $theme   = wp_get_theme();
    $version = $theme->get( 'Version' );

    $is_dashboard = is_page( 'dashboard' );
    $is_login     = is_page( 'login' );
    $is_wc_account = function_exists( 'is_account_page' ) && is_account_page();
    $is_wc_lost    = $is_wc_account && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'lost-password' );
    $is_wc_reset   = $is_wc_account && ( isset( $_GET['key'], $_GET['id'] ) ); // reset key screen

    if ( $is_dashboard || $is_login || $is_wc_account || $is_wc_lost || $is_wc_reset ) {
        // Main dashboard/login/account styles
        wp_enqueue_style(
            'artly-account',
            get_stylesheet_directory_uri() . '/assets/css/artly-account.css',
            array(),
            $version
        );

        wp_enqueue_script(
            'artly-account',
            get_stylesheet_directory_uri() . '/assets/js/artly-account.js',
            array( 'jquery' ),
            $version,
            true
        );

        wp_localize_script(
            'artly-account',
            'ARTLY_ACC',
            array(
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'i18n'  => array(
                    'addPoints' => __( 'Add Points', 'artly' ),
                ),
            )
        );
    }
}, 20 );

// === 4. Data providers (safe wrappers) ===

/**
 * Wallet balance: try filter first (backend owns truth), fallback to user meta or Nehtw Gateway.
 *
 * @param int $user_id
 * @return int
 */
function artly_get_wallet_points( $user_id ) {
    $points = apply_filters( 'artly_wallet_balance', null, $user_id ); // Your plugin/BFF can return an int
    if ( $points !== null ) {
        return max( 0, (int) $points );
    }

    // Try Nehtw Gateway function
    if ( function_exists( 'nehtw_gateway_get_user_points_balance' ) ) {
        $points = nehtw_gateway_get_user_points_balance( $user_id );
        if ( $points !== false ) {
            return max( 0, (int) $points );
        }
    }

    // Fallback to user meta
    $points = (int) get_user_meta( $user_id, 'artly_wallet_points', true );
    return max( 0, $points );
}

/**
 * Recent downloads: ask backend, fallback to Woo orders w/ download permissions.
 *
 * @param int $user_id
 * @param int $limit
 * @return array
 */
function artly_get_recent_downloads( $user_id, $limit = 10 ) {
    $items = apply_filters( 'artly_recent_downloads', null, $user_id, $limit ); // expect array of ['title','thumb','url','created_at','size']
    if ( is_array( $items ) ) {
        return array_slice( $items, 0, $limit );
    }

    // Fallback to Woo (digital products)
    if ( function_exists( 'wc_get_customer_available_downloads' ) ) {
        $downloads = wc_get_customer_available_downloads( $user_id );
        $out       = array();
        foreach ( array_slice( $downloads, 0, $limit ) as $d ) {
            $out[] = array(
                'title'      => $d['download_name'] ?? '',
                'thumb'      => get_the_post_thumbnail_url( $d['product_id'], 'thumbnail' ),
                'url'        => $d['download_url'],
                'created_at' => strtotime( $d['access_granted'] ?? 'now' ),
                'size'       => null,
            );
        }
        return $out;
    }

    return array();
}
// Canonicalize login to /login
add_action('template_redirect', function () {
    // Comprehensive routing logic for dashboard and account pages
  if ( is_admin() ) {
    return;
  }

  $is_account   = function_exists( 'is_account_page' ) && is_account_page();
  $is_wc_ep     = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url();
  $is_dashboard = is_page( 'dashboard' );
  $is_login     = is_page( 'login' );

  // LOGGED OUT
  if ( ! is_user_logged_in() ) {
    // My Account base (no endpoint) → Login
    if ( $is_account && ! $is_wc_ep ) {
      wp_safe_redirect( home_url( '/login/' ), 302 );
      exit;
    }

    // Dashboard should not be visible when logged out
    if ( $is_dashboard ) {
      wp_safe_redirect( home_url( '/login/' ), 302 );
      exit;
    }

    // Allow /my-account/lost-password/ and reset key endpoints to work
    return;
  }

  // LOGGED IN
  // My Account base → Dashboard
  if ( $is_account && ! $is_wc_ep ) {
    wp_safe_redirect( home_url( '/dashboard/' ), 301 );
    exit;
  }

  // Already logged in and hits /login → Dashboard
  if ( $is_login ) {
    wp_safe_redirect( home_url( '/dashboard/' ), 302 );
    exit;
  }
});
  
/**
 * Subscriptions summary: ask backend/plugin; return normalized structure.
 *
 * @param int $user_id
 * @return array
 */
function artly_get_subscriptions( $user_id ) {
    $subs = apply_filters( 'artly_subscriptions_summary', null, $user_id );
    if ( is_array( $subs ) ) {
        return $subs;
    }

    // Try Nehtw Gateway subscriptions
    if ( function_exists( 'nehtw_gateway_get_user_active_subscription' ) ) {
        $sub = nehtw_gateway_get_user_active_subscription( $user_id );
        if ( $sub ) {
            return array(
                array(
                    'name'      => $sub['plan_name'] ?? __( 'Subscription', 'artly' ),
                    'status'    => $sub['status'] ?? 'active',
                    'renews_at' => $sub['next_renewal_at'] ?? date( 'Y-m-d', strtotime( '+1 month' ) ),
                    'plan'      => $sub['plan_key'] ?? '',
                    'amount'    => $sub['amount'] ?? 0,
                    'currency'  => $sub['currency'] ?? 'EGP',
                ),
            );
        }
    }

    return array(); // each: ['name','status','renews_at','plan','amount','currency']
}

/**
 * Utility: currency toggle (display only)
 *
 * @param float $amount_egp
 * @param float|null $usd_rate
 * @return string
 */
function artly_display_price( $amount_egp, $usd_rate = null ) {
    $show_usd = apply_filters( 'artly_show_usd_prices', false );
    if ( $show_usd && $usd_rate ) {
        return '$' . number_format( ( $amount_egp / $usd_rate ), 2 );
    }
    return number_format_i18n( $amount_egp, 2 ) . ' ' . __( 'EGP', 'artly' );
}