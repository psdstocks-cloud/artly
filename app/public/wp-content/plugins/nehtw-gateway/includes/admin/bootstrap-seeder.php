<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get all supported sites with their regex patterns and default points
 */
function nehtw_get_all_sites_config() {
    return array(
        // Shutterstock variants
        array(
            'site_key'        => 'shutterstock',
            'label'           => 'Shutterstock',
            'regex_pattern'   => '/shutterstock\.com\/(.*)(image-vector|image-photo|image-illustration|image|image-generated|editorial)\/([0-9a-zA-Z-_]*)-([0-9a-z]*)/',
            'points_per_file' => 10,
        ),
        array(
            'site_key'        => 'vshutter',
            'label'           => 'Shutterstock Video',
            'regex_pattern'   => '/shutterstock\.com(|\/[a-z]*)\/video\/clip-([0-9]*)/',
            'points_per_file' => 10,
        ),
        array(
            'site_key'        => 'mshutter',
            'label'           => 'Shutterstock Music',
            'regex_pattern'   => '/shutterstock\.com(.*)music\/(.*)track-([0-9]*)-/',
            'points_per_file' => 10,
        ),
        // Adobe Stock - multiple patterns, using most comprehensive
        array(
            'site_key'        => 'adobestock',
            'label'           => 'Adobe Stock',
            'regex_pattern'   => '/stock\.adobe\.com\/(..\/||.....\/)(images|templates|3d-assets|stock-photo|video)\/([a-zA-Z0-9-%.,]*)\/([0-9]*)/',
            'points_per_file' => 10,
        ),
        // Depositphotos - multiple patterns
        array(
            'site_key'        => 'depositphotos',
            'label'           => 'Depositphotos',
            'regex_pattern'   => '/depositphotos\.com\/([0-9]*)\/(stock-photo|stock-illustration|free-stock)(.*)/',
            'points_per_file' => 8,
        ),
        array(
            'site_key'        => 'depositphotos_video',
            'label'           => 'Depositphotos Video',
            'regex_pattern'   => '/depositphotos\.com\/([0-9]*)\/stock-video(.*)/',
            'points_per_file' => 10,
        ),
        // 123RF
        array(
            'site_key'        => '123rf',
            'label'           => '123RF',
            'regex_pattern'   => '/123rf\.com\/(photo|free-photo)_([0-9]*)_/',
            'points_per_file' => 8,
        ),
        // iStockphoto / Getty Images
        array(
            'site_key'        => 'istockphoto',
            'label'           => 'iStockphoto',
            'regex_pattern'   => '/istockphoto\.com\/(.*)gm([0-9A-Z_]*)-/',
            'points_per_file' => 10,
        ),
        // Freepik variants
        array(
            'site_key'        => 'freepik',
            'label'           => 'Freepik',
            'regex_pattern'   => '/freepik\.(.*)(.*)_([0-9]*).htm/',
            'points_per_file' => 6,
        ),
        array(
            'site_key'        => 'vfreepik',
            'label'           => 'Freepik Video',
            'regex_pattern'   => '/freepik.(.*)\/(.*)-?video-?(.*)\/([0-9a-z-]*)_([0-9]*)/',
            'points_per_file' => 8,
        ),
        // Flaticon
        array(
            'site_key'        => 'flaticon',
            'label'           => 'Flaticon',
            'regex_pattern'   => '/flaticon.com\/(.*)\/([0-9a-z-]*)_([0-9]*)/',
            'points_per_file' => 5,
        ),
        array(
            'site_key'        => 'flaticonpack',
            'label'           => 'Flaticon Pack',
            'regex_pattern'   => '/flaticon.com\/(.*)(packs|stickers-pack)\/([0-9a-z-]*)/',
            'points_per_file' => 10,
        ),
        // Envato Elements
        array(
            'site_key'        => 'envato',
            'label'           => 'Envato Elements',
            'regex_pattern'   => '/elements\.envato\.com(.*)\/([0-9a-zA-Z-]*)-([0-9A-Z]*)/',
            'points_per_file' => 10,
        ),
        // Dreamstime
        array(
            'site_key'        => 'dreamstime',
            'label'           => 'Dreamstime',
            'regex_pattern'   => '/dreamstime(.*)-image([0-9]*)/',
            'points_per_file' => 8,
        ),
        // PNGTree
        array(
            'site_key'        => 'pngtree',
            'label'           => 'PNGTree',
            'regex_pattern'   => '/pngtree\.com(.*)_([0-9]*).html/',
            'points_per_file' => 6,
        ),
        // VectorStock
        array(
            'site_key'        => 'vectorstock',
            'label'           => 'VectorStock',
            'regex_pattern'   => '/vectorstock.com\/([0-9a-zA-Z-]*)\/([0-9a-zA-Z-]*)-([0-9]*)/',
            'points_per_file' => 8,
        ),
        // MotionArray
        array(
            'site_key'        => 'motionarray',
            'label'           => 'MotionArray',
            'regex_pattern'   => '/motionarray.com\/([a-zA-Z0-9-]*)\/([a-zA-Z0-9-]*)-([0-9]*)/',
            'points_per_file' => 10,
        ),
        // Alamy
        array(
            'site_key'        => 'alamy',
            'label'           => 'Alamy',
            'regex_pattern'   => '/(alamy|alamyimages)\.(com|es|de|it|fr)\/(.*)(-|image)([0-9]*).html/',
            'points_per_file' => 10,
        ),
        // Motion Elements
        array(
            'site_key'        => 'motionelements',
            'label'           => 'Motion Elements',
            'regex_pattern'   => '/motionelements\.com\/(([a-z-]*\/)|)(([a-z-3]*)|(product|davinci-resolve-template))(\/|-)([0-9]*)-/',
            'points_per_file' => 10,
        ),
        // Storyblocks
        array(
            'site_key'        => 'storyblocks',
            'label'           => 'Storyblocks',
            'regex_pattern'   => '/storyblocks\.com\/(video|images|audio)\/stock\/([0-9a-z-]*)-([0-9a-z_]*)/',
            'points_per_file' => 10,
        ),
        // Epidemic Sound
        array(
            'site_key'        => 'epidemicsound',
            'label'           => 'Epidemic Sound',
            'regex_pattern'   => '/epidemicsound.com\/(.*)tracks?\/([a-zA-Z0-9-]*)/',
            'points_per_file' => 8,
        ),
        // Yellow Images
        array(
            'site_key'        => 'yellowimages',
            'label'           => 'Yellow Images',
            'regex_pattern'   => '/yellowimages\.com\/(stock\/|(.*)p=)([0-9a-z-]*)/',
            'points_per_file' => 8,
        ),
        // Vecteezy
        array(
            'site_key'        => 'vecteezy',
            'label'           => 'Vecteezy',
            'regex_pattern'   => '/vecteezy.com\/([\/a-zA-Z-]*)\/([0-9]*)/',
            'points_per_file' => 6,
        ),
        // Creative Fabrica
        array(
            'site_key'        => 'creativefabrica',
            'label'           => 'Creative Fabrica',
            'regex_pattern'   => '/creativefabrica.com\/(.*)product\/([a-z0-9-]*)/',
            'points_per_file' => 8,
        ),
        // Lovepik
        array(
            'site_key'        => 'lovepik',
            'label'           => 'Lovepik',
            'regex_pattern'   => '/lovepik.com\/([a-z]*)-([0-9]*)\//',
            'points_per_file' => 6,
        ),
        // Rawpixel
        array(
            'site_key'        => 'rawpixel',
            'label'           => 'Rawpixel',
            'regex_pattern'   => '/rawpixel\.com\/image\/([0-9]*)/',
            'points_per_file' => 8,
        ),
        // DeeEzy
        array(
            'site_key'        => 'deeezy',
            'label'           => 'DeeEzy',
            'regex_pattern'   => '/deeezy\.com\/product\/([0-9]*)/',
            'points_per_file' => 8,
        ),
        // FootageCrate / ProductionCrate / GraphicsCrate
        array(
            'site_key'        => 'footagecrate',
            'label'           => 'FootageCrate',
            'regex_pattern'   => '/(productioncrate|footagecrate|graphicscrate)\.com\/([a-z0-9-]*)\/([a-zA-Z0-9-_]*)/',
            'points_per_file' => 8,
        ),
        // Artgrid
        array(
            'site_key'        => 'artgrid_HD',
            'label'           => 'Artgrid',
            'regex_pattern'   => '/artgrid\.io\/clip\/([0-9]*)\//',
            'points_per_file' => 10,
        ),
        // PixelSquid
        array(
            'site_key'        => 'pixelsquid',
            'label'           => 'PixelSquid',
            'regex_pattern'   => '/pixelsquid.com(.*)-([0-9]*)/',
            'points_per_file' => 10,
        ),
        // UI8
        array(
            'site_key'        => 'ui8',
            'label'           => 'UI8',
            'regex_pattern'   => '/ui8\.net\/(.*)\/(.*)\/([0-9a-zA-Z-]*)/',
            'points_per_file' => 8,
        ),
        // IconScout
        array(
            'site_key'        => 'iconscout',
            'label'           => 'IconScout',
            'regex_pattern'   => '/iconscout.com\/((\w{2})\/?$|(\w{2})\/|)([0-9a-z-]*)\/([0-9a-z-_]*)/',
            'points_per_file' => 6,
        ),
        // Designi
        array(
            'site_key'        => 'designi',
            'label'           => 'Designi',
            'regex_pattern'   => '/designi.com.br\/([0-9a-zA-Z]*)/',
            'points_per_file' => 6,
        ),
        // MockupCloud
        array(
            'site_key'        => 'mockupcloud',
            'label'           => 'MockupCloud',
            'regex_pattern'   => '/mockupcloud.com\/(product|scene|graphics\/product)\/([a-z0-9-]*)/',
            'points_per_file' => 8,
        ),
        // Artlist
        array(
            'site_key'        => 'artlist_footage',
            'label'           => 'Artlist Footage',
            'regex_pattern'   => '/artlist.io\/(stock-footage|video-templates)\/(.*)\/([0-9]*)/',
            'points_per_file' => 10,
        ),
        array(
            'site_key'        => 'artlist_sound',
            'label'           => 'Artlist Sound',
            'regex_pattern'   => '/artlist.io\/(sfx|royalty-free-music)\/(.*)\/([0-9]*)/',
            'points_per_file' => 8,
        ),
        // Pixeden
        array(
            'site_key'        => 'pixeden',
            'label'           => 'Pixeden',
            'regex_pattern'   => '/pixeden.com\/([0-9a-z-]*)\/([0-9a-z-]*)/',
            'points_per_file' => 8,
        ),
        // UIHut
        array(
            'site_key'        => 'uihut',
            'label'           => 'UIHut',
            'regex_pattern'   => '/uihut.com\/designs\/([0-9]*)/',
            'points_per_file' => 6,
        ),
        // Craftwork
        array(
            'site_key'        => 'craftwork',
            'label'           => 'Craftwork',
            'regex_pattern'   => '/craftwork.design\/product\/([0-9a-z-]*)/',
            'points_per_file' => 8,
        ),
        // Baixar Design
        array(
            'site_key'        => 'baixardesign',
            'label'           => 'Baixar Design',
            'regex_pattern'   => '/baixardesign.com.br\/arquivo\/([0-9a-z]*)/',
            'points_per_file' => 6,
        ),
        // Soundstripe
        array(
            'site_key'        => 'soundstripe',
            'label'           => 'Soundstripe',
            'regex_pattern'   => '/soundstripe.com\/(.*)\/([0-9]*)/',
            'points_per_file' => 8,
        ),
        // MrMockup
        array(
            'site_key'        => 'mrmockup',
            'label'           => 'MrMockup',
            'regex_pattern'   => '/mrmockup.com\/product\/([0-9a-z-]*)/',
            'points_per_file' => 8,
        ),
        // DesignBR
        array(
            'site_key'        => 'designbr',
            'label'           => 'DesignBR',
            'regex_pattern'   => '/designbr\.com\.br\/(.*)modal=([^&]+)/',
            'points_per_file' => 6,
        ),
        // UpLabs
        array(
            'site_key'        => 'uplabs',
            'label'           => 'UpLabs',
            'regex_pattern'   => '/uplabs.com\/posts\/([0-9a-z-]*)/',
            'points_per_file' => 6,
        ),
        // PixelBuddha
        array(
            'site_key'        => 'pixelbuddha',
            'label'           => 'PixelBuddha',
            'regex_pattern'   => '/pixelbuddha.net\/(premium|)(.*)\/([0-9a-z-]*)/',
            'points_per_file' => 8,
        ),
    );
}

/**
 * Seed sites if table is empty
 */
function nehtw_seed_sites_if_empty() {
    global $wpdb;
    $table = $wpdb->prefix . 'nehtw_sites';
    
    // Check if table exists
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
    if ( ! $table_exists ) {
        return;
    }
    
    $has_rows = $wpdb->get_var( "SELECT COUNT(1) FROM {$table}" );

    if ( $has_rows ) {
        return;
    }

    $now = current_time( 'mysql' );
    $sites = nehtw_get_all_sites_config();

    foreach ( $sites as $site ) {
        $site['status'] = 'active';
        $site['created_at'] = $now;
        $site['updated_at'] = $now;
        $wpdb->insert( $table, $site );
    }
}
add_action( 'admin_init', 'nehtw_seed_sites_if_empty' );

/**
 * Sync/update all sites with latest patterns (adds missing sites, updates patterns)
 */
function nehtw_sync_all_sites() {
    global $wpdb;
    $table = $wpdb->prefix . 'nehtw_sites';
    
    // Check if table exists
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
    if ( ! $table_exists ) {
        return false;
    }
    
    $now = current_time( 'mysql' );
    $sites = nehtw_get_all_sites_config();
    $existing_sites = $wpdb->get_results( "SELECT site_key FROM {$table}", OBJECT_K );
    
    foreach ( $sites as $site ) {
        $site_key = $site['site_key'];
        
        if ( isset( $existing_sites[ $site_key ] ) ) {
            // Update existing site (only regex_pattern if it changed)
            $wpdb->update(
                $table,
                array(
                    'regex_pattern' => $site['regex_pattern'],
                    'updated_at'    => $now,
                ),
                array( 'site_key' => $site_key ),
                array( '%s', '%s' ),
                array( '%s' )
            );
        } else {
            // Insert new site
            $site['status'] = 'active';
            $site['url'] = isset( $site['url'] ) ? $site['url'] : null;
            $site['created_at'] = $now;
            $site['updated_at'] = $now;
            $wpdb->insert( $table, $site );
        }
    }
    
    // Clear cache
    delete_transient( 'nehtw_sites_cache' );
    
    return true;
}
