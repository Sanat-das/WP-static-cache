<?php
defined( 'ABSPATH' ) || exit;

function wpsc_get_setting( $key, $default = null ) {
    return WPSC_Settings::instance()->get( $key, $default );
}

function wpsc_is_public_cache_enabled() {
    return (bool) wpsc_get_setting( 'public_cache_enabled', true );
}

function wpsc_is_private_cache_enabled() {
    return (bool) wpsc_get_setting( 'private_cache_enabled', false );
}

function wpsc_get_cache_dir() {
    $dir = wpsc_get_setting( 'cache_dir', WPSC_CACHE_DIR_DEFAULT );
    return trailingslashit( $dir );
}

function wpsc_build_cache_path( $url ) {
    $parts = parse_url( $url );
    $path   = isset( $parts['path'] ) ? $parts['path'] : '/';
    $path   = rtrim( $path, '/' );
    if ( empty( $path ) ) {
        $path = '/index';
    }
    $dir    = wpsc_get_cache_dir() . 'public' . $path;
    return array( 'dir' => $dir, 'file' => $dir . '/index.html', 'meta' => $dir . '/.meta.json' );
}

function wpsc_get_user_agent() {
    return isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
}

function wpsc_is_logged_in_cookie() {
    if ( ! isset( $_COOKIE ) ) { return false; }
    foreach ( $_COOKIE as $name => $value ) {
        if ( strpos( $name, 'wordpress_logged_in_' ) === 0 ) { return true; }
    }
    return false;
}

function wpsc_is_bypass_cookie() {
    $cookies = wpsc_get_setting( 'exclude_cookies', array( 'wordpress_logged_in_', 'comment_author_', 'woocommerce_items_in_cart' ) );
    if ( ! isset( $_COOKIE ) || empty( $cookies ) ) { return false; }
    foreach ( $_COOKIE as $name => $value ) {
        foreach ( (array) $cookies as $pattern ) {
            if ( strpos( $name, $pattern ) !== false ) { return true; }
        }
    }
    return false;
}

function wpsc_parse_changelog( $max_versions = 10 ) {
    $readme = WPSC_DIR . '/readme.txt';
    if ( ! file_exists( $readme ) ) {
        return array();
    }
    $content = file_get_contents( $readme );
    $lines = explode( "\n", $content );
    $changelog = array();
    $current_version = '';
    $in_changelog = false;
    foreach ( $lines as $line ) {
        $trimmed = trim( $line );
        if ( preg_match( '/^==\s*Changelog\s*==$/i', $trimmed ) ) {
            $in_changelog = true;
            continue;
        }
        if ( $in_changelog ) {
            if ( preg_match( '/^==\s*.+\s*==$/', $trimmed ) ) {
                break;
            }
            if ( preg_match( '/^=\s*v?([0-9]+\.[0-9]+(?:\.[0-9]+)?)\s*=(.*)$/i', $trimmed, $m ) ) {
                $current_version = $m[1];
                $changelog[ $current_version ] = array();
            } elseif ( $current_version && preg_match( '/^\*\s*(.+)$/', $trimmed, $m ) ) {
                $changelog[ $current_version ][] = $m[1];
            }
        }
    }
    if ( $max_versions > 0 ) {
        $changelog = array_slice( $changelog, 0, $max_versions, true );
    }
    return $changelog;
}

function wpsc_get_plugin_meta( $key = '' ) {
    if ( ! function_exists( 'get_plugin_data' ) ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $data = get_plugin_data( WPSC_FILE, false, false );
    return $key ? ( isset( $data[ $key ] ) ? $data[ $key ] : '' ) : $data;
}

function wpsc_get_page_type() {
    if ( is_front_page() || is_home() ) { return 'home'; }
    if ( is_singular() ) { return 'single'; }
    if ( is_archive() || is_tax() || is_category() || is_tag() ) { return 'archive'; }
    return 'other';
}

function wpsc_get_ttl_for_page_type( $page_type ) {
    $ttls = array(
        'home'    => (int) wpsc_get_setting( 'homepage_ttl', 60 ),
        'archive' => (int) wpsc_get_setting( 'taxonomy_ttl', 60 ),
        'single'  => (int) wpsc_get_setting( 'single_post_ttl', 0 ),
        'other'   => (int) wpsc_get_setting( 'other_ttl', 60 ),
    );
    $ttl = isset( $ttls[ $page_type ] ) ? $ttls[ $page_type ] : 60;
    return $ttl > 0 ? $ttl * 60 : 0;
}
