<?php
/**
 * Stock ordering helper functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Return supported stock sites and their point costs.
 *
 * This is INTERNAL ONLY and NOT printed directly to users.
 * It will be used by both backend and theme template.
 *
 * @return array[] {
 *   @type string $key       Internal site key for API (e.g. 'shutterstock').
 *   @type string $label     Human label (e.g. 'Shutterstock').
 *   @type float  $points    Points cost per single download.
 * }
 */
function nehtw_gateway_get_stock_sites_config() {
    $sites = get_option( 'nehtw_gateway_stock_sites', array() );

    // Provide comprehensive defaults if option is empty.
    if ( ! is_array( $sites ) || empty( $sites ) ) {
        $sites = array(
            'motionelements' => array(
                'key'     => 'motionelements',
                'label'   => 'Motion Elements',
                'points'  => 0.0,
                'enabled' => false,
                'url'     => 'https://www.motionelements.com',
                'domains' => array( 'motionelements.com' ),
            ),
            'adobestock' => array(
                'key'     => 'adobestock',
                'label'   => 'Adobe Stock',
                'points'  => 0.25,
                'enabled' => true,
                'url'     => 'https://stock.adobe.com',
                'domains' => array( 'stock.adobe.com', 'adobestock.com' ),
            ),
            'pixelbuddha' => array(
                'key'     => 'pixelbuddha',
                'label'   => 'Pixel Buddha',
                'points'  => 0.4,
                'enabled' => true,
                'url'     => 'https://pixelbuddha.net',
                'domains' => array( 'pixelbuddha.net' ),
            ),
            'iconscout' => array(
                'key'     => 'iconscout',
                'label'   => 'IconScout',
                'points'  => 0.2,
                'enabled' => true,
                'url'     => 'https://iconscout.com',
                'domains' => array( 'iconscout.com' ),
            ),
            'mockupcloud' => array(
                'key'     => 'mockupcloud',
                'label'   => 'Mockup Cloud',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://www.mockupcloud.com',
                'domains' => array( 'mockupcloud.com' ),
            ),
            'ui8' => array(
                'key'     => 'ui8',
                'label'   => 'UI8',
                'points'  => 2.0,
                'enabled' => true,
                'url'     => 'https://ui8.net',
                'domains' => array( 'ui8.net' ),
            ),
            'pixeden' => array(
                'key'     => 'pixeden',
                'label'   => 'Pixeden',
                'points'  => 0.4,
                'enabled' => true,
                'url'     => 'https://www.pixeden.com',
                'domains' => array( 'pixeden.com' ),
            ),
            'creativefabrica' => array(
                'key'     => 'creativefabrica',
                'label'   => 'Creative Fabrica',
                'points'  => 0.4,
                'enabled' => true,
                'url'     => 'https://www.creativefabrica.com',
                'domains' => array( 'creativefabrica.com' ),
            ),
            'envato' => array(
                'key'     => 'envato',
                'label'   => 'Envato Elements',
                'points'  => 0.35,
                'enabled' => true,
                'url'     => 'https://elements.envato.com',
                'domains' => array( 'elements.envato.com', 'envato.com' ),
            ),
            'vectorstock' => array(
                'key'     => 'vectorstock',
                'label'   => 'VectorStock',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://www.vectorstock.com',
                'domains' => array( 'vectorstock.com' ),
            ),
            'dreamstime' => array(
                'key'     => 'dreamstime',
                'label'   => 'Dreamstime',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://www.dreamstime.com',
                'domains' => array( 'dreamstime.com' ),
            ),
            'storyblocks' => array(
                'key'     => 'storyblocks',
                'label'   => 'Storyblocks',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://www.storyblocks.com',
                'domains' => array( 'storyblocks.com' ),
            ),
            '123rf' => array(
                'key'     => '123rf',
                'label'   => '123RF',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://www.123rf.com',
                'domains' => array( '123rf.com' ),
            ),
            'vecteezy' => array(
                'key'     => 'vecteezy',
                'label'   => 'Vecteezy',
                'points'  => 0.3,
                'enabled' => true,
                'url'     => 'https://www.vecteezy.com',
                'domains' => array( 'vecteezy.com' ),
            ),
            'rawpixel' => array(
                'key'     => 'rawpixel',
                'label'   => 'Rawpixel',
                'points'  => 0.4,
                'enabled' => true,
                'url'     => 'https://www.rawpixel.com',
                'domains' => array( 'rawpixel.com' ),
            ),
            'freepik' => array(
                'key'     => 'freepik',
                'label'   => 'Freepik',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://www.freepik.com',
                'domains' => array( 'freepik.com' ),
            ),
            'vfreepik' => array(
                'key'     => 'vfreepik',
                'label'   => 'Freepik Video',
                'points'  => 1.0,
                'enabled' => true,
                'url'     => 'https://www.freepik.com',
                'domains' => array( 'freepik.com' ),
            ),
            'vshutter' => array(
                'key'     => 'vshutter',
                'label'   => 'Shutterstock Video',
                'points'  => 2.0,
                'enabled' => true,
                'url'     => 'https://www.shutterstock.com/video',
                'domains' => array( 'shutterstock.com' ),
            ),
            'mshutter' => array(
                'key'     => 'mshutter',
                'label'   => 'Shutterstock Music',
                'points'  => 1.5,
                'enabled' => true,
                'url'     => 'https://www.shutterstock.com/music',
                'domains' => array( 'shutterstock.com' ),
            ),
            'depositphotos_video' => array(
                'key'     => 'depositphotos_video',
                'label'   => 'Depositphotos Video',
                'points'  => 1.0,
                'enabled' => true,
                'url'     => 'https://depositphotos.com',
                'domains' => array( 'depositphotos.com' ),
            ),
            'flaticonpack' => array(
                'key'     => 'flaticonpack',
                'label'   => 'Flaticon Pack',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://www.flaticon.com',
                'domains' => array( 'flaticon.com' ),
            ),
            'istockphoto' => array(
                'key'     => 'istockphoto',
                'label'   => 'iStock / Getty Images',
                'points'  => 1.0,
                'enabled' => true,
                'url'     => 'https://www.istockphoto.com',
                'domains' => array( 'istockphoto.com', 'gettyimages.com' ),
            ),
            'pngtree' => array(
                'key'     => 'pngtree',
                'label'   => 'PNGTree',
                'points'  => 0.3,
                'enabled' => true,
                'url'     => 'https://pngtree.com',
                'domains' => array( 'pngtree.com' ),
            ),
            'lovepik' => array(
                'key'     => 'lovepik',
                'label'   => 'Lovepik',
                'points'  => 0.3,
                'enabled' => true,
                'url'     => 'https://lovepik.com',
                'domains' => array( 'lovepik.com' ),
            ),
            'uplabs' => array(
                'key'     => 'uplabs',
                'label'   => 'UpLabs',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://www.uplabs.com',
                'domains' => array( 'uplabs.com' ),
            ),
            'uihut' => array(
                'key'     => 'uihut',
                'label'   => 'UIHut',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://uihut.com',
                'domains' => array( 'uihut.com' ),
            ),
            'mrmockup' => array(
                'key'     => 'mrmockup',
                'label'   => 'Mr Mockup',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://mrmockup.com',
                'domains' => array( 'mrmockup.com' ),
            ),
            'designbr' => array(
                'key'     => 'designbr',
                'label'   => 'Design BR',
                'points'  => 0.3,
                'enabled' => true,
                'url'     => 'https://designbr.com.br',
                'domains' => array( 'designbr.com.br' ),
            ),
            'designi' => array(
                'key'     => 'designi',
                'label'   => 'Designi',
                'points'  => 0.3,
                'enabled' => true,
                'url'     => 'https://designi.com.br',
                'domains' => array( 'designi.com.br' ),
            ),
            'baixardesign' => array(
                'key'     => 'baixardesign',
                'label'   => 'Baixar Design',
                'points'  => 0.3,
                'enabled' => true,
                'url'     => 'https://baixardesign.com.br',
                'domains' => array( 'baixardesign.com.br' ),
            ),
            'shutterstock' => array(
                'key'     => 'shutterstock',
                'label'   => 'Shutterstock',
                'points'  => 1.0,
                'enabled' => true,
                'url'     => 'https://www.shutterstock.com',
                'domains' => array( 'shutterstock.com' ),
            ),
            'ss_video_hd' => array(
                'key'     => 'ss_video_hd',
                'label'   => 'Shutterstock Video HD',
                'points'  => 2.0,
                'enabled' => true,
                'url'     => 'https://www.shutterstock.com/video',
                'domains' => array( 'shutterstock.com' ),
            ),
            'ss_video_4k' => array(
                'key'     => 'ss_video_4k',
                'label'   => 'Shutterstock Video 4K',
                'points'  => 3.0,
                'enabled' => true,
                'url'     => 'https://www.shutterstock.com/video',
                'domains' => array( 'shutterstock.com' ),
            ),
            'ss_music' => array(
                'key'     => 'ss_music',
                'label'   => 'Shutterstock Music',
                'points'  => 1.5,
                'enabled' => true,
                'url'     => 'https://www.shutterstock.com/music',
                'domains' => array( 'shutterstock.com' ),
            ),
            'flaticon' => array(
                'key'     => 'flaticon',
                'label'   => 'Flaticon',
                'points'  => 0.3,
                'enabled' => true,
                'url'     => 'https://www.flaticon.com',
                'domains' => array( 'flaticon.com' ),
            ),
            'craftwork' => array(
                'key'     => 'craftwork',
                'label'   => 'Craftwork',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://craftwork.design',
                'domains' => array( 'craftwork.design' ),
            ),
            'alamy' => array(
                'key'     => 'alamy',
                'label'   => 'Alamy',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://www.alamy.com',
                'domains' => array( 'alamy.com' ),
            ),
            'motionarray' => array(
                'key'     => 'motionarray',
                'label'   => 'Motion Array',
                'points'  => 1.0,
                'enabled' => true,
                'url'     => 'https://motionarray.com',
                'domains' => array( 'motionarray.com' ),
            ),
            'soundstripe' => array(
                'key'     => 'soundstripe',
                'label'   => 'Soundstripe',
                'points'  => 1.0,
                'enabled' => true,
                'url'     => 'https://www.soundstripe.com',
                'domains' => array( 'soundstripe.com' ),
            ),
            'yellowimages' => array(
                'key'     => 'yellowimages',
                'label'   => 'Yellow Images',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://yellowimages.com',
                'domains' => array( 'yellowimages.com' ),
            ),
            'depositphotos' => array(
                'key'     => 'depositphotos',
                'label'   => 'Depositphotos',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://depositphotos.com',
                'domains' => array( 'depositphotos.com' ),
            ),
            'artlist_sound' => array(
                'key'     => 'artlist_sound',
                'label'   => 'Artlist Music & SFX',
                'points'  => 1.5,
                'enabled' => true,
                'url'     => 'https://artlist.io',
                'domains' => array( 'artlist.io' ),
            ),
            'artlist_footage' => array(
                'key'     => 'artlist_footage',
                'label'   => 'Artlist Video & Templates',
                'points'  => 2.0,
                'enabled' => true,
                'url'     => 'https://artlist.io',
                'domains' => array( 'artlist.io' ),
            ),
            'epidemicsound' => array(
                'key'     => 'epidemicsound',
                'label'   => 'Epidemic Sound',
                'points'  => 1.5,
                'enabled' => true,
                'url'     => 'https://www.epidemicsound.com',
                'domains' => array( 'epidemicsound.com' ),
            ),
            'artgrid_HD' => array(
                'key'     => 'artgrid_HD',
                'label'   => 'Artgrid HD',
                'points'  => 1.5,
                'enabled' => true,
                'url'     => 'https://artgrid.io',
                'domains' => array( 'artgrid.io' ),
            ),
            'deeezy' => array(
                'key'     => 'deeezy',
                'label'   => 'Deezzy',
                'points'  => 0.3,
                'enabled' => true,
                'url'     => 'https://www.deeezy.com',
                'domains' => array( 'deeezy.com' ),
            ),
            'pixelsquid' => array(
                'key'     => 'pixelsquid',
                'label'   => 'PixelSquid',
                'points'  => 1.0,
                'enabled' => true,
                'url'     => 'https://www.pixelsquid.com',
                'domains' => array( 'pixelsquid.com' ),
            ),
            'footagecrate' => array(
                'key'     => 'footagecrate',
                'label'   => 'FootageCrate',
                'points'  => 0.5,
                'enabled' => true,
                'url'     => 'https://footagecrate.com',
                'domains' => array( 'footagecrate.com' ),
            ),
            'freepik_video' => array(
                'key'     => 'freepik_video',
                'label'   => 'Freepik Video',
                'points'  => 1.0,
                'enabled' => true,
                'url'     => 'https://www.freepik.com',
                'domains' => array( 'freepik.com' ),
            ),
        );
    }

    // Ensure all sites have required fields
    foreach ( $sites as $key => &$site ) {
        if ( ! isset( $site['key'] ) ) {
            $site['key'] = $key;
        }
        if ( ! isset( $site['enabled'] ) ) {
            $site['enabled'] = true;
        }
        if ( ! isset( $site['domains'] ) || ! is_array( $site['domains'] ) ) {
            $site['domains'] = array();
        }
        if ( ! isset( $site['url'] ) ) {
            $site['url'] = '';
        }
    }

    /**
     * Optional filter if needed elsewhere.
     */
    return apply_filters( 'nehtw_gateway_stock_sites_config', $sites );
}

/**
 * PHP equivalent of the JS idMapping helper.
 *
 * @param string   $source Source key.
 * @param string[] $arr    Array of values to join.
 * @return string
 */
function nehtw_gateway_stock_id_mapping( $source, $arr ) {
    // For our purposes we just join the parts with a dash.
    // This matches what the backend expects.
    return implode( '-', $arr );
}

/**
 * PHP equivalent of the JS idExtractor.
 *
 * @param string $url
 * @return array|false { 'source' => 'shutterstock', 'id' => '2365327491', 'url' => '...' } or false
 */
function nehtw_gateway_stock_id_extractor( $url ) {
    $url = trim( (string) $url );

    if ( '' === $url ) {
        return false;
    }

    $patterns = array(
        array(
            'source' => 'vshutter',
            'regex'  => '/shutterstock\.com(|\/[a-z]*)\/video\/clip-([0-9]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => 'mshutter',
            'regex'  => '/shutterstock\.com(.*)music\/(.*)track-([0-9]*)-/i',
            'id'     => 3,
        ),
        array(
            'source' => 'shutterstock',
            'regex'  => '/shutterstock\.com\/(.*)(image-vector|image-photo|image-illustration|image|image-generated|editorial)\/([0-9a-zA-Z-_]*)-([0-9a-z]*)/i',
            'id'     => 4,
        ),
        array(
            'source' => 'shutterstock',
            'regex'  => '/shutterstock\.com\/(.*)(image-vector|image-photo|image-illustration|image-generated|editorial)\/([0-9a-z]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'adobestock',
            'regex'  => '/stock\.adobe\.com\/(..\/||.....\/)(images|templates|3d-assets|stock-photo|video)\/([a-zA-Z0-9-%.,]*)\/([0-9]*)/i',
            'id'     => 4,
        ),
        array(
            'source' => 'adobestock',
            'regex'  => '/stock\.adobe\.com(.*)asset_id=([0-9]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => 'adobestock',
            'regex'  => '/stock\.adobe\.com\/(.*)search\/audio\?(k|keywords)=([0-9]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'adobestock',
            'regex'  => '/stock\.adobe\.com\/(..\/||.....\/)([0-9]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => 'depositphotos',
            'regex'  => '/depositphotos\.com(.*)depositphotos_([0-9]*)(.*)\.jpg/i',
            'id'     => 2,
        ),
        array(
            'source' => 'depositphotos_video',
            'regex'  => '/depositphotos\.com\/([0-9]*)\/stock-video(.*)/i',
            'id'     => 1,
        ),
        array(
            'source' => 'depositphotos',
            'regex'  => '/depositphotos\.com\/([0-9]*)\/(stock-photo|stock-illustration|free-stock)(.*)/i',
            'id'     => 1,
        ),
        array(
            'source' => 'depositphotos',
            'regex'  => '/depositphotos\.com(.*)qview=([0-9]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => 'depositphotos',
            'regex'  => '/depositphotos\.com(.*)\/(photo|editorial|vector|illustration)\/([0-9a-z-]*)-([0-9]*)/i',
            'id'     => 4,
        ),
        array(
            'source' => '123rf',
            'regex'  => '/123rf\.com\/(photo|free-photo)_([0-9]*)_/i',
            'id'     => 2,
        ),
        array(
            'source' => '123rf',
            'regex'  => '/123rf\.com\/(.*)mediapopup=([0-9]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => '123rf',
            'regex'  => '/123rf\.com\/stock-photo\/([0-9]*)\.html/i',
            'id'     => 1,
        ),
        array(
            'source' => 'istockphoto',
            'regex'  => '/istockphoto\.com\/(.*)gm([0-9A-Z_]*)-/i',
            'id'     => 2,
        ),
        array(
            'source' => 'istockphoto',
            'regex'  => '/gettyimages\.com\/(.*)\/([0-9]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => 'vfreepik',
            'regex'  => '/freepik\.(.*)\/(.*)-?video-?(.*)\/([0-9a-z-]*)_([0-9]*)/i',
            'id'     => 5,
        ),
        array(
            'source' => 'freepik',
            'regex'  => '/freepik\.(.*)(.*)_([0-9]*)\.htm/i',
            'id'     => 3,
        ),
        array(
            'source' => 'flaticon',
            'regex'  => '/freepik\.com\/(icon|icone)\/(.*)_([0-9]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'flaticon',
            'regex'  => '/flaticon\.com\/(.*)\/([0-9a-z-]*)_([0-9]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'flaticonpack',
            'regex'  => '/flaticon\.com\/(.*)(packs|stickers-pack)\/([0-9a-z-]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'envato',
            'regex'  => '/elements\.envato\.com(.*)\/([0-9a-zA-Z-]*)-([0-9A-Z]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'dreamstime',
            'regex'  => '/dreamstime(.*)-image([0-9]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => 'pngtree',
            'regex'  => '/pngtree\.com(.*)_([0-9]*)\.html/i',
            'id'     => 2,
        ),
        array(
            'source' => 'vectorstock',
            'regex'  => '/vectorstock\.com\/([0-9a-zA-Z-]*)\/([0-9a-zA-Z-]*)-([0-9]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'motionarray',
            'regex'  => '/motionarray\.com\/([a-zA-Z0-9-]*)\/([a-zA-Z0-9-]*)-([0-9]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'alamy',
            'regex'  => '/(alamy|alamyimages)\.(com|es|de|it|fr)\/(.*)(-|image)([0-9]*)\.html/i',
            'id'     => 5,
        ),
        array(
            'source' => 'motionelements',
            'regex'  => '/motionelements\.com\/(([a-z-]*\/)|)(([a-z-3]*)|(product|davinci-resolve-template))(\/|-)([0-9]*)-/i',
            'callback' => function( $matches ) {
                $arr = array();
                if ( isset( $matches[3] ) && $matches[3] ) {
                    $arr[] = $matches[3];
                }
                if ( isset( $matches[7] ) && $matches[7] ) {
                    $arr[] = $matches[7];
                }
                return nehtw_gateway_stock_id_mapping( 'motionelements', $arr );
            },
        ),
        array(
            'source' => 'storyblocks',
            'regex'  => '/storyblocks\.com\/(video|images|audio)\/stock\/([0-9a-z-]*)-([0-9a-z_]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'epidemicsound',
            'regex'  => '/epidemicsound\.com\/(.*)tracks?\/([a-zA-Z0-9-]*)/i',
            'callback' => function( $matches ) {
                $arr = array();
                if ( isset( $matches[1] ) && $matches[1] ) {
                    $arr[] = $matches[1];
                }
                if ( isset( $matches[2] ) && $matches[2] ) {
                    $arr[] = $matches[2];
                }
                return nehtw_gateway_stock_id_mapping( 'epidemicsound', $arr );
            },
        ),
        array(
            'source' => 'yellowimages',
            'regex'  => '/yellowimages\.com\/(stock\/|(.*)p=)([0-9a-z-]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'vecteezy',
            'regex'  => '/vecteezy\.com\/([\/a-zA-Z-]*)\/([0-9]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => 'creativefabrica',
            'regex'  => '/creativefabrica\.com\/(.*)product\/([a-z0-9-]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => 'lovepik',
            'regex'  => '/lovepik\.com\/([a-z]*)-([0-9]*)\//i',
            'id'     => 2,
        ),
        array(
            'source' => 'rawpixel',
            'regex'  => '/rawpixel\.com\/image\/([0-9]*)/i',
            'id'     => 1,
        ),
        array(
            'source' => 'deeezy',
            'regex'  => '/deeezy\.com\/product\/([0-9]*)/i',
            'id'     => 1,
        ),
        array(
            'source' => 'footagecrate',
            'regex'  => '/(productioncrate|footagecrate|graphicscrate)\.com\/([a-z0-9-]*)\/([a-zA-Z0-9-_]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'artgrid_HD',
            'regex'  => '/artgrid\.io\/clip\/([0-9]*)\//i',
            'id'     => 1,
        ),
        array(
            'source' => 'pixelsquid',
            'regex'  => '/pixelsquid\.com(.*)-([0-9]*)\?image=(...)/i',
            'callback' => function( $matches ) {
                $arr = array();
                if ( isset( $matches[2] ) && $matches[2] ) {
                    $arr[] = $matches[2];
                }
                if ( isset( $matches[3] ) && $matches[3] ) {
                    $arr[] = $matches[3];
                }
                return nehtw_gateway_stock_id_mapping( 'pixelsquid', $arr );
            },
        ),
        array(
            'source' => 'pixelsquid',
            'regex'  => '/pixelsquid\.com(.*)-([0-9]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => 'ui8',
            'regex'  => '/ui8\.net\/(.*)\/(.*)\/([0-9a-zA-Z-]*)/i',
            'id'     => 3,
        ),
        array(
            'source' => 'iconscout',
            'regex'  => '/iconscout\.com\/((\w{2})\/?$|(\w{2})\/|)([0-9a-z-]*)\/([0-9a-z-_]*)/i',
            'id'     => 5,
        ),
        array(
            'source' => 'designi',
            'regex'  => '/designi\.com\.br\/([0-9a-zA-Z]*)/i',
            'id'     => 1,
        ),
        array(
            'source' => 'mockupcloud',
            'regex'  => '/mockupcloud\.com\/(product|scene|graphics\/product)\/([a-z0-9-]*)/i',
            'id'     => 2,
        ),
        array(
            'source' => 'artlist_footage',
            'regex'  => '/artlist\.io\/(stock-footage|video-templates)\/(.*)\/([0-9]*)/i',
            'callback' => function( $matches ) {
                $arr = array();
                if ( isset( $matches[1] ) && $matches[1] ) {
                    $arr[] = $matches[1];
                }
                if ( isset( $matches[3] ) && $matches[3] ) {
                    $arr[] = $matches[3];
                }
                return nehtw_gateway_stock_id_mapping( 'artlist_footage', $arr );
            },
        ),
        array(
            'source' => 'artlist_sound',
            'regex'  => '/artlist\.io\/(sfx|royalty-free-music)\/(.*)\/([0-9]*)/i',
            'callback' => function( $matches ) {
                $arr = array();
                if ( isset( $matches[1] ) && $matches[1] ) {
                    $arr[] = $matches[1];
                }
                if ( isset( $matches[3] ) && $matches[3] ) {
                    $arr[] = $matches[3];
                }
                return nehtw_gateway_stock_id_mapping( 'artlist_sound', $arr );
            },
        ),
        array(
            'source' => 'pixeden',
            'regex'  => '/pixeden\.com\/([0-9a-z-]*)\/([0-9a-z-]*)/i',
            'callback' => function( $matches ) {
                $arr = array();
                if ( isset( $matches[1] ) && $matches[1] ) {
                    $arr[] = $matches[1];
                }
                if ( isset( $matches[2] ) && $matches[2] ) {
                    $arr[] = $matches[2];
                }
                return nehtw_gateway_stock_id_mapping( 'pixeden', $arr );
            },
        ),
        array(
            'source' => 'uplabs',
            'regex'  => '/uplabs\.com\/posts\/([0-9a-z-]*)/i',
            'id'     => 1,
        ),
        array(
            'source' => 'pixelbuddha',
            'regex'  => '/pixelbuddha\.net\/(premium|)(.*)\/([0-9a-z-]*)/i',
            'callback' => function( $matches ) {
                $arr = array();
                if ( isset( $matches[1] ) && $matches[1] ) {
                    $arr[] = $matches[1];
                }
                if ( isset( $matches[2] ) && $matches[2] ) {
                    $arr[] = $matches[2];
                }
                if ( isset( $matches[3] ) && $matches[3] ) {
                    $arr[] = $matches[3];
                }
                return nehtw_gateway_stock_id_mapping( 'pixelbuddha', $arr );
            },
        ),
        array(
            'source' => 'uihut',
            'regex'  => '/uihut\.com\/designs\/([0-9]*)/i',
            'id'     => 1,
        ),
        array(
            'source' => 'craftwork',
            'regex'  => '/craftwork\.design\/product\/([0-9a-z-]*)/i',
            'id'     => 1,
        ),
        array(
            'source' => 'baixardesign',
            'regex'  => '/baixardesign\.com\.br\/arquivo\/([0-9a-z]*)/i',
            'id'     => 1,
        ),
        array(
            'source' => 'soundstripe',
            'regex'  => '/soundstripe\.com\/(.*)\/([0-9]*)/i',
            'callback' => function( $matches ) {
                $arr = array();
                if ( isset( $matches[1] ) && $matches[1] ) {
                    $arr[] = $matches[1];
                }
                if ( isset( $matches[2] ) && $matches[2] ) {
                    $arr[] = $matches[2];
                }
                return nehtw_gateway_stock_id_mapping( 'soundstripe', $arr );
            },
        ),
        array(
            'source' => 'mrmockup',
            'regex'  => '/mrmockup\.com\/product\/([0-9a-z-]*)/i',
            'id'     => 1,
        ),
        array(
            'source' => 'designbr',
            'regex'  => '/designbr\.com\.br\/(.*)modal=([^&]+)/i',
            'id'     => 2,
        ),
    );

    foreach ( $patterns as $pattern ) {
        if ( ! preg_match( $pattern['regex'], $url, $matches ) ) {
            continue;
        }

        $id = '';

        if ( isset( $pattern['callback'] ) && is_callable( $pattern['callback'] ) ) {
            $id = call_user_func( $pattern['callback'], $matches );
        } else {
            $index = isset( $pattern['id'] ) ? (int) $pattern['id'] : 0;
            if ( isset( $matches[ $index ] ) ) {
                $id = $matches[ $index ];
            }
        }

        if ( '' === $id ) {
            continue;
        }

        return array(
            'source' => $pattern['source'],
            'id'     => $id,
            'url'    => $url,
        );
    }

    return false;
}

/**
 * Parse a stock URL and return site + remote ID using idExtractor.
 *
 * @param string $url
 * @return array|null { 'site' => 'shutterstock', 'remote_id' => '1234567890' }
 */
function nehtw_gateway_parse_stock_url( $url ) {
    $result = nehtw_gateway_stock_id_extractor( $url );

    if ( ! $result ) {
        return null;
    }

    return array(
        'site'      => $result['source'], // matches site keys in config
        'remote_id' => $result['id'],
    );
}

/**
 * Extract Shutterstock ID from URL.
 *
 * @param array  $parts    Parsed URL parts.
 * @param string $original Original URL.
 * @return string
 */
function nehtw_gateway_extract_shutterstock_id_from_url( $parts, $original ) {
    if ( empty( $parts['path'] ) ) {
        return '';
    }

    // Try various patterns: /image-photo/id-1234567890, /photo/..., etc.
    $segments = explode( '/', trim( $parts['path'], '/' ) );

    foreach ( $segments as $seg ) {
        // Match "id-1234567890" or "1234567890"
        if ( preg_match( '/^id-?(\d+)$/', $seg, $m ) ) {
            return $m[1];
        }
        // Match pure numeric segment (likely ID)
        if ( preg_match( '/^(\d+)$/', $seg ) ) {
            return $seg;
        }
    }

    // Fallback: try query params
    if ( ! empty( $parts['query'] ) ) {
        parse_str( $parts['query'], $query );
        if ( ! empty( $query['id'] ) ) {
            return (string) $query['id'];
        }
    }

    return '';
}

/**
 * Extract Adobe Stock ID from URL.
 *
 * @param array  $parts    Parsed URL parts.
 * @param string $original Original URL.
 * @return string
 */
function nehtw_gateway_extract_adobe_id_from_url( $parts, $original ) {
    if ( empty( $parts['path'] ) ) {
        return '';
    }

    $segments = explode( '/', trim( $parts['path'], '/' ) );

    // Adobe URLs often end with the ID: /stock-photo/.../123456789
    $last = end( $segments );
    if ( preg_match( '/^(\d+)$/', $last ) ) {
        return $last;
    }

    // Try query params
    if ( ! empty( $parts['query'] ) ) {
        parse_str( $parts['query'], $query );
        if ( ! empty( $query['id'] ) ) {
            return (string) $query['id'];
        }
    }

    return '';
}

/**
 * Extract Freepik ID from URL.
 *
 * @param array  $parts    Parsed URL parts.
 * @param string $original Original URL.
 * @return string
 */
function nehtw_gateway_extract_freepik_id_from_url( $parts, $original ) {
    if ( empty( $parts['path'] ) ) {
        return '';
    }

    $segments = explode( '/', trim( $parts['path'], '/' ) );

    // Freepik URLs: /photo/.../123456789 or /vector/.../123456789
    foreach ( $segments as $seg ) {
        if ( preg_match( '/^(\d+)$/', $seg ) ) {
            return $seg;
        }
    }

    // Try query params
    if ( ! empty( $parts['query'] ) ) {
        parse_str( $parts['query'], $query );
        if ( ! empty( $query['id'] ) ) {
            return (string) $query['id'];
        }
    }

    return '';
}

/**
 * Check if user has a completed stock order for a given site + remote_id.
 *
 * @param int    $user_id   User ID.
 * @param string $site      Site key.
 * @param string $remote_id Remote asset ID.
 * @return array|null Row data or null if not found.
 */
function nehtw_gateway_get_existing_stock_order( $user_id, $site, $remote_id ) {
    if ( class_exists( 'Nehtw_Gateway_Stock_Orders' ) ) {
        $order = Nehtw_Gateway_Stock_Orders::find_existing_user_order( $user_id, $site, $remote_id );
        if ( $order ) {
            // Check if order is completed and has valid download link
            $status = isset( $order['status'] ) ? strtolower( (string) $order['status'] ) : '';
            if ( in_array( $status, array( 'completed', 'complete', 'ready', 'delivered' ), true ) ) {
                return $order;
            }
        }
    }

    return null;
}

