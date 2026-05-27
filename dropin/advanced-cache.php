<?php
/**
 * WP Static Cache - Advanced Cache Drop-In
 * This file is auto-generated. Do not edit directly.
 */
defined( 'ABSPATH' ) || exit;

define( 'WPSC_DROPIN_ACTIVE', true );

if ( isset( $_SERVER['HTTP_X_WPSC_PRELOAD'] ) ) {
    return;
}

$wpsc_request_path = parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
$wpsc_ext = strtolower( pathinfo( $wpsc_request_path, PATHINFO_EXTENSION ) );
if ( $wpsc_ext !== '' && $wpsc_ext !== 'html' && $wpsc_ext !== 'htm' && $wpsc_ext !== 'php' ) {
    return;
}

$wpsc_cache_dir = '{{WPSC_CACHE_DIR_PLACEHOLDER}}';
$wpsc_gzip = '{{WPSC_GZIP_ENABLED_PLACEHOLDER}}' === 'true';
$wpsc_cacheable_qs = '{{WPSC_CACHEABLE_QS_PLACEHOLDER}}';
$wpsc_exclude_cookies = '{{WPSC_EXCLUDE_COOKIES_PLACEHOLDER}}';
$wpsc_exclude_ua = '{{WPSC_EXCLUDE_UA_PLACEHOLDER}}';
$wpsc_max_file_size = (int) '{{WPSC_MAX_FILE_SIZE_PLACEHOLDER}}';
$wpsc_exclude_urls = explode( "\n", '{{WPSC_EXCLUDE_URLS_PLACEHOLDER}}' );
$wpsc_exclude_parameters = '{{WPSC_EXCLUDE_PARAMETERS_PLACEHOLDER}}' === 'true';
$wpsc_log_enabled = '{{WPSC_LOG_ENABLED_PLACEHOLDER}}' === 'true';
$wpsc_log_dir = '{{WPSC_CACHE_DIR_PLACEHOLDER}}logs/';
$wpsc_swr_window = (int) '{{WPSC_SWR_WINDOW_PLACEHOLDER}}';

if ( ! function_exists( 'wpsc_dropin_log' ) ) {
    function wpsc_dropin_log( $message, $level = 'DEBUG' ) {
        global $wpsc_log_enabled, $wpsc_log_dir;
        if ( ! $wpsc_log_enabled ) { return; }
        $log_file = $wpsc_log_dir . 'debug.log';
        if ( ! is_dir( $wpsc_log_dir ) ) { @mkdir( $wpsc_log_dir, 0755, true ); }
        $entry = '[' . date( 'Y-m-d H:i:s' ) . '] [' . $level . '] [Dropin] ' . $message . PHP_EOL;
        @file_put_contents( $log_file, $entry, FILE_APPEND | LOCK_EX );
    }
}

$wpsc_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
if ( ! in_array( $wpsc_method, array( 'GET', 'HEAD' ), true ) ) {
    return;
}

$wpsc_uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
if ( empty( $wpsc_uri ) ) {
    $wpsc_uri = '/';
}
$wpsc_uri = rtrim( $wpsc_uri, '/' );
if ( empty( $wpsc_uri ) ) {
    $wpsc_uri = '/index';
}

$wpsc_has_logged_in = false;
if ( isset( $_COOKIE ) ) {
    foreach ( $_COOKIE as $name => $value ) {
        if ( strpos( $name, 'wordpress_logged_in_' ) === 0 ) {
            $wpsc_has_logged_in = true;
            break;
        }
    }
    if ( ! $wpsc_has_logged_in && $wpsc_exclude_cookies ) {
        $excluded = explode( '|', $wpsc_exclude_cookies );
        foreach ( $_COOKIE as $name => $value ) {
            foreach ( $excluded as $pattern ) {
                if ( $pattern && strpos( $name, $pattern ) !== false ) {
                    return;
                }
            }
        }
    }
}

if ( $wpsc_exclude_ua && isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
    $ua = $_SERVER['HTTP_USER_AGENT'];
    $patterns = explode( '|', $wpsc_exclude_ua );
    foreach ( $patterns as $pattern ) {
        if ( $pattern && stripos( $ua, $pattern ) !== false ) {
            return;
        }
    }
}

if ( isset( $_GET['wpsc_analysis'] ) || isset( $_GET['wpsc_optimized_test'] ) ) {
    wpsc_dropin_log( 'Bypass cache: analysis or optimized test mode', 'INFO' );
    return;
}

if ( ! empty( $_GET ) ) {
    if ( $wpsc_exclude_parameters && $wpsc_cacheable_qs ) {
        $allowed = explode( '|', $wpsc_cacheable_qs );
        foreach ( $_GET as $key => $val ) {
            if ( ! in_array( $key, $allowed, true ) ) {
                return;
            }
        }
    }
}

if ( ! empty( $wpsc_exclude_urls ) ) {
    foreach ( (array) $wpsc_exclude_urls as $wpsc_pattern ) {
        $wpsc_pattern = trim( $wpsc_pattern );
        if ( $wpsc_pattern === '' ) { continue; }
        if ( @preg_match( $wpsc_pattern, $wpsc_uri ) ) {
            return;
        }
        if ( strpos( $wpsc_uri, $wpsc_pattern ) !== false ) {
            return;
        }
        if ( strpos( $wpsc_uri . '/', $wpsc_pattern ) !== false ) {
            return;
        }
    }
}

if ( $wpsc_has_logged_in ) {
    $wpsc_role_hash = isset( $_COOKIE['wpsc_role_hash'] ) ? $_COOKIE['wpsc_role_hash'] : '';
    if ( preg_match( '/^[a-f0-9]{32}$/', $wpsc_role_hash ) ) {
        $wpsc_p_dir  = $wpsc_cache_dir . 'private/' . $wpsc_role_hash . $wpsc_uri;
        $wpsc_p_html = $wpsc_p_dir . '/index.html';
        $wpsc_p_meta = $wpsc_p_dir . '/.meta.json';
        if ( file_exists( $wpsc_p_html ) && file_exists( $wpsc_p_meta ) ) {
            $wpsc_p_m = json_decode( file_get_contents( $wpsc_p_meta ), true );
            if ( $wpsc_p_m ) {
                $wpsc_p_now     = time();
                $wpsc_p_exp     = isset( $wpsc_p_m['expires_at'] ) ? (int) $wpsc_p_m['expires_at'] : 0;
                $wpsc_p_expired = $wpsc_p_exp > 0 && $wpsc_p_now > $wpsc_p_exp;
                $wpsc_p_stale   = false;
                if ( $wpsc_p_expired ) {
                    $wpsc_p_deadline = $wpsc_p_exp + $wpsc_swr_window;
                    if ( $wpsc_swr_window > 0 && $wpsc_p_now <= $wpsc_p_deadline ) {
                        $wpsc_p_stale = true;
                    } else {
                        @unlink( $wpsc_p_html );
                        @unlink( $wpsc_p_meta );
                        $wpsc_p_gz = $wpsc_p_dir . '/index.html.gz';
                        if ( file_exists( $wpsc_p_gz ) ) { @unlink( $wpsc_p_gz ); }
                    }
                }
                if ( ! $wpsc_p_expired || $wpsc_p_stale ) {
                    header( 'X-Cache: ' . ( $wpsc_p_stale ? 'STALE' : 'HIT' ) );
                    header( 'X-Cache-Group: private' );
                    wpsc_dropin_log( 'Private ' . ( $wpsc_p_stale ? 'STALE' : 'HIT' ) . ': ' . $wpsc_uri, 'INFO' );
                    readfile( $wpsc_p_html );
                    exit;
                }
            }
        }
    }
    return;
}

$wpsc_cache_subdir = $wpsc_cache_dir . 'public' . $wpsc_uri;
$wpsc_html_file = $wpsc_cache_subdir . '/index.html';
$wpsc_meta_file = $wpsc_cache_subdir . '/.meta.json';

if ( ! file_exists( $wpsc_html_file ) || ! file_exists( $wpsc_meta_file ) ) {
    wpsc_dropin_log( 'Cache MISS: ' . $wpsc_uri, 'INFO' );
    return;
}

$wpsc_meta = json_decode( file_get_contents( $wpsc_meta_file ), true );
if ( ! $wpsc_meta ) {
    return;
}

$wpsc_now = time();
$wpsc_is_expired = false;
if ( isset( $wpsc_meta['expires_at'] ) && $wpsc_meta['expires_at'] > 0 ) {
    if ( $wpsc_now > $wpsc_meta['expires_at'] ) {
        $wpsc_is_expired = true;
    }
}

$wpsc_serving_stale = false;
if ( $wpsc_is_expired ) {
    $wpsc_within_swr = $wpsc_swr_window > 0 && isset( $wpsc_meta['expires_at'] ) && $wpsc_meta['expires_at'] > 0 && $wpsc_now <= $wpsc_meta['expires_at'] + $wpsc_swr_window;
    if ( $wpsc_within_swr ) {
        $wpsc_serving_stale = true;
    } else {
        @unlink( $wpsc_html_file );
        @unlink( $wpsc_meta_file );
        wpsc_dropin_log( 'Cache EXPIRED and deleted: ' . $wpsc_uri, 'INFO' );
        return;
    }
}

wpsc_dropin_log( 'Serving ' . ( $wpsc_serving_stale ? 'STALE' : 'HIT' ) . ': ' . $wpsc_uri, 'INFO' );
header( 'X-Cache: ' . ( $wpsc_serving_stale ? 'STALE' : 'HIT' ) );
readfile( $wpsc_html_file );
exit;
