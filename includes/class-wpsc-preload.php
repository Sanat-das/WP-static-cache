<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Preload {
    private static $instance = null;
    private $settings;
    private $queue_key = 'wpsc_preload_queue';
    private $priority_queue_key = 'wpsc_preload_queue_priority';
    private $slots_key = 'wpsc_preload_slots';
    private $force_key = 'wpsc_preload_force';

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = WPSC_Settings::instance();
    }

    public function start() {
        if ( ! $this->settings->get( 'preload_enabled', false ) ) {
            return;
        }
        $queue = $this->discover_urls();
        if ( empty( $queue ) ) {
            WPSC_Logger::info( 'Preload now: no URLs discovered' );
            return;
        }
        update_option( $this->queue_key, $queue, false );
        update_option( 'wpsc_preload_last_run', time() );
        set_transient( $this->force_key, true, 3600 );
        $this->schedule_next_batch();
        $this->process_batch( true );
    }

    public function process_batch( $force = false ) {
        if ( ! $this->settings->get( 'preload_enabled', false ) ) {
            return;
        }

        $force = $force || get_transient( $this->force_key );

        // Drain priority queue first (post events, manual flush).
        $pqueue = get_option( $this->priority_queue_key, array() );
        if ( ! is_array( $pqueue ) ) {
            $pqueue = array();
        }
        if ( ! empty( $pqueue ) ) {
            $this->process_queue( $this->priority_queue_key, $pqueue, $force );
            return;
        }

        // Fall back to main queue.
        $queue = get_option( $this->queue_key, array() );
        if ( ! is_array( $queue ) ) {
            $queue = array();
        }

        if ( empty( $queue ) ) {
            delete_transient( $this->force_key );
            $queue = $this->scan_stale_cache();
            if ( empty( $queue ) ) {
                return;
            }
            update_option( $this->queue_key, $queue, false );
        }

        $this->process_queue( $this->queue_key, $queue, $force );
    }

    private function process_queue( $key, $queue, $force ) {
        if ( ! $this->acquire_slot() ) {
            return;
        }

        $batch_size = (int) $this->settings->get( 'preload_batch_size', 10 );
        $timeout = (int) $this->settings->get( 'preload_timeout', 10 );
        $user_agent = $this->settings->get( 'preload_user_agent', 'WPStaticCachePreload/1.0' );

        $batch = array_splice( $queue, 0, $batch_size );
        $failed = array();

        foreach ( $batch as $url ) {
            if ( ! $force && $this->is_cache_fresh( $url ) ) {
                continue;
            }
            $result = $this->process_url( $url, $timeout, $user_agent );
            if ( ! $result ) {
                $failed[] = $url;
            }
        }

        $failed = array_unique( array_merge( $failed, $queue ) );
        $max = (int) $this->settings->get( 'preload_max_urls', 1000 );
        if ( $max > 0 && count( $failed ) > $max ) {
            $failed = array_slice( $failed, 0, $max );
        }

        if ( ! empty( $failed ) ) {
            update_option( $key, $failed, false );
            $this->schedule_next_batch();
            WPSC_Logger::debug( 'Preload batch processed with queue pending', array( 'queue_key' => $key, 'processed' => count( $batch ), 'remaining' => count( $failed ) ) );
        } else {
            delete_option( $key );
            if ( $key === $this->queue_key ) {
                delete_transient( $this->force_key );
            }
            update_option( 'wpsc_preload_last_run', time() );
            WPSC_Logger::info( 'Preload queue completed', array( 'queue_key' => $key ) );
        }

        $this->release_slot();
    }

    private function schedule_next_batch() {
        if ( ! wp_next_scheduled( 'wpsc_preload_batch' ) ) {
            wp_schedule_single_event( time() + 1, 'wpsc_preload_batch' );
        }
    }

    public function process_url( $url, $timeout = null, $user_agent = null ) {
        if ( is_null( $timeout ) ) {
            $timeout = (int) $this->settings->get( 'preload_timeout', 10 );
        }
        if ( is_null( $user_agent ) ) {
            $user_agent = $this->settings->get( 'preload_user_agent', 'WPStaticCachePreload/1.0' );
        }
        $redirects = 5;
        $args = array(
            'timeout'     => $timeout,
            'blocking'    => true,
            'sslverify'   => false,
            'redirection' => $redirects,
            'headers'     => array(
                'User-Agent' => $user_agent,
                'X-WPSC-Preload' => '1',
            ),
        );
        WPSC_Logger::debug( 'Preload regenerating URL', array( 'url' => $url ) );
        $response = wp_remote_get( $url, $args );
        if ( is_wp_error( $response ) ) {
            WPSC_Logger::warning( 'Preload regeneration failed, will retry on next cron', array( 'url' => $url, 'error' => $response->get_error_message() ) );
            return false;
        }
        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        if ( $code !== 200 || strlen( $body ) < 255 ) {
            if ( $code === 404 ) {
                WPSC_Logger::warning( 'Preload skipping URL (404), removed from queue', array( 'url' => $url ) );
                $this->delete_cache_for_url( $url );
                return true;
            }
            WPSC_Logger::warning( 'Preload regeneration returned non-200 or too small, will retry on next cron', array( 'url' => $url, 'code' => $code, 'size' => strlen( $body ) ) );
            return false;
        }
        WPSC_Logger::debug( 'Preload regeneration successful', array( 'url' => $url, 'code' => $code, 'size' => strlen( $body ) ) );
        return true;
    }

    private function delete_cache_for_url( $url ) {
        $path = parse_url( $url, PHP_URL_PATH );
        if ( empty( $path ) ) {
            $path = '/';
        }
        $path = rtrim( $path, '/' );
        if ( empty( $path ) ) {
            $path = '/index';
        }
        $cache_dir = trailingslashit( $this->settings->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) ) . 'public';
        $item_dir  = $cache_dir . $path;
        if ( is_dir( $item_dir ) ) {
            $it = new RecursiveDirectoryIterator( $item_dir, RecursiveDirectoryIterator::SKIP_DOTS );
            $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
            foreach ( $files as $file ) {
                if ( $file->isDir() ) {
                    rmdir( $file->getRealPath() );
                } else {
                    unlink( $file->getRealPath() );
                }
            }
            @rmdir( $item_dir );
            WPSC_Logger::debug( 'Preload deleted stale cache for 404 URL', array( 'url' => $url ) );
        }
    }

    private function discover_urls() {
        $max = (int) $this->settings->get( 'preload_max_urls', 1000 );
        $urls = array();

        // 1. Homepage
        if ( $this->settings->get( 'preload_include_homepage', true ) ) {
            $urls[] = home_url( '/' );
        }

        // 2. Pages
        if ( $this->settings->get( 'preload_include_pages', true ) ) {
            $page_count = (int) $this->settings->get( 'preload_page_count', 10 );
            $pages = get_posts( array(
                'post_type'      => 'page',
                'posts_per_page' => $page_count,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ) );
            foreach ( $pages as $p ) {
                $urls[] = get_permalink( $p );
            }
        }

        // 3. Archives (taxonomies)
        if ( $this->settings->get( 'preload_include_taxonomies', true ) ) {
            $selected_tax = (array) $this->settings->get( 'preload_taxonomies', array() );
            $max_terms = (int) $this->settings->get( 'preload_max_terms', 0 );
            $max_archive_pages = (int) $this->settings->get( 'preload_max_archive_pages', 10 );
            if ( ! empty( $selected_tax ) ) {
                foreach ( $selected_tax as $tax ) {
                    $term_args = array( 'taxonomy' => $tax, 'hide_empty' => true );
                    if ( $max_terms > 0 ) {
                        $term_args['number'] = $max_terms;
                    }
                    $terms = get_terms( $term_args );
                    if ( is_wp_error( $terms ) ) {
                        continue;
                    }
                    $per_page = max( 1, (int) get_option( 'posts_per_page', 10 ) );
                    $pagination_base = $GLOBALS['wp_rewrite']->pagination_base;
                    foreach ( $terms as $term ) {
                        $link = get_term_link( $term );
                        if ( ! is_wp_error( $link ) ) {
                            $urls[] = $link;
                            if ( $max_archive_pages !== 0 ) {
                                $total_pages = ceil( $term->count / $per_page );
                                $max_pages = min( $total_pages, $max_archive_pages );
                                for ( $p = 2; $p <= $max_pages; $p++ ) {
                                    $urls[] = trailingslashit( $link ) . $pagination_base . '/' . $p . '/';
                                }
                            }
                        }
                    }
                }
            }
        }

        // 4. Posts (post post type)
        if ( $this->settings->get( 'preload_include_posts', true ) ) {
            $count = (int) $this->settings->get( 'preload_post_count', 10 );
            $post_types = (array) $this->settings->get( 'preload_post_types', array( 'post' ) );

            if ( in_array( 'post', $post_types, true ) ) {
                $posts = get_posts( array(
                    'post_type'      => 'post',
                    'posts_per_page' => $count,
                    'post_status'    => 'publish',
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                ) );
                foreach ( $posts as $p ) {
                    $urls[] = get_permalink( $p );
                }
            }
        }

        // 5. Other (remaining custom post types)
        if ( $this->settings->get( 'preload_include_posts', true ) ) {
            $count = (int) $this->settings->get( 'preload_post_count', 10 );
            $post_types = (array) $this->settings->get( 'preload_post_types', array( 'post' ) );
            $remaining = array_diff( $post_types, array( 'post' ) );

            if ( ! empty( $remaining ) ) {
                $other = get_posts( array(
                    'post_type'      => array_values( $remaining ),
                    'posts_per_page' => $count,
                    'post_status'    => 'publish',
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                ) );
                foreach ( $other as $p ) {
                    $urls[] = get_permalink( $p );
                }
            }
        }

        // 6. Date archives
        if ( $this->settings->get( 'preload_include_date_archives', false ) ) {
            $months_back = (int) $this->settings->get( 'preload_date_archive_months', 12 );
            for ( $i = 0; $i < $months_back; $i++ ) {
                $time = strtotime( "-{$i} months" );
                $year = (int) date( 'Y', $time );
                $month = (int) date( 'm', $time );
                $urls[] = get_year_link( $year );
                $urls[] = get_month_link( $year, $month );
            }
        }

        // 7. Author archives
        if ( $this->settings->get( 'preload_include_author_archives', false ) ) {
            $authors = get_users( array( 'who' => 'authors', 'has_published_posts' => true ) );
            foreach ( $authors as $author ) {
                $url = get_author_posts_url( $author->ID );
                if ( ! empty( $url ) ) {
                    $urls[] = $url;
                }
            }
        }

        // 8. Nav menus
        if ( $this->settings->get( 'preload_include_menus', true ) ) {
            $menus = wp_get_nav_menus();
            foreach ( $menus as $menu ) {
                $items = wp_get_nav_menu_items( $menu->term_id );
                if ( is_array( $items ) ) {
                    foreach ( $items as $item ) {
                        if ( ! empty( $item->url ) ) {
                            $urls[] = $item->url;
                        }
                    }
                }
            }
        }

        // 9. Custom URLs
        $custom_urls = $this->settings->get( 'preload_custom_urls', '' );
        if ( ! empty( $custom_urls ) && is_string( $custom_urls ) ) {
            $lines = explode( "\n", $custom_urls );
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( ! empty( $line ) ) {
                    $urls[] = $line;
                }
            }
        }

        $urls = array_unique( $urls );
        if ( $max > 0 ) {
            $urls = array_slice( $urls, 0, $max );
        }
        WPSC_Logger::debug( 'URL discovery complete', array( 'url_count' => count( $urls ) ) );
        return $urls;
    }

    private function scan_stale_cache() {
        $cache_dir = trailingslashit( $this->settings->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) ) . 'public';
        if ( ! is_dir( $cache_dir ) ) {
            return array();
        }

        $stale_urls = array();
        $it = new RecursiveDirectoryIterator( $cache_dir, RecursiveDirectoryIterator::SKIP_DOTS );
        $files = new RecursiveIteratorIterator( $it );
        $now = time();
        $max = (int) $this->settings->get( 'preload_max_urls', 1000 );

        foreach ( $files as $file ) {
            if ( $file->getFilename() !== '.meta.json' ) {
                continue;
            }
            $meta = json_decode( file_get_contents( $file->getRealPath() ), true );
            if ( ! $meta || ! isset( $meta['uri'], $meta['expires_at'] ) ) {
                continue;
            }
            if ( (int) $meta['expires_at'] === 0 ) {
                continue;
            }
            if ( $now >= (int) $meta['expires_at'] ) {
                $stale_urls[] = home_url( $meta['uri'] );
                if ( $max > 0 && count( $stale_urls ) >= $max ) {
                    break;
                }
            }
        }

        $stale_urls = array_unique( $stale_urls );
        WPSC_Logger::debug( 'Stale cache scan complete', array( 'stale_count' => count( $stale_urls ) ) );
        return $stale_urls;
    }

    public function stop() {
        delete_option( $this->queue_key );
        delete_option( $this->priority_queue_key );
        delete_transient( $this->slots_key );
        delete_transient( $this->force_key );
        $cron = wp_next_scheduled( 'wpsc_preload_batch' );
        if ( $cron ) { wp_unschedule_event( $cron, 'wpsc_preload_batch' ); }
    }

    public function queue_urls( $urls, $priority = false ) {
        if ( empty( $urls ) ) { return; }
        if ( ! $this->settings->get( 'preload_enabled', false ) ) { return; }
        $key = $priority ? $this->priority_queue_key : $this->queue_key;
        $queue = get_option( $key, array() );
        if ( ! is_array( $queue ) ) { $queue = array(); }
        foreach ( (array) $urls as $url ) {
            if ( ! in_array( $url, $queue, true ) ) {
                $queue[] = $url;
            }
        }
        update_option( $key, $queue, false );
        $this->schedule_next_batch();
        WPSC_Logger::debug( 'URLs queued for preload', array( 'count' => count( (array) $urls ), 'priority' => $priority ) );
    }

    public function get_queue_size() {
        $queue = get_option( $this->queue_key, array() );
        $pqueue = get_option( $this->priority_queue_key, array() );
        return ( is_array( $queue ) ? count( $queue ) : 0 ) + ( is_array( $pqueue ) ? count( $pqueue ) : 0 );
    }

    public function is_running() {
        return $this->get_concurrent_count() > 0;
    }

    private function is_cache_fresh( $url ) {
        $path = parse_url( $url, PHP_URL_PATH );
        if ( empty( $path ) ) {
            $path = '/';
        }
        $path = rtrim( $path, '/' );
        if ( empty( $path ) ) {
            $path = '/index';
        }
        $cache_dir = trailingslashit( $this->settings->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) );
        $meta_file = $cache_dir . 'public' . $path . '/.meta.json';
        if ( ! file_exists( $meta_file ) ) {
            return false;
        }
        $meta = json_decode( file_get_contents( $meta_file ), true );
        if ( ! $meta || ! isset( $meta['expires_at'] ) ) {
            return false;
        }
        if ( (int) $meta['expires_at'] === 0 ) {
            return true;
        }
        return time() < (int) $meta['expires_at'];
    }

    private function acquire_slot() {
        $max = (int) $this->settings->get( 'preload_max_concurrent', 3 );
        $current = $this->get_concurrent_count();
        if ( $current >= $max ) {
            return false;
        }
        set_transient( $this->slots_key, $current + 1, 300 );
        return true;
    }

    private function release_slot() {
        $current = $this->get_concurrent_count();
        if ( $current <= 1 ) {
            delete_transient( $this->slots_key );
        } else {
            set_transient( $this->slots_key, $current - 1, 300 );
        }
    }

    private function get_concurrent_count() {
        return (int) get_transient( $this->slots_key );
    }
}
