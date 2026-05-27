<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Statistics {
    public static function get_cache_stats() {
        $settings = WPSC_Settings::instance();
        $dir = trailingslashit( $settings->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) ) . 'public';
        $stats = array(
            'total_files'     => 0,
            'total_size'      => 0,
            'by_type'         => array( 'home' => 0, 'single' => 0, 'archive' => 0, 'other' => 0 ),
            'unique_archives' => 0,
            'largest_file'    => array( 'name' => '', 'size' => 0 ),
            'oldest'          => PHP_INT_MAX,
            'newest'          => 0,
        );
        if ( ! is_dir( $dir ) ) {
            return $stats;
        }
        $it = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
        $files = new RecursiveIteratorIterator( $it );
        $archive_bases = array();
        foreach ( $files as $file ) {
            if ( $file->getFilename() === 'index.html' ) {
                $stats['total_files']++;
                $size = $file->getSize();
                $stats['total_size'] += $size;
                $mtime = $file->getMTime();
                if ( $mtime < $stats['oldest'] ) {
                    $stats['oldest'] = $mtime;
                }
                if ( $mtime > $stats['newest'] ) {
                    $stats['newest'] = $mtime;
                }
                if ( $size > $stats['largest_file']['size'] ) {
                    $stats['largest_file'] = array( 'name' => $file->getPathname(), 'size' => $size );
                }
                $meta_path = dirname( $file->getPathname() ) . '/.meta.json';
                if ( file_exists( $meta_path ) ) {
                    $meta = json_decode( file_get_contents( $meta_path ), true );
                    if ( $meta && isset( $meta['page_type'] ) && isset( $stats['by_type'][ $meta['page_type'] ] ) ) {
                        $stats['by_type'][ $meta['page_type'] ]++;
                        if ( $meta['page_type'] === 'archive' && isset( $meta['uri'] ) ) {
                            $base = preg_replace( '#/page/\d+/?#', '', $meta['uri'] );
                            $archive_bases[ $base ] = true;
                        }
                    } else {
                        $stats['by_type']['other']++;
                    }
                } else {
                    $stats['by_type']['other']++;
                }
            }
        }
        $stats['unique_archives'] = count( $archive_bases );
        return $stats;
    }

    public static function get_preload_status() {
        $queue = get_option( 'wpsc_preload_queue', array() );
        return array(
            'queue_size'   => is_array( $queue ) ? count( $queue ) : 0,
            'last_run'     => get_option( 'wpsc_preload_last_run', 0 ),
            'is_running'   => WPSC_Preload::instance()->is_running(),
        );
    }

    public static function get_system_info() {
        global $wpdb;
        $info = array(
            'php_version'    => PHP_VERSION,
            'wp_version'     => get_bloginfo( 'version' ),
            'server'         => isset( $_SERVER['SERVER_SOFTWARE'] ) ? esc_html( $_SERVER['SERVER_SOFTWARE'] ) : 'Unknown',
            'cache_backend'  => self::detect_object_cache(),
            'disk_free'      => function_exists( 'disk_free_space' ) && ( $disk = @disk_free_space( ABSPATH ) ) !== false ? size_format( $disk ) : 'Unknown',
            'memory_limit'   => ini_get( 'memory_limit' ),
            'max_exec_time'  => ini_get( 'max_execution_time' ),
            'mysql_version'  => $wpdb->db_version(),
            'multisite'      => is_multisite() ? 'Yes' : 'No',
        );
        return $info;
    }

    private static function detect_object_cache() {
        if ( wp_using_ext_object_cache() ) {
            if ( class_exists( 'Redis' ) ) { return 'Redis'; }
            if ( class_exists( 'Memcached' ) ) { return 'Memcached'; }
            return 'External Object Cache';
        }
        return 'None (default WP)';
    }

    public static function get_settings_status() {
        $settings = WPSC_Settings::instance();
        return array(
            'public_cache_enabled'  => (bool) $settings->get( 'public_cache_enabled', true ),
            'private_cache_enabled' => (bool) $settings->get( 'private_cache_enabled', false ),
            'private_cache_active'  => (bool) $settings->get( 'private_cache_enabled', false ),
            'dropin_installed'      => defined( 'WP_CACHE' ) && WP_CACHE && WPSC_Dropin::is_installed(),
            'cache_backend'         => 'Disk (full-page)',
        );
    }

    public static function get_object_cache_status() {
        return array(
            'active'              => wp_using_ext_object_cache(),
            'redis_available'     => class_exists( 'Redis' ),
            'memcached_available' => class_exists( 'Memcached' ),
            'memcache_available'  => class_exists( 'Memcache' ),
            'connection'          => wp_using_ext_object_cache() ? 'active' : 'none',
            'backend'             => 'WordPress Object Cache (wp_cache_*)',
            'host'                => 'N/A',
            'port'                => 'N/A',
            'database'            => 'N/A',
            'default_lifetime'    => 'N/A',
        );
    }
}
