<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Public_Cache {
    private static $instance = null;
    private $settings;
    private $cache_dir;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = WPSC_Settings::instance();
        $this->cache_dir = trailingslashit( $this->settings->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) );
    }

    private function is_login_page() {
        $login_url = wp_login_url();
        if ( ! $login_url ) {
            return false;
        }
        $login_path = parse_url( $login_url, PHP_URL_PATH );
        $req_path   = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        return $login_path && $req_path && untrailingslashit( $login_path ) === untrailingslashit( $req_path );
    }

    public function init_buffer() {
        if ( ! $this->settings->get( 'public_cache_enabled', true ) ) {
            return;
        }
        if ( $this->should_bypass() ) {
            return;
        }
        ob_start( array( $this, 'buffer_callback' ) );
    }

    public function buffer_callback( $buffer ) {
        WPSC_Logger::info( 'Page render started', array( 'uri' => $_SERVER['REQUEST_URI'], 'size' => strlen( $buffer ) ) );
        if ( is_404() || strlen( $buffer ) < 255 ) {
            return $buffer;
        }
        $max_size = (int) $this->settings->get( 'max_cache_file_size', 5120 ) * 1024;
        if ( $max_size > 0 && strlen( $buffer ) > $max_size ) {
            return $buffer;
        }
        $buffer = WPSC_JS_Optimizer::instance()->process_html( $buffer );
        $buffer = WPSC_Image_Optimizer::instance()->process_html( $buffer );
        $cache_version = $this->settings->get( 'cache_version', '1.0' );
        if ( ! empty( $cache_version ) ) {
            $buffer = preg_replace(
                '/((?:href|src)\s*=\s*["\'][^"\']*?)ver=[a-z0-9._-]+(["\'])/i',
                '$1ver=' . $cache_version . '$2',
                $buffer
            );
        }
        $uri = $this->get_cache_uri();
        $dir = $this->cache_dir . 'public' . $uri;
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        $html_path = $dir . '/index.html';
        file_put_contents( $html_path, $buffer );
        $page_type = wpsc_get_page_type();
        $ttl = wpsc_get_ttl_for_page_type( $page_type );
        $meta = array(
            'created_at' => time(),
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
            'page_type'  => $page_type,
            'uri'        => $_SERVER['REQUEST_URI'],
            'generating' => false,
        );
        file_put_contents( $dir . '/.meta.json', wp_json_encode( $meta ) );
        WPSC_Logger::info( 'Cache generated', array( 'uri' => $_SERVER['REQUEST_URI'], 'size' => strlen( $buffer ), 'page_type' => $page_type, 'ttl' => $ttl ) );
        return $buffer;
    }

    public function serve_from_cache() {
        $uri = $this->get_cache_uri();
        $dir = $this->cache_dir . 'public' . $uri;
        $html_path = $dir . '/index.html';
        $meta_path = $dir . '/.meta.json';

        if ( ! file_exists( $html_path ) || ! file_exists( $meta_path ) ) {
            return false;
        }
        $meta = json_decode( file_get_contents( $meta_path ), true );
        if ( ! $meta ) {
            return false;
        }
        $now = time();
        $is_expired = false;
        if ( isset( $meta['expires_at'] ) && $meta['expires_at'] > 0 ) {
            if ( $now > $meta['expires_at'] ) {
                $is_expired = true;
            }
        }
        if ( $is_expired ) {
            $swr_window = (int) $this->settings->get( 'swr_window', 300 );
            $is_stale = $meta['expires_at'] > 0 && $now <= $meta['expires_at'] + $swr_window;
            if ( ! $is_stale ) {
                if ( file_exists( $html_path ) ) { unlink( $html_path ); }
                if ( file_exists( $meta_path ) ) { unlink( $meta_path ); }
                return false;
            }
            $this->send_headers( $meta, true );
        } else {
            $this->send_headers( $meta, false );
        }
        readfile( $html_path );
        exit;
    }

    private function send_headers( $meta, $is_stale ) {
        header( 'X-Cache: ' . ( $is_stale ? 'STALE' : 'HIT' ) );
        if ( $this->settings->get( 'cache_control_enabled', true ) ) {
            $max_age = (int) $this->settings->get( 'cache_control_maxage', 3600 );
            $swr = (int) $this->settings->get( 'swr_window', 300 );
            if ( $is_stale ) {
                header( 'Cache-Control: public, max-age=0, stale-while-revalidate=' . $swr );
            } else {
                header( 'Cache-Control: public, max-age=' . $max_age . ', stale-while-revalidate=' . $swr );
            }
        }
    }

    private function should_bypass() {
        if ( isset( $_GET['wpsc_analysis'] ) ) {
            return true;
        }
        if ( isset( $_GET['wpsc_optimized_test'] ) ) {
            return false;
        }
        if ( isset( $_SERVER['HTTP_X_WPSC_PRELOAD'] ) ) {
            return false;
        }
        if ( is_user_logged_in() ) {
            return true;
        }
        if ( wpsc_is_bypass_cookie() ) {
            return true;
        }
        if ( is_admin() || is_preview() ) {
            return true;
        }
        if ( $this->is_login_page() ) {
            return true;
        }
        if ( $this->settings->get( 'exclude_404', true ) && is_404() ) {
            return true;
        }
        if ( $this->settings->get( 'exclude_feeds', true ) && is_feed() ) {
            return true;
        }
        if ( $this->settings->get( 'exclude_rest_api', true ) ) {
            if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
                return true;
            }
            $rest_prefix = rest_get_url_prefix();
            if ( $rest_prefix ) {
                $req_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
                if ( $req_path && strpos( $req_path, '/' . $rest_prefix . '/' ) !== false ) {
                    return true;
                }
            }
        }
        $excluded_ids = $this->settings->get( 'exclude_post_ids', array() );
        if ( ! empty( $excluded_ids ) ) {
            $excluded_ids = array_map( 'intval', (array) $excluded_ids );
            $queried_id = get_queried_object_id();
            if ( $queried_id && is_singular() && in_array( $queried_id, $excluded_ids ) ) {
                return true;
            }
        }
        $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( $_SERVER['REQUEST_METHOD'] ) : 'GET';
        if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
            return true;
        }
        if ( $this->settings->get( 'exclude_parameters', true ) && ! empty( $_GET ) ) {
            $cacheable = (array) $this->settings->get( 'cacheable_qs', array() );
            $cacheable = array_map( 'trim', $cacheable );
            foreach ( $_GET as $key => $val ) {
                if ( ! in_array( $key, $cacheable, true ) ) {
                    return true;
                }
            }
        }
        $uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        $patterns = $this->settings->get( 'exclude_urls', array() );
        if ( is_string( $patterns ) ) {
            $patterns = explode( "\n", $patterns );
        }
        foreach ( (array) $patterns as $pattern ) {
            $pattern = trim( $pattern );
            if ( empty( $pattern ) ) {
                continue;
            }
            if ( @preg_match( $pattern, $uri ) ) {
                return true;
            }
            if ( strpos( $uri, $pattern ) !== false ) {
                return true;
            }
            if ( strpos( $uri . '/', $pattern ) !== false ) {
                return true;
            }
        }
        $rejected_qs = (array) $this->settings->get( 'rejected_qs', array() );
        foreach ( $rejected_qs as $qs ) {
            $qs = trim( $qs );
            if ( ! empty( $qs ) && isset( $_GET[ $qs ] ) ) {
                return true;
            }
        }
        $exclude_qs = (array) $this->settings->get( 'exclude_qs', array() );
        foreach ( $exclude_qs as $qs ) {
            $qs = trim( $qs );
            if ( ! empty( $qs ) && isset( $_GET[ $qs ] ) ) {
                return true;
            }
        }
        $ua = wpsc_get_user_agent();
        $excluded_ua = (array) $this->settings->get( 'exclude_user_agents', array() );
        foreach ( $excluded_ua as $pattern ) {
            $pattern = trim( $pattern );
            if ( ! empty( $pattern ) && stripos( $ua, $pattern ) !== false ) {
                return true;
            }
        }
        return false;
    }

    private function get_cache_uri() {
        $uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        if ( empty( $uri ) ) {
            $uri = '/';
        }
        $uri = rtrim( $uri, '/' );
        if ( empty( $uri ) ) {
            $uri = '/index';
        }
        return $uri;
    }

    public static function flush_all() {
        WPSC_Logger::info( 'Public cache flushed' );
        $settings = WPSC_Settings::instance();
        $dir = trailingslashit( $settings->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) ) . 'public';
        if ( is_dir( $dir ) ) {
            $it = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
            $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
            foreach ( $files as $file ) {
                if ( $file->isDir() ) {
                    @rmdir( $file->getRealPath() );
                } else {
                    @unlink( $file->getRealPath() );
                }
            }
        }
    }

    public static function flush_url( $url, $mark_stale = false ) {
        $path_info = wpsc_build_cache_path( $url );
        if ( $mark_stale && file_exists( $path_info['meta'] ) ) {
            $meta = json_decode( file_get_contents( $path_info['meta'] ), true );
            if ( $meta ) {
                $meta['expires_at'] = time();
                $meta['stale'] = true;
                file_put_contents( $path_info['meta'], wp_json_encode( $meta ) );
                return;
            }
        }
        if ( file_exists( $path_info['file'] ) ) {
            unlink( $path_info['file'] );
        }
        if ( file_exists( $path_info['meta'] ) ) {
            unlink( $path_info['meta'] );
        }
    }

    public static function flush_expired() {
        $settings = WPSC_Settings::instance();
        $dir = trailingslashit( $settings->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) ) . 'public';
        if ( ! is_dir( $dir ) ) {
            return;
        }
        $max_age = (int) $settings->get( 'max_cache_age', 0 );
        $max_age_ts = $max_age > 0 ? time() - ( $max_age * DAY_IN_SECONDS ) : 0;
        $it = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
        $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $files as $file ) {
            if ( $file->getFilename() === '.meta.json' ) {
                $meta = json_decode( file_get_contents( $file->getRealPath() ), true );
                $delete = false;
                if ( $meta && isset( $meta['expires_at'] ) && $meta['expires_at'] > 0 && time() > $meta['expires_at'] ) {
                    $delete = true;
                }
                if ( ! $delete && $max_age_ts > 0 && $file->getMTime() < $max_age_ts ) {
                    $delete = true;
                }
                if ( $delete ) {
                    $parent = dirname( $file->getRealPath() );
                    array_map( 'unlink', glob( $parent . '/*' ) );
                    @rmdir( $parent );
                }
            }
        }
    }
}
