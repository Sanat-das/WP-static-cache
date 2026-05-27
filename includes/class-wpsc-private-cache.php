<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Private_Cache {
    private static $instance = null;
    private $settings;
    private $cache_dir;
    private $enabled;
    private $version;
    private $uri;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings  = WPSC_Settings::instance();
        $this->cache_dir = trailingslashit( $this->settings->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) );
        $this->enabled   = (bool) $this->settings->get( 'private_cache_enabled', false );
    }

    public function is_enabled() {
        return $this->enabled;
    }

    public function init() {
        if ( ! $this->enabled ) { return; }
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) { return; }
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) { return; }
        if ( defined( 'WP_CLI' ) && WP_CLI ) { return; }
        if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) { return; }
        if ( is_admin() || is_preview() || is_404() ) { return; }
        if ( ! is_user_logged_in() ) { return; }

        $role_hash = $this->get_role_hash();
        if ( ! $role_hash ) { return; }

        $this->set_role_cookie( $role_hash );

        $this->version = (int) get_option( 'wpsc_private_version', 1 );
        $this->uri     = $this->get_current_uri();

        if ( $this->should_exclude( $this->uri ) ) { return; }

        if ( $this->serve( $role_hash ) ) {
            exit;
        }

        ob_start( function( $buffer ) use ( $role_hash ) {
            if ( strlen( $buffer ) > 255 && ! is_404() ) {
                $this->store( $buffer, $role_hash );
            }
            return $buffer;
        } );
    }

    private function set_role_cookie( $role_hash ) {
        if ( headers_sent() ) { return; }
        setcookie(
            'wpsc_role_hash',
            $role_hash,
            time() + YEAR_IN_SECONDS,
            COOKIEPATH,
            COOKIE_DOMAIN,
            is_ssl(),
            true
        );
    }

    public static function clear_role_cookie() {
        if ( headers_sent() ) { return; }
        setcookie( 'wpsc_role_hash', '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
    }

    private function get_role_hash() {
        $user = wp_get_current_user();
        if ( ! $user->exists() ) { return false; }
        $roles = $user->roles;
        if ( empty( $roles ) ) { $roles = array( 'nobody' ); }
        if ( count( $roles ) > 5 ) { return false; }
        sort( $roles );
        return md5( implode( ',', $roles ) . '|' . $user->ID );
    }

    private function get_current_uri() {
        $uri = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        if ( empty( $uri ) ) { $uri = '/'; }
        $uri = rtrim( $uri, '/' );
        return empty( $uri ) ? '/index' : $uri;
    }

    private function should_exclude( $uri ) {
        $patterns = $this->settings->get( 'exclude_urls', array() );
        if ( is_string( $patterns ) ) {
            $patterns = explode( "\n", $patterns );
        }
        foreach ( (array) $patterns as $pattern ) {
            $pattern = trim( $pattern );
            if ( empty( $pattern ) ) { continue; }
            if ( @preg_match( $pattern, $uri ) ) { return true; }
            if ( strpos( $uri, $pattern ) !== false ) { return true; }
            if ( strpos( $uri . '/', $pattern ) !== false ) { return true; }
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

        if ( $this->settings->get( 'exclude_parameters', true ) && ! empty( $_GET ) ) {
            $cacheable = (array) $this->settings->get( 'cacheable_qs', array() );
            $cacheable = array_map( 'trim', $cacheable );
            foreach ( $_GET as $key => $val ) {
                if ( ! in_array( $key, $cacheable, true ) ) {
                    return true;
                }
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

        $excluded_ids = $this->settings->get( 'exclude_post_ids', array() );
        if ( ! empty( $excluded_ids ) ) {
            $excluded_ids = array_map( 'intval', (array) $excluded_ids );
            $queried_id = get_queried_object_id();
            if ( $queried_id && is_singular() && in_array( $queried_id, $excluded_ids ) ) {
                return true;
            }
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

        if ( $this->settings->get( 'exclude_feeds', true ) && is_feed() ) {
            return true;
        }

        return false;
    }

    private function serve( $role_hash ) {
        $oc_key = "wpsc_priv:v{$this->version}:{$role_hash}:{$this->uri}";

        $cached = wp_cache_get( $oc_key, 'wpsc_private' );
        if ( $cached !== false ) {
            header( 'X-Cache: HIT' );
            header( 'X-Cache-Group: private' );
            echo $cached;
            return true;
        }

        $meta = $this->read_meta( $role_hash );
        if ( ! $meta ) { return false; }

        $now        = time();
        $expires_at = isset( $meta['expires_at'] ) ? (int) $meta['expires_at'] : 0;
        $is_expired = $expires_at > 0 && $now > $expires_at;

        if ( $is_expired ) {
            $swr = (int) $this->settings->get( 'swr_window', 300 );
            if ( $expires_at > 0 && $now <= $expires_at + $swr ) {
                header( 'X-Cache: STALE' );
            } else {
                $this->delete_url_dir( $role_hash );
                return false;
            }
        } else {
            header( 'X-Cache: HIT' );
        }

        header( 'X-Cache-Group: private' );

        $html_file = $this->file_path( $role_hash, 'index.html' );
        if ( ! file_exists( $html_file ) ) { return false; }

        readfile( $html_file );

        return true;
    }

    private function store( $buffer, $role_hash ) {
        $dir = $this->cache_dir . 'private/' . $role_hash . $this->uri;
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }

        $ttl       = (int) $this->settings->get( 'pc_ttl_private_pages', 300 );
        $meta      = array(
            'created_at' => time(),
            'expires_at' => $ttl > 0 ? time() + $ttl : 0,
            'uri'        => $_SERVER['REQUEST_URI'],
            'role_hash'  => $role_hash,
        );

        file_put_contents( $dir . '/index.html', $buffer );
        file_put_contents( $dir . '/.meta.json', wp_json_encode( $meta ) );

        $oc_key = "wpsc_priv:v{$this->version}:{$role_hash}:{$this->uri}";
        wp_cache_set( $oc_key, $buffer, 'wpsc_private', $ttl );
    }

    private function read_meta( $role_hash ) {
        $meta_file = $this->file_path( $role_hash, '.meta.json' );
        if ( ! file_exists( $meta_file ) ) { return false; }
        $meta = json_decode( file_get_contents( $meta_file ), true );
        return is_array( $meta ) ? $meta : false;
    }

    private function file_path( $role_hash, $file = '' ) {
        $path = $this->cache_dir . 'private/' . $role_hash . $this->uri;
        return $file ? $path . '/' . $file : $path;
    }

    public function flush_url( $url ) {
        $private_dir = $this->cache_dir . 'private';
        if ( ! is_dir( $private_dir ) ) { return; }

        $uri = parse_url( $url, PHP_URL_PATH );
        if ( empty( $uri ) ) { $uri = '/'; }
        $uri = rtrim( $uri, '/' );
        if ( empty( $uri ) ) { $uri = '/index'; }

        $role_dirs = glob( $private_dir . '/*', GLOB_ONLYDIR );
        foreach ( $role_dirs as $role_dir ) {
            $target = $role_dir . $uri;
            if ( is_dir( $target ) ) {
                array_map( 'unlink', glob( $target . '/*' ) );
                @rmdir( $target );
            }
        }

        $this->bump_version();
    }

    public function flush_all() {
        $dir = $this->cache_dir . 'private';
        if ( is_dir( $dir ) ) {
            $it   = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
            $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
            foreach ( $files as $file ) {
                if ( $file->isDir() ) { @rmdir( $file->getRealPath() ); }
                else { @unlink( $file->getRealPath() ); }
            }
        }
        $this->bump_version();
    }

    public function flush_expired() {
        $dir = $this->cache_dir . 'private';
        if ( ! is_dir( $dir ) ) { return; }

        $now = time();
        $swr = (int) $this->settings->get( 'swr_window', 300 );
        $it  = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
        $files = new RecursiveIteratorIterator( $it );

        foreach ( $files as $file ) {
            if ( $file->getFilename() !== '.meta.json' ) { continue; }
            $meta = json_decode( file_get_contents( $file->getRealPath() ), true );
            if ( ! $meta || ! isset( $meta['expires_at'] ) ) { continue; }
            $expires_at = (int) $meta['expires_at'];
            if ( $expires_at > 0 && $now > $expires_at + $swr ) {
                $parent = dirname( $file->getRealPath() );
                array_map( 'unlink', glob( $parent . '/*' ) );
                @rmdir( $parent );
            }
        }
    }

    public function invalidate_all_private_pages() {
        $this->flush_all();
    }

    private function bump_version() {
        update_option( 'wpsc_private_version', ++$this->version );
    }

    private function delete_url_dir( $role_hash ) {
        $dir = $this->cache_dir . 'private/' . $role_hash . $this->uri;
        if ( is_dir( $dir ) ) {
            array_map( 'unlink', glob( $dir . '/*' ) );
            @rmdir( $dir );
        }
    }
}
