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
            'artlist_music' => array(
                'key'     => 'artlist_music',
                'label'   => 'Artlist Music',
                'points'  => 1.5,
                'enabled' => true,
                'url'     => 'https://artlist.io',
                'domains' => array( 'artlist.io' ),
            ),
            'artlist_sfx' => array(
                'key'     => 'artlist_sfx',
                'label'   => 'Artlist SFX',
                'points'  => 1.0,
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
            'artgrid_hd' => array(
                'key'     => 'artgrid_hd',
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
            'artlist_video' => array(
                'key'     => 'artlist_video',
                'label'   => 'Artlist Video',
                'points'  => 2.0,
                'enabled' => true,
                'url'     => 'https://artlist.io',
                'domains' => array( 'artlist.io' ),
            ),
            'artlist_template' => array(
                'key'     => 'artlist_template',
                'label'   => 'Artlist Template',
                'points'  => 1.5,
                'enabled' => true,
                'url'     => 'https://artlist.io',
                'domains' => array( 'artlist.io' ),
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
 * Parse a stock URL and return site + remote ID.
 *
 * @param string $url
 * @return array|null { 'site' => 'shutterstock', 'remote_id' => '1234567890' }
 */
function nehtw_gateway_parse_stock_url( $url ) {
    $url = trim( (string) $url );

    if ( '' === $url ) {
        return null;
    }

    $parts = wp_parse_url( $url );

    if ( empty( $parts['host'] ) ) {
        return null;
    }

    $host = strtolower( $parts['host'] );

    $sites = nehtw_gateway_get_stock_sites_config();

    foreach ( $sites as $site_key => $config ) {
        if ( empty( $config['domains'] ) || ! is_array( $config['domains'] ) ) {
            continue;
        }

        foreach ( $config['domains'] as $pattern ) {
            if ( false !== strpos( $host, strtolower( $pattern ) ) ) {
                $remote_id = nehtw_gateway_extract_remote_id_for_site( $site_key, $parts, $url );

                if ( $remote_id ) {
                    return array(
                        'site'      => $site_key,
                        'remote_id' => $remote_id,
                    );
                }
            }
        }
    }

    return null;
}

/**
 * Extract remote ID for a specific site.
 *
 * @param string $site_key Site key.
 * @param array  $parts    Parsed URL parts.
 * @param string $original Original URL.
 * @return string
 */
function nehtw_gateway_extract_remote_id_for_site( $site_key, $parts, $original ) {
    switch ( $site_key ) {
        case 'shutterstock':
        case 'ss_video_hd':
        case 'ss_video_4k':
        case 'ss_music':
            return nehtw_gateway_extract_shutterstock_id_from_url( $parts, $original );

        case 'adobestock':
        case 'adobestock_video':
            return nehtw_gateway_extract_adobe_id_from_url( $parts, $original );

        case 'freepik':
        case 'freepik_video':
            return nehtw_gateway_extract_freepik_id_from_url( $parts, $original );

        // For the rest, use a generic last-path-segment or last-dash ID approach.
        default:
            if ( empty( $parts['path'] ) ) {
                return '';
            }

            $path     = trim( $parts['path'], '/' );
            $segments = explode( '/', $path );

            return end( $segments );
    }
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

