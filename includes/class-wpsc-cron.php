<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Cron {
    private static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setup() {
        add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
        add_action( 'wpsc_preload_batch', array( $this, 'run_preload_batch' ) );
        add_action( 'wpsc_keepalive_ping', array( $this, 'run_keepalive_ping' ) );
        $this->schedule_events();
    }

    public function add_schedules( $schedules ) {
        $settings = WPSC_Settings::instance();
        $value = (int) $settings->get( 'preload_interval_value', 5 );
        $unit = $settings->get( 'preload_interval_unit', 'minutes' );
        $interval = $this->compute_interval( $value, $unit );

        $schedules['wpsc_custom_interval'] = array(
            'interval' => $interval,
            'display'  => __( 'Custom Preload Interval', 'wp-static-cache' ),
        );
        $schedules['wpsc_keepalive'] = array(
            'interval' => 300,
            'display'  => __( 'Every 5 minutes (Keep-Alive)', 'wp-static-cache' ),
        );
        return $schedules;
    }

    public function schedule_events() {
        $settings = WPSC_Settings::instance();

        if ( ! $settings->get( 'preload_enabled', false ) ) {
            $this->unschedule_batch();
        } else {
            $current_schedule = wp_get_schedule( 'wpsc_preload_batch' );
            if ( $current_schedule !== 'wpsc_custom_interval' ) {
                $ts = wp_next_scheduled( 'wpsc_preload_batch' );
                if ( $ts ) {
                    wp_unschedule_event( $ts, 'wpsc_preload_batch' );
                }
                wp_schedule_event( time(), 'wpsc_custom_interval', 'wpsc_preload_batch' );
            }
        }

        if ( wpsc_is_public_cache_enabled() ) {
            $current = wp_get_schedule( 'wpsc_keepalive_ping' );
            if ( $current !== 'wpsc_keepalive' ) {
                $ts = wp_next_scheduled( 'wpsc_keepalive_ping' );
                if ( $ts ) {
                    wp_unschedule_event( $ts, 'wpsc_keepalive_ping' );
                }
                wp_schedule_event( time(), 'wpsc_keepalive', 'wpsc_keepalive_ping' );
            }
        } else {
            $this->unschedule_keepalive();
        }
    }

    public function run_preload_batch() {
        WPSC_Preload::instance()->process_batch();
    }

    public function run_keepalive_ping() {
        $home_url = home_url( '/' );
        wp_remote_get( $home_url, array(
            'timeout'    => 5,
            'blocking'   => false,
            'user-agent' => 'WPStaticCacheKeepAlive/1.0',
        ) );
    }

    public function unschedule_batch() {
        $ts = wp_next_scheduled( 'wpsc_preload_batch' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'wpsc_preload_batch' );
        }
    }

    public function unschedule_keepalive() {
        $ts = wp_next_scheduled( 'wpsc_keepalive_ping' );
        if ( $ts ) {
            wp_unschedule_event( $ts, 'wpsc_keepalive_ping' );
        }
    }

    public function unschedule_all() {
        $this->unschedule_batch();
        $this->unschedule_keepalive();
    }

    private function compute_interval( $value, $unit ) {
        switch ( $unit ) {
            case 'seconds':
                return max( 10, $value );
            case 'minutes':
                return max( 10, $value * 60 );
            case 'hours':
                return max( 10, $value * 3600 );
            case 'days':
                return max( 10, $value * 86400 );
            default:
                return 300;
        }
    }
}
