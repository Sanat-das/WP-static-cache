<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Activation {
    public static function activate() {
        self::check_requirements();
        self::create_cache_dir();
        self::write_dropin();
        WPSC_Dropin::ensure_wp_cache_constant();
        self::set_cron_schedules();
        self::set_default_options();
    }

	public static function deactivate() {
		$settings = WPSC_Settings::instance();
		if ( $settings->get( 'delete_on_deactivate', true ) ) {
			self::delete_cache_dir();
		}
		self::remove_dropin();
		self::clear_cron_schedules();
		WPSC_Server::instance()->remove_htaccess();
	}

    private static function check_requirements() {
        if ( version_compare( PHP_VERSION, WPSC_MIN_PHP, '<' ) ) {
            deactivate_plugins( plugin_basename( WPSC_FILE ) );
            wp_die( sprintf( __( 'WP Static Cache requires PHP %s or higher.', 'wp-static-cache' ), WPSC_MIN_PHP ) );
        }
        global $wp_version;
        if ( version_compare( $wp_version, WPSC_MIN_WP, '<' ) ) {
            deactivate_plugins( plugin_basename( WPSC_FILE ) );
            wp_die( sprintf( __( 'WP Static Cache requires WordPress %s or higher.', 'wp-static-cache' ), WPSC_MIN_WP ) );
        }
    }

    private static function create_cache_dir() {
        $dir = WPSC_CACHE_DIR_DEFAULT;
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        file_put_contents( $dir . 'index.php', '<?php // Silence.' );
        $htaccess = $dir . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Deny from all\n" );
        }
    }

    private static function delete_cache_dir() {
        $dir = WPSC_CACHE_DIR_DEFAULT;
        if ( file_exists( $dir ) ) {
            WPSC_Public_Cache::flush_all();
            WPSC_Private_Cache::instance()->flush_all();
        }
    }

    public static function write_dropin() {
        WPSC_Dropin::write();
    }

    private static function remove_dropin() {
        if ( file_exists( WPSC_DROPIN_PATH ) ) {
            $content = file_get_contents( WPSC_DROPIN_PATH );
            if ( strpos( $content, 'WP Static Cache' ) !== false ) {
                unlink( WPSC_DROPIN_PATH );
            }
        }
    }

    private static function set_cron_schedules() {
    }

    private static function clear_cron_schedules() {
        $hooks = array( 'wpsc_preload_batch' );
        foreach ( $hooks as $hook ) {
            $ts = wp_next_scheduled( $hook );
            if ( $ts ) {
                wp_unschedule_event( $ts, $hook );
            }
        }
    }

    private static function set_default_options() {
        if ( ! get_option( WPSC_SETTINGS_KEY ) ) {
            $defaults = WPSC_Settings::instance()->get_defaults();
            update_option( WPSC_SETTINGS_KEY, $defaults );
        }
    }
}
