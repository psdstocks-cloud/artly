<?php
/**
 * Bilingual homepage helper functions for Artly theme
 * 
 * Handles language detection, locale switching, and asset enqueuing
 * for the bilingual homepage template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle language switching early (before headers are sent)
 * This runs on 'init' hook to set cookies before any output
 */
function artly_handle_language_switch() {
    // Check URL parameter first (highest priority)
    if ( isset( $_GET['lang'] ) ) {
        $lang = sanitize_text_field( $_GET['lang'] );
        if ( in_array( $lang, array( 'en', 'ar' ), true ) ) {
            artly_set_language_cookie( $lang );
        }
    }
}
add_action( 'init', 'artly_handle_language_switch', 1 );

/**
 * Get current language preference
 * 
 * Priority:
 * 1. URL parameter ?lang=en or ?lang=ar
 * 2. Cookie artly_lang
 * 3. Browser Accept-Language header
 * 4. Default to 'en'
 * 
 * @return string Language code ('en' or 'ar')
 */
function artly_get_current_language() {
    // Check URL parameter first (highest priority)
    if ( isset( $_GET['lang'] ) ) {
        $lang = sanitize_text_field( $_GET['lang'] );
        if ( in_array( $lang, array( 'en', 'ar' ), true ) ) {
            return $lang;
        }
    }
    
    // Check cookie
    if ( isset( $_COOKIE['artly_lang'] ) ) {
        $lang = sanitize_text_field( $_COOKIE['artly_lang'] );
        if ( in_array( $lang, array( 'en', 'ar' ), true ) ) {
            return $lang;
        }
    }
    
    // Check browser language
    if ( isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
        $browser_lang = substr( $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2 );
        if ( $browser_lang === 'ar' ) {
            return 'ar';
        }
    }
    
    // Default to English
    return 'en';
}

/**
 * Set language preference cookie
 * 
 * @param string $lang Language code ('en' or 'ar')
 * @return void
 */
function artly_set_language_cookie( $lang ) {
    if ( ! in_array( $lang, array( 'en', 'ar' ), true ) ) {
        return;
    }
    
    // Only set cookie if headers haven't been sent
    if ( ! headers_sent() ) {
        // Set cookie for 30 days
        setcookie( 'artly_lang', $lang, time() + ( 86400 * 30 ), '/', '', is_ssl(), true );
    }
    
    // Make available immediately in current request
    $_COOKIE['artly_lang'] = $lang;
}

/**
 * Switch WordPress locale based on language preference
 * 
 * @param string $locale Current locale
 * @return string Modified locale
 */
function artly_switch_locale( $locale ) {
    // Only apply on bilingual homepage template
    $is_bilingual_page = is_page_template( 'page-home-bilingual.php' ) 
        || is_page( 'bilingual-homepage' )
        || ( is_page() && get_page_template_slug() === 'page-home-bilingual.php' );
    
    if ( ! $is_bilingual_page ) {
        return $locale;
    }
    
    $lang = artly_get_current_language();
    
    if ( $lang === 'ar' ) {
        return 'ar'; // Arabic locale
    }
    
    return 'en_US'; // English locale (default)
}
add_filter( 'locale', 'artly_switch_locale' );

/**
 * Get language direction (LTR or RTL)
 * 
 * @return string 'ltr' or 'rtl'
 */
function artly_get_language_direction() {
    $lang = artly_get_current_language();
    return ( $lang === 'ar' ) ? 'rtl' : 'ltr';
}

/**
 * Enqueue language switcher on all pages
 */
function artly_enqueue_language_switcher() {
    $lang = artly_get_current_language();
    $theme_version = wp_get_theme()->get( 'Version' );
    
    // Language switcher (load on all pages)
    wp_enqueue_script(
        'artly-language-switcher',
        get_template_directory_uri() . '/assets/js/language-switcher.js',
        array( 'jquery' ),
        $theme_version,
        true
    );
    
    // Localize language switcher
    wp_localize_script( 'artly-language-switcher', 'artlyLang', array(
        'current'     => $lang,
        'direction'   => artly_get_language_direction(),
        'isLoggedIn'  => is_user_logged_in(),
        'userId'      => get_current_user_id(),
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'artly_lang_switch' ),
        'siteUrl'     => home_url( '/' ),
    ) );
}
add_action( 'wp_enqueue_scripts', 'artly_enqueue_language_switcher', 20 );

/**
 * Enqueue bilingual homepage assets
 * 
 * Only loads on the bilingual homepage template
 */
function artly_enqueue_bilingual_assets() {
    // Check if this is the bilingual homepage (by template or page slug)
    $is_bilingual_page = is_page_template( 'page-home-bilingual.php' ) 
        || is_page( 'bilingual-homepage' )
        || ( is_page() && get_page_template_slug() === 'page-home-bilingual.php' );
    
    if ( ! $is_bilingual_page ) {
        return;
    }
    
    $lang = artly_get_current_language();
    $theme_version = wp_get_theme()->get( 'Version' );
    
    // Ensure base styles are loaded first
    if ( ! wp_style_is( 'artly-style', 'enqueued' ) ) {
        wp_enqueue_style( 'artly-style', get_stylesheet_uri(), array(), $theme_version );
    }
    
    if ( ! wp_style_is( 'artly-layout', 'enqueued' ) ) {
        wp_enqueue_style(
            'artly-layout',
            get_template_directory_uri() . '/assets/css/layout.css',
            array( 'artly-style' ),
            $theme_version
        );
    }
    
    // Also load the base home.css for shared styles
    wp_enqueue_style(
        'artly-home-base',
        get_template_directory_uri() . '/assets/css/home.css',
        array( 'artly-style', 'artly-layout' ),
        $theme_version
    );
    
    // Bilingual homepage CSS
    wp_enqueue_style(
        'artly-home-bilingual',
        get_template_directory_uri() . '/assets/css/home-bilingual.css',
        array( 'artly-style', 'artly-layout', 'artly-home-base' ),
        $theme_version
    );
    
    // RTL CSS (only for Arabic)
    if ( $lang === 'ar' ) {
        wp_enqueue_style(
            'artly-rtl',
            get_template_directory_uri() . '/assets/css/rtl.css',
            array( 'artly-home-bilingual' ),
            $theme_version
        );
    }
    
    // GSAP Core (check if already loaded)
    if ( ! wp_script_is( 'gsap', 'enqueued' ) ) {
        wp_enqueue_script(
            'gsap',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
            array(),
            '3.12.5',
            true
        );
    }
    
    // GSAP ScrollTrigger (check if already loaded)
    if ( ! wp_script_is( 'gsap-scrolltrigger', 'enqueued' ) ) {
        wp_enqueue_script(
            'gsap-scrolltrigger',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js',
            array( 'gsap' ),
            '3.12.5',
            true
        );
    }
    
    // Home animations v2
    wp_enqueue_script(
        'artly-home-animations-v2',
        get_template_directory_uri() . '/assets/js/home-animations-v2.js',
        array( 'gsap', 'gsap-scrolltrigger' ),
        $theme_version,
        true
    );
    
    // Stats counter
    wp_enqueue_script(
        'artly-stats-counter',
        get_template_directory_uri() . '/assets/js/stats-counter.js',
        array( 'gsap', 'gsap-scrolltrigger' ),
        $theme_version,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'artly_enqueue_bilingual_assets', 21 );

/**
 * Add language-specific meta tags and hreflang
 */
function artly_bilingual_meta_tags() {
    // Only on bilingual homepage template
    $is_bilingual_page = is_page_template( 'page-home-bilingual.php' ) 
        || is_page( 'bilingual-homepage' )
        || ( is_page() && get_page_template_slug() === 'page-home-bilingual.php' );
    
    if ( ! $is_bilingual_page ) {
        return;
    }
    
    $lang = artly_get_current_language();
    $current_url = home_url( $_SERVER['REQUEST_URI'] );
    
    // Remove existing lang parameter
    $current_url = remove_query_arg( 'lang', $current_url );
    
    // Hreflang tags
    $en_url = add_query_arg( 'lang', 'en', $current_url );
    $ar_url = add_query_arg( 'lang', 'ar', $current_url );
    
    echo '<link rel="alternate" hreflang="en" href="' . esc_url( $en_url ) . '" />' . "\n";
    echo '<link rel="alternate" hreflang="ar" href="' . esc_url( $ar_url ) . '" />' . "\n";
    echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $en_url ) . '" />' . "\n";
    
    // Set HTML lang and dir attributes via JavaScript (since locale filter runs after head)
    echo '<script>
        document.documentElement.lang = "' . esc_js( $lang ) . '";
        document.documentElement.dir = "' . esc_js( artly_get_language_direction() ) . '";
    </script>' . "\n";
}
add_action( 'wp_head', 'artly_bilingual_meta_tags', 5 );

