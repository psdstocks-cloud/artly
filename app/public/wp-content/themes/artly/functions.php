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

        wp_enqueue_script(
            'pricing-woo',
            get_template_directory_uri() . '/assets/js/pricing-woo.js',
            array( 'artly-pricing' ),
            wp_get_theme()->get( 'Version' ),
            true
        );

        wp_localize_script(
            'pricing-woo',
            'wpApiSettings',
            array(
                'nonce' => wp_create_nonce( 'wp_rest' ),
            )
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

        // Localize script with PHP data
        wp_localize_script( 'artly-pricing', 'artlyPricingSettings', array(
            'isLoggedIn' => is_user_logged_in(),
            'userCurrency' => artly_detect_currency(),
            'homeUrl' => home_url(),
            'woocommerceProductId' => $woocommerce_product_id,
            'woocommerceActive' => class_exists( 'WooCommerce' ),
        ) );
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
 * Cinematic GSAP + Lottie setup for Artly home
 */
function artly_enqueue_cinematic_scripts() {
    if ( is_front_page() ) {
        // GSAP Core
        wp_enqueue_script(
            'gsap',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
            array(),
            null,
            true
        );

        // GSAP ScrollTrigger
        wp_enqueue_script(
            'scrolltrigger',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js',
            array( 'gsap' ),
            null,
            true
        );

        // GSAP TextPlugin (for text animations)
        wp_enqueue_script(
            'textplugin',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/TextPlugin.min.js',
            array( 'gsap' ),
            null,
            true
        );

        // Lottie
        wp_enqueue_script(
            'lottie',
            'https://cdnjs.cloudflare.com/ajax/libs/lottie-web/5.12.2/lottie.min.js',
            array(),
            null,
            true
        );

        // Our custom cinematic animations
        wp_enqueue_script(
            'artly-gsap-home',
            get_template_directory_uri() . '/assets/js/gsap-home.js',
            array( 'gsap', 'scrolltrigger', 'textplugin', 'lottie' ),
            wp_get_theme()->get( 'Version' ),
            true
        );

        // Home page animations (enhanced)
        wp_enqueue_script(
            'artly-home-animations',
            get_template_directory_uri() . '/assets/js/home-animations.js',
            array( 'gsap', 'scrolltrigger' ),
            wp_get_theme()->get( 'Version' ),
            true
        );

        // Conversion tracking
        wp_enqueue_script(
            'artly-conversion-tracking',
            get_template_directory_uri() . '/assets/js/conversion-tracking.js',
            array(),
            wp_get_theme()->get( 'Version' ),
            true
        );
    }
}
add_action( 'wp_enqueue_scripts', 'artly_enqueue_cinematic_scripts' );

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
    } elseif ( strlen( $password ) < 8 ) {
        $errors[] = 'Password must be at least 8 characters.';
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
 * Enqueue login page assets
 */
function artly_enqueue_login_assets() {
    if ( is_page_template( 'page-login.php' ) || is_page( 'login' ) ) {
        wp_enqueue_style(
            'artly-login',
            get_template_directory_uri() . '/assets/css/login.css',
            array( 'artly-style' ),
            wp_get_theme()->get( 'Version' )
        );
        // Note: wp_login_form() handles form submission natively, no custom JS needed
    }
}
add_action( 'wp_enqueue_scripts', 'artly_enqueue_login_assets' );

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
    $redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( $_POST['redirect_to'] ) : home_url( '/my-downloads/' );
    
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
 */
function artly_custom_login_url( $login_url, $redirect, $force_reauth ) {
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