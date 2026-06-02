<?php
defined( "ABSPATH" ) || exit;

class WPSC_Settings {
    private static $instance = null;
    private $settings = null;
    private $tabs = array();
    private $fields = array();

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->register_defaults();$this->register_info_panels();
        $this->populate_dynamic_field_options();
    }

    private function register_defaults() {
        $this->register_tab( "dashboard", __( "Dashboard", "wp-static-cache" ) );
        $this->register_tab( "general", __( "General", "wp-static-cache" ) );
        $this->register_tab( "public-cache", __( "Public Cache", "wp-static-cache" ) );
        $this->register_tab( "private-cache", __( "Private Cache", "wp-static-cache" ) );
        $this->register_tab( "preload", __( "Preload", "wp-static-cache" ) );
        $this->register_tab( "exclusions", __( "Exclusions", "wp-static-cache" ) );
        $this->register_tab( "auto-flush", __( "Auto Flush", "wp-static-cache" ) );
        $this->register_tab( "logging", __( "Logging", "wp-static-cache" ) );
        $this->register_tab( "js-optimization", __( "JS Optimization", "wp-static-cache" ) );
        $this->register_tab( "image-optimization", __( "Image Optimization", "wp-static-cache" ) );
        $this->register_tab( "tools", __( "Tools", "wp-static-cache" ) );

        $this->add_fields( "general", array(
            "public_cache_enabled" => array( "type" => "toggle", "label" => "Public Cache", "desc" => "Enable disk-based page caching for visitors.", "default" => true ),
            "private_cache_enabled" => array( "type" => "toggle", "label" => "Private Cache", "desc" => "Enable full-page caching for logged-in users. Uses disk storage with optional wp_cache_* front tier.", "default" => false ),
            "homepage_ttl" => array( "type" => "number", "label" => "Homepage TTL (minutes)", "desc" => "Cache lifetime for homepage. 0 = never expire.", "default" => 60, "attrs" => array( "min" => 0, "max" => 525600 ) ),
            "taxonomy_ttl" => array( "type" => "number", "label" => "Taxonomy TTL (minutes)", "desc" => "Cache lifetime for archives.", "default" => 60, "attrs" => array( "min" => 0, "max" => 525600 ) ),
            "single_post_ttl" => array( "type" => "number", "label" => "Single Post TTL (minutes)", "desc" => "0 = never expire (invalidated only on update).", "default" => 0, "attrs" => array( "min" => 0, "max" => 525600 ) ),
            "other_ttl" => array( "type" => "number", "label" => "Other Pages TTL (minutes)", "desc" => "Cache lifetime for other page types.", "default" => 60, "attrs" => array( "min" => 0, "max" => 525600 ) ),
            "cache_dir" => array( "type" => "text", "label" => "Cache Directory", "desc" => "Absolute path for cache storage.", "default" => WP_CONTENT_DIR . "/cache/wp-static-cache/" ),
            "delete_on_deactivate" => array( "type" => "toggle", "label" => "Delete on Deactivation", "desc" => "Remove all cache files on deactivation.", "default" => true ),
            "cache_version" => array( "type" => "text", "label" => "Cache Version", "desc" => "Change to globally invalidate all cached pages.", "default" => "1.0" ),
        ) );

        $this->add_fields( "public-cache", array(
            "cacheable_qs" => array( "type" => "multi-text", "label" => "Cacheable Query Strings", "desc" => "Comma-separated.", "default" => "utm_source, utm_medium, utm_campaign, utm_term, utm_content, gclid, fbclid" ),
            "rejected_qs" => array( "type" => "multi-text", "label" => "Rejected Query Strings", "desc" => "Bypass cache if any of these params exist.", "default" => "nocache, preview, embed, elementor-preview" ),
            "cache_control_enabled" => array( "type" => "toggle", "label" => "Cache-Control Headers", "default" => true ),
            "cache_control_maxage" => array( "type" => "number", "label" => "Max-Age (seconds)", "default" => 3600, "attrs" => array( "min" => 0, "max" => 86400 ) ),
            "swr_window" => array( "type" => "number", "label" => "SWR Window (seconds)", "desc" => "Serve stale while regenerating.", "default" => 300, "attrs" => array( "min" => 0, "max" => 86400 ) ),
            "max_cache_file_size" => array( "type" => "number", "label" => "Max File Size (KB)", "desc" => "Pages larger than this are not cached.", "default" => 5120, "attrs" => array( "min" => 0, "max" => 102400 ) ),
            "max_cache_age" => array( "type" => "number", "label" => "Max Cache Age (days)", "desc" => "Automatically delete cached files older than this. 0 = disabled.", "default" => 0, "attrs" => array( "min" => 0, "max" => 365 ) ),
            "serve_method" => array( "type" => "select", "label" => "Serving Method", "default" => "php", "options" => array( "php"=>"PHP (drop-in)", "htaccess"=>".htaccess", "nginx"=>"Nginx" ) ),

        ) );
        $this->add_fields( "private-cache", array(
            "pc_ttl_private_pages" => array( "type" => "number", "label" => "Private Page TTL (seconds)", "desc" => "How long a private page stays cached. 0 = never expire. Applied per user-role group.", "default" => 300, "attrs" => array( "min" => 0, "max" => 86400 ) ),
        ) );

        $this->add_fields( "preload", array(
            "preload_enabled" => array( "type" => "toggle", "label" => "Enable Preload", "desc" => "Automatically scan and regenerate stale cached pages on a schedule.", "default" => false ),
            "preload_interval_value" => array( "type" => "number", "label" => "Interval Value", "desc" => "How often the preload cron runs.", "default" => 5, "attrs" => array( "min" => 1, "max" => 999999 ) ),
            "preload_interval_unit" => array( "type" => "select", "label" => "Interval Unit", "desc" => "Unit for the preload interval.", "default" => "minutes", "options" => array( "seconds"=>"Seconds", "minutes"=>"Minutes", "hours"=>"Hours", "days"=>"Days" ) ),
            "preload_batch_size" => array( "type" => "number", "label" => "Batch Size", "desc" => "URLs per batch. Higher = faster preload.", "default" => 10, "attrs" => array( "min" => 1, "max" => 100 ) ),
            "preload_max_urls" => array( "type" => "number", "label" => "Max URLs", "desc" => "Maximum to regenerate per run. 0=unlimited.", "default" => 1000, "attrs" => array( "min" => 0, "max" => 100000 ) ),
            "preload_timeout" => array( "type" => "number", "label" => "Timeout (s)", "desc" => "HTTP request timeout for regeneration.", "default" => 10, "attrs" => array( "min" => 1, "max" => 120 ) ),
            "preload_max_concurrent" => array( "type" => "number", "label" => "Max Concurrent Batches", "desc" => "Number of simultaneous batch processes (higher = faster preload).", "default" => 3, "attrs" => array( "min" => 1, "max" => 20 ) ),
            "preload_include_homepage" => array( "type" => "toggle", "label" => "Include Homepage", "desc" => "Preload the homepage URL.", "default" => true ),
            "preload_include_posts" => array( "type" => "toggle", "label" => "Include Posts", "desc" => "Preload recent posts.", "default" => true ),
            "preload_post_count" => array( "type" => "number", "label" => "Post Count", "desc" => "Number of recent posts.", "default" => 100, "attrs" => array( "min" => 1, "max" => 1000 ) ),
            "preload_include_pages" => array( "type" => "toggle", "label" => "Include Pages", "desc" => "Preload published pages.", "default" => true ),
            "preload_page_count" => array( "type" => "number", "label" => "Page Count", "desc" => "Number of recent pages.", "default" => 10, "attrs" => array( "min" => 1, "max" => 1000 ) ),
            "preload_post_types" => array( "type" => "checkbox_group", "label" => "Custom Post Types", "desc" => "Additional post types to include when preloading.", "default" => array( "post" ), "options" => array() ),
            "preload_include_taxonomies" => array( "type" => "toggle", "label" => "Include Taxonomies", "desc" => "Preload taxonomy archive pages.", "default" => true ),
            "preload_taxonomies" => array( "type" => "checkbox_group", "label" => "Taxonomies", "desc" => "Select taxonomies to include when preloading.", "default" => array(), "options" => array() ),
            "preload_max_terms" => array( "type" => "number", "label" => "Max Terms per Taxonomy", "desc" => "0 = no limit.", "default" => 0, "attrs" => array( "min" => 0, "max" => 10000 ) ),
            "preload_max_archive_pages" => array( "type" => "number", "label" => "Max Pagination Pages per Term", "desc" => "0 = no limit.", "default" => 1, "attrs" => array( "min" => 0, "max" => 1000 ) ),
            "preload_include_date_archives" => array( "type" => "toggle", "label" => "Include Date Archives", "desc" => "Preload yearly, monthly, daily archives.", "default" => false ),
            "preload_date_archive_months" => array( "type" => "number", "label" => "Date Archive Depth (months)", "desc" => "How many months back to preload.", "default" => 12, "attrs" => array( "min" => 1, "max" => 120 ) ),
            "preload_include_author_archives" => array( "type" => "toggle", "label" => "Include Author Archives", "desc" => "Preload author archive pages.", "default" => false ),
            "preload_include_menus" => array( "type" => "toggle", "label" => "Include Nav Menus", "desc" => "Preload all navigation menu URLs.", "default" => true ),
            "preload_custom_urls" => array( "type" => "textarea", "label" => "Custom URLs", "desc" => "One URL per line. Manually add specific URLs to preload.", "default" => "" ),
            "preload_user_agent" => array( "type" => "text", "label" => "User Agent", "desc" => "User-Agent header sent during regeneration.", "default" => "WPStaticCachePreload/1.0" ),
        ) );

        $this->add_fields( "exclusions", array(
            "exclude_urls" => array( "type" => "textarea", "label" => "URL Patterns (regex)", "desc" => "One per line. Matching URLs bypass cache.", "default" => "/cart/\n/checkout/\n/my-account/\n/wp-login\n/wp-admin\n/preview/\n\\.xml\n\\.txt" ),
            "exclude_qs" => array( "type" => "multi-text", "label" => "Query String Exclusions", "default" => "nocache, preview, embed, elementor-preview" ),
            "exclude_cookies" => array( "type" => "multi-text", "label" => "Cookie Exclusions", "desc" => "Bypass if cookie matches.", "default" => "comment_author_, woocommerce_items_in_cart" ),
            "exclude_user_agents" => array( "type" => "multi-text", "label" => "User Agent Exclusions", "default" => "w3c_validator, googlebot, bingbot, baiduspider" ),
            "exclude_post_ids" => array( "type" => "multi-text", "label" => "Exclude Post IDs", "default" => "" ),
            "exclude_rest_api" => array( "type" => "toggle", "label" => "Exclude REST API", "default" => true ),
            "exclude_feeds" => array( "type" => "toggle", "label" => "Exclude RSS Feeds", "default" => true ),
            "exclude_404" => array( "type" => "toggle", "label" => "Exclude 404 Pages", "default" => true ),
            "exclude_parameters" => array( "type" => "toggle", "label" => "Exclude URLs With Params", "default" => true ),
        ) );

        $this->add_fields( "auto-flush", array(
            "autoflush_theme_switch" => array( "type" => "toggle", "label" => "Flush on Theme Switch", "desc" => "Flush all cache when theme is changed.", "default" => false ),
            "autoflush_menu_update" => array( "type" => "toggle", "label" => "Flush on Menu Update", "desc" => "Flush all cache when navigation menus are updated.", "default" => false ),
            "autoflush_widget_change" => array( "type" => "toggle", "label" => "Flush on Widget Change", "desc" => "Flush all cache when widgets are added/removed/edited.", "default" => false ),
            "autoflush_customizer_save" => array( "type" => "toggle", "label" => "Flush on Customizer Save", "desc" => "Flush all cache when theme customizer settings are saved.", "default" => false ),
            "autoflush_plugin_change" => array( "type" => "toggle", "label" => "Flush on Plugin Activate/Deactivate", "desc" => "Flush all cache when a plugin is activated or deactivated.", "default" => false ),
            "autoflush_permalink_change" => array( "type" => "toggle", "label" => "Flush on Permalink Change", "desc" => "Flush all cache when permalink structure is updated.", "default" => false ),
            "autoflush_reading_change" => array( "type" => "toggle", "label" => "Flush on Reading Settings Change", "desc" => "Flush all cache when homepage or posts page settings change.", "default" => false ),
            "autoflush_upgrade" => array( "type" => "toggle", "label" => "Flush on WP/Plugin/Theme Update", "desc" => "Flush all cache when WordPress, plugins, or themes are updated.", "default" => false ),
            "autoflush_user_update" => array( "type" => "toggle", "label" => "Flush Author Archive on Profile Update", "desc" => "Flush author archive cache when a user updates their profile or is deleted.", "default" => false ),
            "autoflush_post_publish" => array( "type" => "toggle", "label" => "Flush All Private Cache on Post Publish/Update", "desc" => "Flush all private cached pages when a post is published, updated, deleted, or trashed. Fixes stale dynamic widgets like Recent Posts for logged-in users.", "default" => false ),
        ) );
        $this->add_fields( "js-optimization", array(
            "js_test_url" => array( "type" => "text", "label" => "Test URL", "default" => "" ),
            "js_remove_list" => array( "type" => "textarea", "label" => "Block List", "desc" => "Scripts to remove from page output.", "default" => "" ),
            "js_defer_include" => array( "type" => "textarea", "label" => "Defer List", "desc" => "Scripts to defer (add defer attribute).", "default" => "" ),
            "js_delay_include" => array( "type" => "textarea", "label" => "Delay List", "desc" => "Scripts to delay until user interaction.", "default" => "" ),
            "js_delay_timeout" => array( "type" => "number", "label" => "Delay Timeout (seconds)", "desc" => "Timeout before delayed scripts load automatically (1-120).", "default" => 5, "attrs" => array( "min" => 1, "max" => 120 ) ),
        ) );

        $this->add_fields( "image-optimization", array(
            "img_opt_enabled" => array( "type" => "toggle", "label" => "Enable Image Optimization", "desc" => "Generate and serve modern image formats (WebP/AVIF) to supported browsers for public cached pages. Converted files are stored alongside originals in the uploads directory.", "default" => false ),
            "img_opt_webp" => array( "type" => "toggle", "label" => "Generate WebP", "desc" => "Create WebP versions of JPEG, PNG, and GIF images.", "default" => true ),
            "img_opt_avif" => array( "type" => "toggle", "label" => "Generate AVIF", "desc" => "Create AVIF versions (requires PHP 8.1+).", "default" => false ),
            "img_opt_webp_quality" => array( "type" => "number", "label" => "WebP Quality", "desc" => "Quality for WebP conversion (1-100).", "default" => 82, "attrs" => array( "min" => 1, "max" => 100 ) ),
            "img_opt_avif_quality" => array( "type" => "number", "label" => "AVIF Quality", "desc" => "Quality for AVIF conversion (1-100).", "default" => 80, "attrs" => array( "min" => 1, "max" => 100 ) ),
            "img_opt_max_width" => array( "type" => "number", "label" => "Max Image Width (px)", "desc" => "Images wider than this will be resized before optimization. 0 = unlimited.", "default" => 2560, "attrs" => array( "min" => 0, "max" => 10000 ) ),
            "img_opt_max_height" => array( "type" => "number", "label" => "Max Image Height (px)", "desc" => "Images taller than this will be resized before optimization. 0 = unlimited.", "default" => 2560, "attrs" => array( "min" => 0, "max" => 10000 ) ),
            "img_opt_thumb_sizes" => array( "type" => "thumb_sizes", "label" => "Thumbnail Sizes", "desc" => "Custom thumbnail sizes managed via the Thumbnail Sizes section below.", "default" => array() ),
            "img_opt_max_per_run" => array( "type" => "number", "label" => "Max Images Per Run", "desc" => "Maximum number of most recently uploaded images to process per Optimize All run.", "default" => 100, "attrs" => array( "min" => 1, "max" => 9999 ) ),
            "img_opt_skip_classes" => array( "type" => "multi-text", "label" => "Skip CSS Classes", "desc" => "Image with any of these CSS classes will be skipped (comma-separated).", "default" => "skip-lazy, nopin, no-webp" ),
        ) );

        $this->add_fields( "logging", array(
            "logging_enabled" => array( "type" => "toggle", "label" => "Enable Logging", "desc" => "Write debug/info/error log files for troubleshooting.", "default" => false ),
            "log_level" => array( "type" => "select", "label" => "Log Level", "desc" => "Minimum severity to record.", "default" => "debug", "options" => array( "debug"=>"DEBUG (all)", "info"=>"INFO", "warning"=>"WARNING only", "error"=>"ERROR only" ) ),
            "log_max_size" => array( "type" => "number", "label" => "Max Log File Size (MB)", "desc" => "Auto-rotate when file exceeds this size.", "default" => 5, "attrs" => array( "min" => 1, "max" => 100 ) ),
            "log_cleanup_days" => array( "type" => "number", "label" => "Auto-Cleanup (days)", "desc" => "Delete log files older than this.", "default" => 30, "attrs" => array( "min" => 1, "max" => 365 ) ),
        ) );

        $this->add_fields( "tools", array(
            "flush_public" => array( "type" => "action_button", "label" => "Flush Public Cache", "action" => "flush_public" ),
            "flush_private" => array( "type" => "action_button", "label" => "Flush Private Cache", "action" => "flush_private" ),
            "flush_all" => array( "type" => "action_button", "label" => "Flush All Cache", "action" => "flush_all", "confirm" => "Are you sure?" ),
            "flush_expired" => array( "type" => "action_button", "label" => "Flush Expired Only", "action" => "flush_expired" ),
            "reset_defaults" => array( "type" => "action_button", "label" => "Reset All Settings to Defaults", "action" => "reset_defaults", "confirm" => "This will reset all plugin settings to their factory defaults. Cache data on disk will not be deleted. Continue?" ),
        ) );
    }
    public function register_tab( $slug, $title ) { $this->tabs[ $slug ] = $title; }

    public function add_fields( $tab, $fields ) {
        if ( ! isset( $this->fields[ $tab ] ) ) { $this->fields[ $tab ] = array(); }
        foreach ( $fields as $key => $def ) { $def["id"] = $key; $this->fields[ $tab ][ $key ] = $def; }
    }

    public function get_tabs() { return apply_filters( "wpsc_settings_tabs", $this->tabs ); }

    public function get_fields( $tab = null ) {
        if ( $tab ) { return apply_filters( "wpsc_settings_fields_$tab", isset( $this->fields[ $tab ] ) ? $this->fields[ $tab ] : array() ); }
        $all = array();
        foreach ( array_keys( $this->tabs ) as $t ) { $all[ $t ] = $this->get_fields( $t ); }
        return $all;
    }

    public function get_defaults() {
        static $defaults = null;
        if ( is_null( $defaults ) ) {
            $defaults = array();
            foreach ( $this->fields as $tab => $fields ) {
                foreach ( $fields as $key => $def ) {
                    $val = isset( $def["default"] ) ? $def["default"] : null;
                    if ( $def["type"] === "multi-text" && is_string( $val ) ) {
                        $parts = array_map( "trim", explode( ",", $val ) );
                        $val = array_values( array_filter( $parts, "strlen" ) );
                    }
                    $defaults[ $key ] = $val;
                }
            }
        }
        return apply_filters( "wpsc_settings_defaults", $defaults );
    }

    public function get( $key, $default = null ) {
        $settings = $this->get_all();
        if ( array_key_exists( $key, $settings ) ) { return $settings[ $key ]; }
        $defaults = $this->get_defaults();
        return array_key_exists( $key, $defaults ) ? $defaults[ $key ] : $default;
    }

    public function refresh() {
        $this->settings = null;
    }

    public function get_all() {
        if ( is_null( $this->settings ) ) {
            $saved = get_option( "wpsc_settings", array() );
            $this->settings = array_merge( $this->get_defaults(), is_array( $saved ) ? $saved : array() );
        }
        return $this->settings;
    }

    public function update( $new_settings ) {
        $this->get_all();
        foreach ( $new_settings as $key => $value ) { $this->settings[ $key ] = $value; }
        update_option( "wpsc_settings", $this->settings );
    }

    public function sanitize_field( $key, $value, $def ) {
        switch ( $def["type"] ) {
            case "toggle": return (bool) $value;
            case "number":
                $min = isset( $def["attrs"]["min"] ) ? (int) $def["attrs"]["min"] : 0;
                $max = isset( $def["attrs"]["max"] ) ? (int) $def["attrs"]["max"] : PHP_INT_MAX;
                return min( max( (int) $value, $min ), $max );
            case "multi-text":
                $parts = is_string( $value ) ? explode( ",", $value ) : ( is_array( $value ) ? $value : array() );
                return array_map( "trim", $parts );
            case "checkbox_group": return is_array( $value ) ? $value : array();
            case "textarea": return sanitize_textarea_field( $value );
            case "thumb_sizes":
                if ( ! is_array( $value ) ) {
                    return array();
                }
                $clean = array();
                foreach ( $value as $name => $dim ) {
                    $name = sanitize_key( $name );
                    if ( empty( $name ) ) {
                        continue;
                    }
                    $clean[ $name ] = array(
                        'width'  => isset( $dim['width'] ) ? max( 0, (int) $dim['width'] ) : 0,
                        'height' => isset( $dim['height'] ) ? max( 0, (int) $dim['height'] ) : 0,
                        'crop'   => ! empty( $dim['crop'] ),
                    );
                }
                return $clean;
            default: return sanitize_text_field( $value );
        }
    }

    public function sanitize( $input ) {
        $clean = $this->get_all();
        foreach ( $this->fields as $tab => $fields ) {
            foreach ( $fields as $key => $def ) {
                if ( isset( $input[ $key ] ) ) {
                    $clean[ $key ] = $this->sanitize_field( $key, $input[ $key ], $def );
                }
            }
        }
        return $clean;
    }

    public function render_fields( $fields ) {
        $settings = $this->get_all();
        $section_map = array(
            'general' => array(
                'public_cache_enabled' => 'Cache Mode',
                'private_cache_enabled' => 'Cache Mode',
                'homepage_ttl' => 'Cache Duration',
                'taxonomy_ttl' => 'Cache Duration',
                '_taxonomy_ttl_heading' => 'Cache Duration',
                'single_post_ttl' => 'Cache Duration',
                'other_ttl' => 'Cache Duration',
                'cache_dir' => 'Storage',
                'cache_version' => 'Storage',
                'delete_on_deactivate' => 'Cleanup',
            ),
            'public-cache' => array(
                'cache_control_enabled' => 'Delivery',
                'cache_control_maxage' => 'Delivery',
                'swr_window' => 'Delivery',
                'serve_method' => 'Delivery',
                'cacheable_qs' => 'Query Strings',
                'rejected_qs' => 'Query Strings',
                'max_cache_file_size' => 'Limits',
                'max_cache_age' => 'Limits',

            ),
            'private-cache' => array(
                'pc_ttl_private_pages' => 'Duration',
            ),
            'preload' => array(
                'preload_enabled' => 'Schedule',
                'preload_interval_value' => 'Schedule',
                'preload_interval_unit' => 'Schedule',
                'preload_batch_size' => 'Schedule',
                'preload_max_urls' => 'Schedule',
                'preload_timeout' => 'Schedule',
                'preload_max_concurrent' => 'Schedule',
                'preload_include_homepage' => 'Homepage',
                'preload_include_posts' => 'Content',
                'preload_post_count' => 'Content',
                'preload_include_pages' => 'Content',
                'preload_page_count' => 'Content',
                'preload_post_types' => 'Content',
                'preload_include_taxonomies' => 'Archives',
                'preload_taxonomies' => 'Archives',
                'preload_max_terms' => 'Archives',
                'preload_max_archive_pages' => 'Archives',
                'preload_include_date_archives' => 'Archives',
                'preload_date_archive_months' => 'Archives',
                'preload_include_author_archives' => 'Archives',
                'preload_include_menus' => 'Menus',
                'preload_custom_urls' => 'Custom URLs',
                'preload_user_agent' => 'Settings',
            ),
            'exclusions' => array(
                'exclude_urls' => 'URL Exclusions',
                'exclude_qs' => 'URL Exclusions',
                'exclude_cookies' => 'URL Exclusions',
                'exclude_user_agents' => 'URL Exclusions',
                'exclude_post_ids' => 'URL Exclusions',
                'exclude_rest_api' => 'Page Exclusions',
                'exclude_feeds' => 'Page Exclusions',
                'exclude_404' => 'Page Exclusions',
                'exclude_parameters' => 'Page Exclusions',
            ),
            'auto-flush' => array(
                'autoflush_theme_switch' => 'Auto Flush Events',
                'autoflush_menu_update' => 'Auto Flush Events',
                'autoflush_widget_change' => 'Auto Flush Events',
                'autoflush_customizer_save' => 'Auto Flush Events',
                'autoflush_plugin_change' => 'Auto Flush Events',
                'autoflush_permalink_change' => 'Auto Flush Events',
                'autoflush_reading_change' => 'Auto Flush Events',
                'autoflush_upgrade' => 'Auto Flush Events',
                'autoflush_user_update' => 'Auto Flush Events',
                'autoflush_post_publish' => 'Auto Flush Events',
            ),
            'js-optimization' => array(
                'js_test_url' => 'Test Tool',
                'js_remove_list' => 'Test Tool',
                'js_defer_include' => 'Test Tool',
                'js_delay_include' => 'Test Tool',
                'js_delay_timeout' => 'Test Tool',
            ),
            'image-optimization' => array(
                'img_opt_enabled' => 'General',
                'img_opt_webp' => 'Formats',
                'img_opt_avif' => 'Formats',
                'img_opt_webp_quality' => 'Quality',
                'img_opt_avif_quality' => 'Quality',
                'img_opt_max_width' => 'Resizing',
                'img_opt_max_height' => 'Resizing',
                'img_opt_thumb_sizes' => null,
                'img_opt_max_per_run' => 'Bulk Optimizer',
                'img_opt_skip_classes' => 'Bulk Optimizer',
            ),
            'logging' => array(
                'logging_enabled' => 'Log Settings',
                'log_level' => 'Log Settings',
                'log_max_size' => 'Log Settings',
                'log_cleanup_days' => 'Log Settings',
            ),
        );
        $current_section = null;
        $table_open = false;
        foreach ( $fields as $key => $def ) {
            $tab = '';
            foreach ( $this->tabs as $slug => $title ) {
                if ( isset( $this->fields[ $slug ][ $key ] ) ) {
                    $tab = $slug;
                    break;
                }
            }
            $section = isset( $section_map[ $tab ][ $key ] ) ? $section_map[ $tab ][ $key ] : null;
            if ( $section === null && strpos( $key, 'taxonomy_ttl_' ) === 0 ) {
                $section = 'Cache Duration';
            }
            if ( $section !== $current_section ) {
                if ( $table_open ) {
                    echo '</table>';
                    $table_open = false;
                }
                $current_section = $section;
                if ( $section ) {
                    echo '<h3 class="wpsc-section-title">' . esc_html( $section ) . '</h3>';
                }
                echo '<table class="form-table' . ( $section ? ' wpsc-section' : '' ) . '">';
                $table_open = true;
            }
            $value = isset( $settings[ $key ] ) ? $settings[ $key ] : ( isset( $def["default"] ) ? $def["default"] : "" );
            $desc = isset( $def["desc"] ) ? $def["desc"] : "";
            if ( $def["type"] === "thumb_sizes" ) {
                continue;
            }
            if ( $def["type"] === "subheading" ) {
                ?>
                <tr class="wpsc-subheading-row">
                    <th scope="row" colspan="2">
                        <h4 style="margin:16px 0 4px;color:#50575e;"><?php echo esc_html( $def["label"] ); ?></h4>
                    </th>
                </tr>
                <?php
                continue;
            }
            ?>
            <tr>
                <th scope="row"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def["label"] ); ?></label></th>
                <td>
                    <?php
                    switch ( $def["type"] ) {
                        case "toggle":
                            ?>
                            <input type="hidden" name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="0" />
                            <label class="wpsc-toggle">
                                <input type="checkbox" name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $value, true ); ?> />
                                <span class="wpsc-toggle-slider"></span>
                            </label>
                            <?php
                            break;
                        case "number":
                            $min = isset( $def["attrs"]["min"] ) ? $def["attrs"]["min"] : 0;
                            $max = isset( $def["attrs"]["max"] ) ? $def["attrs"]["max"] : 999999;
                            ?>
                            <input type="number" name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" class="small-text" />
                            <?php
                            break;
                        case "text":
                            ?>
                            <input type="text" name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
                            <?php
                            break;
                        case "multi-text":
                            $val = is_array( $value ) ? implode( ", ", $value ) : $value;
                            ?>
                            <input type="text" name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>" class="regular-text" />
                            <?php
                            break;
                        case "textarea":
                            ?>
                            <textarea name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>" rows="6" class="large-text code"><?php echo esc_textarea( $value ); ?></textarea>
                            <?php
                            break;
                        case "select":
                            ?>
                            <select name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" id="<?php echo esc_attr( $key ); ?>">
                                <?php foreach ( $def["options"] as $opt_val => $opt_label ) : ?>
                                    <option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $value, $opt_val ); ?>><?php echo esc_html( $opt_label ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php
                            break;
                        case "checkbox_group":
                            ?>
                            <input type="hidden" name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="" />
                            <?php
                            foreach ( $def["options"] as $opt_val => $opt_label ) :
                                $checked = is_array( $value ) && in_array( $opt_val, $value );
                                ?>
                                <label class="wpsc-checkbox-label">
                                    <input type="checkbox" name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $key ); ?>][]" value="<?php echo esc_attr( $opt_val ); ?>" <?php checked( $checked ); ?> />
                                    <?php echo esc_html( $opt_label ); ?>
                                </label><br />
                            <?php endforeach;
                            break;
                        case "action_button":
                            $confirm = isset( $def["confirm"] ) ? $def["confirm"] : "";
                            ?>
                            <button type="button" class="button wpsc-action-btn" data-action="<?php echo esc_attr( $def["action"] ); ?>" data-confirm="<?php echo esc_attr( $confirm ); ?>">
                                <?php echo esc_html( $def["label"] ); ?>
                            </button>
                            <?php
                            break;
                        case "info_panel":
                            ?>
                            <div class="wpsc-info-panel" data-panel="<?php echo esc_attr( $def["action"] ?? $key ); ?>">
                                <p class="description"><?php esc_html_e( 'Loading...', 'wp-static-cache' ); ?></p>
                            </div>
                            <?php
                            break;
                        case "thumb_sizes":
                            break;
                    }
                    if ( $desc ) {
                        echo '<p class="description">' . esc_html( $desc ) . '</p>';
                    }
                    ?>
                </td>
            </tr>
            <?php
        }
        if ( $table_open ) {
            echo '</table>';
        }
    }
    private function populate_dynamic_field_options() {
        if ( isset( $this->fields['preload']['preload_post_types'] ) ) {
            $pts = get_post_types( array( 'public' => true ), 'objects' );
            $options = array();
            foreach ( $pts as $pt ) {
                $options[ $pt->name ] = $pt->label;
            }
            $this->fields['preload']['preload_post_types']['options'] = $options;
        }
        if ( isset( $this->fields['preload']['preload_taxonomies'] ) ) {
            $taxes = get_taxonomies( array( 'public' => true ), 'objects' );
            $options = array();
            foreach ( $taxes as $tax ) {
                $options[ $tax->name ] = $tax->label;
            }
            $this->fields['preload']['preload_taxonomies']['options'] = $options;
            $this->fields['preload']['preload_taxonomies']['default'] = array( 'category' );
        }
        if ( isset( $this->fields['general'] ) ) {
            $taxes = get_taxonomies( array( 'public' => true ), 'objects' );
            $global_ttl = $this->get( 'taxonomy_ttl', 60 );
            $injected = array();
            $inserted = false;
            foreach ( $this->fields['general'] as $key => $def ) {
                $injected[ $key ] = $def;
                if ( $key === 'taxonomy_ttl' && ! empty( $taxes ) ) {
                    $injected['_taxonomy_ttl_heading'] = array(
                        'type'  => 'subheading',
                        'label' => __( 'Per-Taxonomy TTL', 'wp-static-cache' ),
                    );
                    foreach ( $taxes as $tax ) {
                        $k = 'taxonomy_ttl_' . $tax->name;
                        if ( ! isset( $injected[ $k ] ) ) {
                            $injected[ $k ] = array(
                                'type'    => 'number',
                                'label'   => sprintf( __( '%s TTL (minutes)', 'wp-static-cache' ), $tax->label ),
                                'desc'    => sprintf( __( 'Leave empty to inherit global Taxonomy TTL (%d min).', 'wp-static-cache' ), $global_ttl ),
                                'default' => '',
                                'attrs'   => array( 'min' => 0, 'max' => 525600 ),
                            );
                        }
                    }
                    $inserted = true;
                }
            }
            if ( $inserted ) {
                $this->fields['general'] = $injected;
            }
        }
        if ( isset( $this->fields['js-optimization']['js_test_url'] ) ) {
            $this->fields['js-optimization']['js_test_url']['default'] = home_url( '/' );
        }

    }

    public function register_info_panels() {
        $this->add_fields( "tools", array(
            "cache_stats" => array( "type" => "info_panel", "label" => "Cache Statistics", "action" => "cache_stats" ),
            "preload_status" => array( "type" => "info_panel", "label" => "Preload Status", "action" => "preload_status" ),
            "system_info" => array( "type" => "info_panel", "label" => "System Information", "action" => "system_info" ),
        ) );
    }


}

