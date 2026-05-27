<?php
/**
 * Plugin Name: WP Static Cache
 * Plugin URI:  https://github.com/your-org/wp-static-cache
 * Description: Dual-layer caching plugin: disk-based public cache for visitors, isolated per-user private cache for logged-in users, plus smart preload with stale-while-revalidate for instant page delivery.
 * Version:     2.2.3
 * Author:      Sanat Das
 * Author URI:  https://wwinnovators.com
 * Text Domain: wp-static-cache
 * Domain Path: /languages
 */

defined( 'ABSPATH' ) || exit;

// AI agents: see AGENTS.md for key feature invariants that must be preserved.

define( 'WPSC_VERSION', '2.2.3' );
define( 'WPSC_FILE', __FILE__ );
define( 'WPSC_DIR', dirname( __FILE__ ) );
define( 'WPSC_INC_DIR', WPSC_DIR . '/includes/' );
define( 'WPSC_ADMIN_DIR', WPSC_DIR . '/admin/' );
define( 'WPSC_DROPIN_DIR', WPSC_DIR . '/dropin/' );
define( 'WPSC_CACHE_DIR_DEFAULT', WP_CONTENT_DIR . '/cache/wp-static-cache/' );
define( 'WPSC_DROPIN_PATH', WP_CONTENT_DIR . '/advanced-cache.php' );
define( 'WPSC_SETTINGS_KEY', 'wpsc_settings' );
define( 'WPSC_MIN_PHP', '7.4' );
define( 'WPSC_MIN_WP', '5.6' );

spl_autoload_register( function ( $class ) {
	$prefix = 'WPSC_';
	if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
		return;
	}
	$class_name = str_replace( $prefix, '', $class );
	$file_name  = 'class-wpsc-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
	$file_path  = WPSC_INC_DIR . $file_name;
	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
} );

require_once WPSC_INC_DIR . 'class-wpsc-functions.php';

register_activation_hook( WPSC_FILE, array( 'WPSC_Activation', 'activate' ) );
register_deactivation_hook( WPSC_FILE, array( 'WPSC_Activation', 'deactivate' ) );

if ( is_admin() ) {
	WPSC_Plugin::instance()->init_admin();
}
add_action( 'init', array( WPSC_Plugin::instance(), 'init' ) );