<?php
defined( "ABSPATH" ) || exit;

class WPSC_Logger {
    const DEBUG   = 0;
    const INFO    = 1;
    const WARNING = 2;
    const ERROR   = 3;

    private static $levels = array(
        "debug"   => self::DEBUG,
        "info"    => self::INFO,
        "warning" => self::WARNING,
        "error"   => self::ERROR,
    );

    public static function debug( $message, $context = array() ) {
        self::write( "DEBUG", $message, $context );
    }

    public static function info( $message, $context = array() ) {
        self::write( "INFO", $message, $context );
    }

    public static function warning( $message, $context = array() ) {
        self::write( "WARNING", $message, $context );
    }

    public static function error( $message, $context = array() ) {
        self::write( "ERROR", $message, $context );
    }

    private static function write( $level_name, $message, $context ) {
        $settings = WPSC_Settings::instance();
        if ( ! $settings->get( "logging_enabled", false ) ) {
            return;
        }
        $level_num = isset( self::$levels[ strtolower( $level_name ) ] ) ? self::$levels[ strtolower( $level_name ) ] : self::INFO;
        $config_level = strtolower( $settings->get( "log_level", "info" ) );
        $config_num = isset( self::$levels[ $config_level ] ) ? self::$levels[ $config_level ] : self::INFO;
        if ( $level_num < $config_num ) {
            return;
        }
        $log_dir = self::get_log_dir();
        if ( ! is_dir( $log_dir ) ) {
            wp_mkdir_p( $log_dir );
            file_put_contents( $log_dir . "index.php", "<?php // Silence." );
            file_put_contents( $log_dir . ".htaccess", "Deny from all\n" );
        }
        $log_file = $log_dir . "debug.log";
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
        $caller = isset( $backtrace[2] ) ? $backtrace[2] : ( isset( $backtrace[1] ) ? $backtrace[1] : array() );
        $source = basename( isset( $caller["file"] ) ? $caller["file"] : "unknown" );
        $context_str = ! empty( $context ) ? " " . wp_json_encode( $context ) : "";
        $entry = sprintf(
            "[%s] [%s] [%s] %s%s\n",
            current_time( "mysql" ),
            strtoupper( $level_name ),
            $source,
            $message,
            $context_str
        );
        file_put_contents( $log_file, $entry, FILE_APPEND | LOCK_EX );
        self::rotate_if_needed( $log_file );
    }

    public static function get_log_dir() {
        $cache_dir = trailingslashit( WPSC_Settings::instance()->get( "cache_dir", WPSC_CACHE_DIR_DEFAULT ) );
        return $cache_dir . "logs/";
    }

    public static function rotate_if_needed( $file ) {
        if ( ! file_exists( $file ) ) {
            return;
        }
        $max_size = (int) WPSC_Settings::instance()->get( "log_max_size", 5 ) * 1024 * 1024;
        if ( filesize( $file ) > $max_size ) {
            $rotated = $file . ".1";
            if ( file_exists( $rotated ) ) {
                unlink( $rotated );
            }
            rename( $file, $rotated );
        }
    }

    public static function cleanup_old() {
        $log_dir = self::get_log_dir();
        $days = (int) WPSC_Settings::instance()->get( "log_cleanup_days", 30 );
        $cutoff = time() - ( $days * DAY_IN_SECONDS );
        foreach ( glob( $log_dir . "*.log*" ) as $file ) {
            if ( filemtime( $file ) < $cutoff ) {
                unlink( $file );
            }
        }
    }

    public static function get_recent_entries( $count = 50 ) {
        $log_file = self::get_log_dir() . "debug.log";
        if ( ! file_exists( $log_file ) ) {
            return array();
        }
        $lines = file( $log_file );
        if ( ! $lines ) {
            return array();
        }
        $lines = array_reverse( $lines );
        $lines = array_slice( $lines, 0, $count );
        return array_reverse( $lines );
    }

    public static function get_log_size() {
        $log_file = self::get_log_dir() . "debug.log";
        return file_exists( $log_file ) ? filesize( $log_file ) : 0;
    }

    public static function get_log_count() {
        $log_file = self::get_log_dir() . "debug.log";
        if ( ! file_exists( $log_file ) ) {
            return 0;
        }
        $lines = file( $log_file );
        return $lines ? count( $lines ) : 0;
    }
}
