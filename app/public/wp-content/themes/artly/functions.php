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
 * Honeypot and nonce validation for the custom login form.
 */
function artly_login_security_checks( $user, $username, $password ) {
    if ( empty( $_POST['artly_login_submission'] ) ) {
        return $user;
    }

    $honeypot = isset( $_POST['artly_login_hp'] ) ? trim( wp_unslash( $_POST['artly_login_hp'] ) ) : '';
    if ( '' !== $honeypot ) {
        return new WP_Error( 'artly_spam', __( 'Invalid email or password.', 'artly' ) );
    }

    $nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'artly_login' ) ) {
        return new WP_Error( 'artly_nonce', __( 'Security check failed. Please try again.', 'artly' ) );
    }

    return $user;
}
add_filter( 'authenticate', 'artly_login_security_checks', 5, 3 );

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

// === 3. Load assets only on My Account ===
add_action( 'wp_enqueue_scripts', function () {
    if ( is_account_page() ) {
        wp_enqueue_style(
            'artly-account',
            get_stylesheet_directory_uri() . '/assets/css/artly-account.css',
            array(),
            wp_get_theme()->get( 'Version' )
        );

        wp_enqueue_script(
            'artly-account',
            get_stylesheet_directory_uri() . '/assets/js/artly-account.js',
            array( 'jquery' ),
            wp_get_theme()->get( 'Version' ),
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
} );

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
    if (function_exists('is_account_page') && is_account_page() && !is_user_logged_in()) {
  
      // If we are on a Woo endpoint like /my-account/lost-password/ or /reset-password/
      if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url()) {
        // Do NOT redirect endpoints (lost password, reset, etc.)
        return;
      }
  
      // We are on the base /my-account/ and user is not logged in -> send to /login
      $login_url = home_url('/login/');
      if (!is_page_template('page-login.php')) {
        wp_safe_redirect($login_url, 301);
        exit;
      }
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