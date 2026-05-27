<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Cleanup {
    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setup_hooks() {
        add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );
        add_action( 'post_updated', array( $this, 'on_post_updated' ), 10, 3 );
        add_action( 'delete_post', array( $this, 'on_delete_post' ) );
        add_action( 'wp_trash_post', array( $this, 'on_trash_post' ) );
        add_action( 'edited_term', array( $this, 'on_edited_term' ), 10, 3 );
        add_action( 'comment_post', array( $this, 'on_comment_change' ) );
        add_action( 'wp_set_comment_status', array( $this, 'on_comment_change' ) );

        add_action( 'switch_theme',              array( $this, 'on_site_event' ) );
        add_action( 'wp_update_nav_menu',        array( $this, 'on_site_event' ) );
        add_action( 'customize_save_after',      array( $this, 'on_site_event' ) );
        add_action( 'activated_plugin',          array( $this, 'on_site_event' ) );
        add_action( 'deactivated_plugin',        array( $this, 'on_site_event' ) );
        add_action( 'update_option_permalink_structure', array( $this, 'on_site_event' ) );
        add_action( 'update_option_page_on_front',       array( $this, 'on_site_event' ) );
        add_action( 'update_option_page_for_posts',      array( $this, 'on_site_event' ) );
        add_action( 'widget_update_callback',    array( $this, 'on_widget_event' ), 99 );
        add_action( 'upgrader_process_complete', array( $this, 'on_upgrade_event' ), 10, 2 );
        add_action( 'profile_update',            array( $this, 'on_profile_update' ), 10, 2 );
        add_action( 'deleted_user',              array( $this, 'on_deleted_user' ) );
    }

    public function on_site_event() {
        $settings = WPSC_Settings::instance();
        $hook = current_filter();
        $setting_map = array(
            'switch_theme'              => 'autoflush_theme_switch',
            'wp_update_nav_menu'        => 'autoflush_menu_update',
            'customize_save_after'      => 'autoflush_customizer_save',
            'activated_plugin'          => 'autoflush_plugin_change',
            'deactivated_plugin'        => 'autoflush_plugin_change',
            'update_option_permalink_structure' => 'autoflush_permalink_change',
            'update_option_page_on_front'       => 'autoflush_reading_change',
            'update_option_page_for_posts'      => 'autoflush_reading_change',
        );
        $setting_key = isset( $setting_map[ $hook ] ) ? $setting_map[ $hook ] : null;
        if ( $setting_key && ! $settings->get( $setting_key, true ) ) {
            return;
        }
        WPSC_Public_Cache::flush_all();
        WPSC_Private_Cache::instance()->flush_all();
        WPSC_Logger::info( 'Public cache flushed by event: ' . $hook );
    }

    public function on_widget_event() {
        $settings = WPSC_Settings::instance();
        if ( ! $settings->get( 'autoflush_widget_change', true ) ) {
            return array();
        }
        $lock = 'wpsc_widget_flush_lock';
        if ( get_transient( $lock ) ) {
            return array();
        }
        set_transient( $lock, time(), 60 );
        WPSC_Public_Cache::flush_all();
        WPSC_Private_Cache::instance()->flush_all();
        WPSC_Logger::info( 'Public cache flushed by event: widget_update_callback' );
        return array();
    }

    public function on_upgrade_event( $upgrader, $options ) {
        $settings = WPSC_Settings::instance();
        if ( ! $settings->get( 'autoflush_upgrade', true ) ) {
            return;
        }
        $type = isset( $options['type'] ) ? $options['type'] : '';
        if ( in_array( $type, array( 'core', 'plugin', 'theme' ), true ) ) {
            $settings->update( array( 'cache_version' => (string) time() ) );
            WPSC_Public_Cache::flush_all();
            WPSC_Private_Cache::instance()->flush_all();
            WPSC_Logger::info( 'Public cache flushed by event: upgrade type=' . $type );
        }
    }

    public function on_profile_update( $user_id, $old_user_data ) {
        $settings = WPSC_Settings::instance();
        if ( ! $settings->get( 'autoflush_user_update', true ) ) {
            return;
        }
        if ( count_user_posts( $user_id ) > 0 ) {
            $url = get_author_posts_url( $user_id );
            WPSC_Public_Cache::flush_url( $url, true );
            WPSC_Private_Cache::instance()->flush_url( $url );
            WPSC_Preload::instance()->queue_urls( array( $url ) );
            WPSC_Logger::info( 'Author archive invalidated for user update', array( 'user_id' => $user_id ) );
        }
    }

    public function on_deleted_user( $user_id ) {
        $settings = WPSC_Settings::instance();
        if ( ! $settings->get( 'autoflush_user_update', true ) ) {
            return;
        }
        $url = get_author_posts_url( $user_id );
        WPSC_Public_Cache::flush_url( $url, true );
        WPSC_Private_Cache::instance()->flush_url( $url );
        WPSC_Preload::instance()->queue_urls( array( $url ) );
        WPSC_Logger::info( 'Author archive invalidated for deleted user', array( 'user_id' => $user_id ) );
    }

    public function on_transition_post_status( $new_status, $old_status, $post ) {
        if ( $new_status === $old_status ) {
            return;
        }
        if ( $new_status === 'publish' || ( $old_status === 'publish' && $new_status !== 'publish' ) ) {
            $this->invalidate_post( $post );
        }
    }

    public function on_post_updated( $post_id, $post_after, $post_before ) {
        if ( $post_after->post_status !== 'publish' && $post_before->post_status !== 'publish' ) {
            return;
        }
        if ( $post_after->post_status === 'publish' ) {
            $this->invalidate_post( $post_after );
        }
    }

    public function on_delete_post( $post_id ) {
        $post = get_post( $post_id );
        if ( $post ) {
            $this->invalidate_post( $post );
        }
    }

    public function on_trash_post( $post_id ) {
        $post = get_post( $post_id );
        if ( $post ) {
            $this->invalidate_post( $post );
        }
    }

    public function on_edited_term( $term_id, $tt_id, $taxonomy ) {
        $term_link = get_term_link( (int) $term_id, $taxonomy );
        if ( is_wp_error( $term_link ) ) {
            return;
        }
        WPSC_Public_Cache::flush_url( $term_link, true );
        WPSC_Private_Cache::instance()->flush_url( $term_link );
        $home_url = home_url( '/' );
        WPSC_Public_Cache::flush_url( $home_url, true );
        WPSC_Private_Cache::instance()->flush_url( $home_url );
        WPSC_Preload::instance()->queue_urls( array( $term_link, $home_url ) );
    }

    public function on_comment_change() {
        $post_id = get_the_ID();
        if ( $post_id ) {
            $post = get_post( $post_id );
            if ( $post ) {
                $this->invalidate_post( $post );
            }
        }
    }

    public function invalidate_post( $post ) {
        if ( ! $post ) { return; }
        $pc  = WPSC_Private_Cache::instance();
        $settings = WPSC_Settings::instance();
        $urls = array();

        $post_url = get_permalink( $post );
        if ( $post_url ) {
            $urls[] = $post_url;
            WPSC_Public_Cache::flush_url( $post_url, true );
            $pc->flush_url( $post_url );
        }

        $home_url = home_url( '/' );
        $urls[] = $home_url;
        WPSC_Public_Cache::flush_url( $home_url, true );
        $pc->flush_url( $home_url );

        $taxonomies = get_object_taxonomies( $post->post_type, 'objects' );
        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_post_terms( $post->ID, $taxonomy->name, array( 'fields' => 'ids' ) );
            if ( is_wp_error( $terms ) ) { continue; }
            foreach ( $terms as $term_id ) {
                $term_link = get_term_link( (int) $term_id, $taxonomy->name );
                if ( ! is_wp_error( $term_link ) ) {
                    $urls[] = $term_link;
                    WPSC_Public_Cache::flush_url( $term_link, true );
                    $pc->flush_url( $term_link );
                }
            }
        }

        $author_url = get_author_posts_url( $post->post_author );
        if ( $author_url ) {
            $urls[] = $author_url;
            WPSC_Public_Cache::flush_url( $author_url, true );
            $pc->flush_url( $author_url );
        }

        if ( get_option( 'page_for_posts' ) ) {
            $blog_url = get_permalink( get_option( 'page_for_posts' ) );
            if ( $blog_url ) {
                $urls[] = $blog_url;
                WPSC_Public_Cache::flush_url( $blog_url, true );
                $pc->flush_url( $blog_url );
            }
        }

        if ( $settings->get( 'autoflush_post_publish', true ) ) {
            $pc->flush_all();
            WPSC_Logger::info( 'Private cache flushed on post event', array( 'post_id' => $post->ID, 'post_type' => $post->post_type ) );
        } else {
            WPSC_Logger::info( 'Post invalidated', array( 'post_id' => $post->ID, 'post_type' => $post->post_type ) );
        }

        WPSC_Preload::instance()->queue_urls( $urls );
    }

}
