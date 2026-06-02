<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$cache_dir = defined( 'WPSC_CACHE_DIR_DEFAULT' ) ? WPSC_CACHE_DIR_DEFAULT : WP_CONTENT_DIR . '/cache/wp-static-cache/';
if ( file_exists( $cache_dir ) ) {
    $it = new RecursiveDirectoryIterator( $cache_dir, RecursiveDirectoryIterator::SKIP_DOTS );
    $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
    foreach ( $files as $file ) {
        if ( $file->isDir() ) { @rmdir( $file->getRealPath() ); }
        else { @unlink( $file->getRealPath() ); }
    }
    @rmdir( $cache_dir );
}

$dropin = WP_CONTENT_DIR . '/advanced-cache.php';
if ( file_exists( $dropin ) ) {
    $content = file_get_contents( $dropin );
    if ( strpos( $content, 'WP Static Cache' ) !== false ) {
        unlink( $dropin );
    }
}

delete_option( 'wpsc_settings' );
delete_option( 'wpsc_preload_queue' );
delete_option( 'wpsc_preload_queue_priority' );
delete_transient( 'wpsc_preload_lock' );

$hooks = array( 'wpsc_preload_batch', 'wpsc_cache_cleanup' );
foreach ( $hooks as $hook ) {
    $ts = wp_next_scheduled( $hook );
    if ( $ts ) { wp_unschedule_event( $ts, $hook ); }
}
