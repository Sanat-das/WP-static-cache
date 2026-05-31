<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Plugin {
    private static $instance = null;
    private $admin_hook_suffix = '';

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        WPSC_Settings::instance();
        require_once WPSC_INC_DIR . 'class-wpsc-js-optimizer.php';
        require_once WPSC_INC_DIR . 'class-wpsc-image-optimizer.php';
        WPSC_JS_Optimizer::instance()->init_hooks();
        WPSC_Image_Optimizer::instance()->init_hooks();
        WPSC_Cron::instance()->setup();
        WPSC_Cleanup::instance()->setup_hooks();
        if ( wpsc_is_public_cache_enabled() ) {
            add_action( 'template_redirect', array( WPSC_Public_Cache::instance(), 'init_buffer' ), 0 );
        }
        if ( wpsc_is_private_cache_enabled() ) {
            add_action( 'wp_loaded', array( WPSC_Private_Cache::instance(), 'init' ), 0 );
            add_action( 'updated_user_meta', array( $this, 'on_capability_change' ), 10, 4 );
        }
        add_action( 'wp_logout', array( 'WPSC_Private_Cache', 'clear_role_cookie' ) );
        add_action( 'clear_auth_cookie', array( 'WPSC_Private_Cache', 'clear_role_cookie' ) );
        if ( WPSC_Settings::instance()->get( 'logging_enabled', false ) ) {
            add_action( 'shutdown', array( 'WPSC_Logger', 'cleanup_old' ) );
        }
        if ( isset( $_GET['wpsc_download_log'] ) && current_user_can( 'manage_options' ) ) {
            $log_file = WPSC_Logger::get_log_dir() . 'debug.log';
            if ( file_exists( $log_file ) ) {
                header( 'Content-Type: text/plain' );
                header( 'Content-Disposition: attachment; filename="wpsc-debug.log"' );
                readfile( $log_file );
                exit;
            }
        }
        if ( current_user_can( 'manage_options' ) && is_admin_bar_showing() ) {
            add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );
            add_action( 'wp_enqueue_scripts', array( $this, 'admin_bar_assets' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_bar_assets' ) );
        }
    }

    public function init_admin() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'wp_ajax_wpsc_action', array( $this, 'handle_ajax_actions' ) );
        add_action( 'update_option_' . WPSC_SETTINGS_KEY, array( 'WPSC_Dropin', 'write' ) );
        add_filter( 'plugin_row_meta', array( $this, 'modify_plugin_row_meta' ), 10, 4 );
        add_action( 'admin_notices', array( $this, 'maybe_show_wp_cache_notice' ) );
    }

    public function maybe_show_wp_cache_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! WPSC_Dropin::is_installed() ) {
            return;
        }
        if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
            return;
        }
        $setting_link = add_query_arg( 'page', 'wp-static-cache', admin_url( 'options-general.php' ) );
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>WP Static Cache:</strong>
                <?php esc_html_e( 'The drop-in file is installed but the', 'wp-static-cache' ); ?>
                <code>WP_CACHE</code>
                <?php esc_html_e( 'constant is not set to', 'wp-static-cache' ); ?>
                <code>true</code>.
                <?php esc_html_e( 'The server cannot serve cached pages until this is fixed.', 'wp-static-cache' ); ?>
            </p>
            <p>
                <?php esc_html_e( 'Please add the following line to your', 'wp-static-cache' ); ?>
                <code>wp-config.php</code>
                <?php esc_html_e( 'before the', 'wp-static-cache' ); ?>
                <code>/* That&rsquo;s all, stop editing! */</code>
                <?php esc_html_e( 'comment:', 'wp-static-cache' ); ?>
            </p>
            <p><code style="display:inline-block;background:#f0f0f1;padding:8px 12px;border:1px solid #c3c4c7;border-radius:4px;">define( 'WP_CACHE', true );</code></p>
            <p>
                <a href="<?php echo esc_url( $setting_link ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Re-deploy Drop-in', 'wp-static-cache' ); ?>
                </a>
                <span style="margin-left:10px;color:#666;font-size:12px;">
                    <?php esc_html_e( 'After adding the line, visit the settings page and save to re-deploy the drop-in file.', 'wp-static-cache' ); ?>
                </span>
            </p>
        </div>
        <?php
    }

    public function add_admin_menu() {
        $this->admin_hook_suffix = add_options_page(
            __( 'WP Static Cache', 'wp-static-cache' ),
            __( 'WP Static Cache', 'wp-static-cache' ),
            'manage_options',
            'wp-static-cache',
            array( $this, 'render_dashboard' )
        );
        add_submenu_page(
            null,
            __( 'About WP Static Cache', 'wp-static-cache' ),
            __( 'About WP Static Cache', 'wp-static-cache' ),
            'manage_options',
            'wpsc-about',
            array( $this, 'render_about_page' )
        );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    public function register_settings() {
        register_setting( 'wpsc_settings', WPSC_SETTINGS_KEY, array(
            'sanitize_callback' => array( WPSC_Settings::instance(), 'sanitize' ),
        ) );
        add_settings_section( 'wpsc_main', '', '__return_empty_string', 'wp-static-cache' );
    }

    public function render_dashboard() {
        $tabs = WPSC_Settings::instance()->get_tabs();
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
        include WPSC_ADMIN_DIR . 'views/dashboard.php';
    }

    public function enqueue_admin_assets( $hook ) {
        if ( $hook !== $this->admin_hook_suffix ) {
            return;
        }
        wp_enqueue_style( 'wpsc-admin', plugin_dir_url( WPSC_FILE ) . 'admin/css/wpsc-admin.css', array(), WPSC_VERSION );
        wp_enqueue_script( 'wpsc-admin', plugin_dir_url( WPSC_FILE ) . 'admin/js/wpsc-admin.js', array( 'jquery' ), WPSC_VERSION, true );
        wp_localize_script( 'wpsc-admin', 'wpscAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'wpsc_ajax' ),
        ) );
    }

    public function admin_bar_assets() {
        wp_add_inline_style( 'admin-bar',
            '#wp-admin-bar-wpsc-cache .ab-icon:before{content:"\f108"!important;top:2px}' .
            '#wp-admin-bar-wpsc-cache .wpsc-flush-link{cursor:pointer;display:inline-block}' .
            '#wp-admin-bar-wpsc-cache .wpsc-flush-link:hover{color:#72aee6}'
        );
        $ajax_url = admin_url( 'admin-ajax.php' );
        $nonce    = wp_create_nonce( 'wpsc_ajax' );
        wp_add_inline_script( 'admin-bar', '
(function($){
    $(function(){
        $(document).on("click","#wp-admin-bar-wpsc-cache .wpsc-flush-action > a",function(e){
            e.preventDefault();
            var item=$(this).closest("li");
            var action=item.attr("id").replace("wp-admin-bar-wpsc-cache-","");
            var original=$(this).text();
            $(this).text("Flushing...");
            $.post("' . esc_js( $ajax_url ) . '",{action:"wpsc_action",wpsc_action:action,_ajax_nonce:"' . esc_js( $nonce ) . '"},function(r){
                item.find("> a").text(r.success?"Done ✓":"Error ✗");
                setTimeout(function(){item.find("> a").text(original)},2000);
            }).fail(function(){item.find("> a").text("Error ✗");setTimeout(function(){item.find("> a").text(original)},3000)});
        });
        $(document).on("click","#wp-admin-bar-wpsc-cache .wpsc-flush-current-url > a",function(e){
            e.preventDefault();
            var item=$(this).closest("li");
            var original=$(this).text();
            $(this).text("Flushing...");
            $.post("' . esc_js( $ajax_url ) . '",{action:"wpsc_action",wpsc_action:"flush_current_url",current_url:window.location.href,_ajax_nonce:"' . esc_js( $nonce ) . '"},function(r){
                item.find("> a").text(r.success?"Done ✓":"Error ✗");
                setTimeout(function(){item.find("> a").text(original)},2000);
            }).fail(function(){item.find("> a").text("Error ✗");setTimeout(function(){item.find("> a").text(original)},3000)});
        });
    });
})(jQuery);' );
    }

    public function add_admin_bar_menu( $wp_admin_bar ) {
        $wp_admin_bar->add_node( array(
            'id'    => 'wpsc-cache',
            'title' => '<span class="ab-icon"></span> Cache',
            'href'  => admin_url( 'options-general.php?page=wp-static-cache' ),
            'meta'  => array( 'title' => 'WP Static Cache' ),
        ) );

        $actions = array(
            'flush_public'  => 'Flush Public Cache',
            'flush_private' => 'Flush Private Cache',
            'flush_all'     => 'Flush All Cache',
            'flush_expired' => 'Flush Expired Cache',
        );

        foreach ( $actions as $action => $label ) {
            $wp_admin_bar->add_node( array(
                'parent' => 'wpsc-cache',
                'id'     => 'wpsc-cache-' . $action,
                'title'  => $label,
                'href'   => '#',
                'meta'   => array(
                    'class' => 'wpsc-flush-action',
                    'onclick' => 'return false;',
                ),
            ) );
        }

        $wp_admin_bar->add_node( array(
            'parent' => 'wpsc-cache',
            'id'     => 'wpsc-cache-flush_current_url',
            'title'  => 'Flush Current URL',
            'href'   => '#',
            'meta'   => array(
                'class' => 'wpsc-flush-current-url',
            ),
        ) );

        $wp_admin_bar->add_node( array(
            'parent' => 'wpsc-cache',
            'id'     => 'wpsc-cache-settings',
            'title'  => 'Settings',
            'href'   => admin_url( 'options-general.php?page=wp-static-cache' ),
            'meta'   => array( 'title' => 'WP Static Cache Settings' ),
        ) );
    }

    public function handle_ajax_actions() {
        check_ajax_referer( 'wpsc_ajax' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( -1 );
        }
        $action = isset( $_POST['wpsc_action'] ) ? sanitize_key( $_POST['wpsc_action'] ) : '';
        switch ( $action ) {
            case 'flush_public':
                WPSC_Public_Cache::flush_all();
                wp_send_json_success( __( 'Public cache flushed.', 'wp-static-cache' ) );
                break;
            case 'flush_private':
                WPSC_Private_Cache::instance()->flush_all();
                wp_send_json_success( __( 'Private cache flushed.', 'wp-static-cache' ) );
                break;
            case 'flush_all':
                WPSC_Public_Cache::flush_all();
                WPSC_Private_Cache::instance()->flush_all();
                wp_send_json_success( __( 'All cache flushed.', 'wp-static-cache' ) );
                break;
            case 'flush_expired':
                WPSC_Public_Cache::flush_expired();
                WPSC_Private_Cache::instance()->flush_expired();
                wp_send_json_success( __( 'Expired cache flushed.', 'wp-static-cache' ) );
                break;
            case 'flush_current_url':
                $url = isset( $_POST['current_url'] ) ? esc_url_raw( $_POST['current_url'] ) : '';
                if ( $url ) {
                    WPSC_Public_Cache::flush_url( $url, true );
                    WPSC_Private_Cache::instance()->flush_url( $url );
                    WPSC_Preload::instance()->queue_urls( array( $url ) );
                    wp_send_json_success( __( 'URL flushed.', 'wp-static-cache' ) );
                }
                wp_send_json_error( __( 'No URL provided.', 'wp-static-cache' ) );
                break;
            case 'preload_now':
                WPSC_Preload::instance()->start();
                wp_send_json_success( __( 'Preload started.', 'wp-static-cache' ) );
                break;
            case 'stop_preload':
                WPSC_Preload::instance()->stop();
                wp_send_json_success( __( 'Preload stopped.', 'wp-static-cache' ) );
                break;
            case 'get_stats':
                $entries = WPSC_Logger::get_recent_entries( 8 );
                $recent = empty( $entries ) ? __( 'No log entries yet. Enable logging in the Logging tab.', 'wp-static-cache' ) : implode( '', $entries );
                $stats = array(
                    'cache'           => WPSC_Statistics::get_cache_stats(),
                    'preload'         => WPSC_Statistics::get_preload_status(),
                    'system'          => WPSC_Statistics::get_system_info(),
                    'settings'        => WPSC_Statistics::get_settings_status(),
                    'recent_activity' => esc_html( $recent ),
                );
                wp_send_json_success( $stats );
                break;
            case 'refresh_log':
                ob_start();
                $entries = WPSC_Logger::get_recent_entries( 100 );
                echo esc_html( implode( '', $entries ) );
                wp_send_json_success( ob_get_clean() );
                break;
            case 'clear_log':
                $log_dir = WPSC_Logger::get_log_dir();
                if ( is_dir( $log_dir ) ) {
                    $log_file = $log_dir . 'debug.log';
                    if ( file_exists( $log_file ) ) { unlink( $log_file ); }
                    foreach ( glob( $log_dir . '*.log*' ) as $f ) { unlink( $f ); }
                }
                wp_send_json_success( __( 'Log cleared.', 'wp-static-cache' ) );
                break;
            case 'download_log':
                $log_file = WPSC_Logger::get_log_dir() . 'debug.log';
                if ( file_exists( $log_file ) ) {
                    wp_send_json_success( array( 'url' => add_query_arg( 'wpsc_download_log', '1', admin_url() ) ) );
                }
                wp_send_json_error( 'No log file.' );
                break;
            case 'test_log':
                WPSC_Logger::info( 'Test log entry from admin', array( 'action' => 'manual_test', 'user' => get_current_user_id() ) );
                wp_send_json_success( __( 'Test entry written.', 'wp-static-cache' ) . ' ' . WPSC_Logger::get_log_dir() . 'debug.log' );
                break;

            case 'js_analyze_url':
                $target_url = isset( $_POST['target_url'] ) ? esc_url_raw( $_POST['target_url'] ) : '';
                if ( ! $target_url || ! wp_http_validate_url( $target_url ) ) {
                    wp_send_json_error( __( 'Invalid URL.', 'wp-static-cache' ) );
                }
                WPSC_Settings::instance()->update( array( 'js_test_url' => $target_url ) );
                $analyze_url = add_query_arg( 'wpsc_analysis', '1', $target_url );
                $response = wp_remote_get( $analyze_url, array( 'timeout' => 30 ) );
                if ( is_wp_error( $response ) ) {
                    wp_send_json_error( $response->get_error_message() );
                }
                $html = wp_remote_retrieve_body( $response );
                require_once WPSC_INC_DIR . 'class-wpsc-js-optimizer.php';
                $scripts = WPSC_JS_Optimizer::instance()->analyze_html( $html );
                wp_send_json_success( $scripts );
                break;

            case 'js_toggle_script_action':
                $script_key = isset( $_POST['script_key'] ) ? sanitize_text_field( $_POST['script_key'] ) : '';
                $toggle_action = isset( $_POST['toggle_action'] ) ? sanitize_key( $_POST['toggle_action'] ) : '';
                $state = isset( $_POST['state'] ) ? (int) $_POST['state'] : 0;
                if ( ! $script_key || ! in_array( $toggle_action, array( 'block', 'defer', 'delay' ), true ) || ! $this->is_valid_script_key( $script_key ) ) {
                    wp_send_json_error( __( 'Invalid parameters.', 'wp-static-cache' ) );
                }
                $settings_map = array(
                    'block' => 'js_remove_list',
                    'defer' => 'js_defer_include',
                    'delay' => 'js_delay_include',
                );
                if ( $state ) {
                    foreach ( $settings_map as $act => $key ) {
                        $cur = WPSC_Settings::instance()->get( $key, '' );
                        $items = array_filter( array_map( 'trim', explode( "\n", $cur ) ) );
                        if ( $act === $toggle_action ) {
                            $items[] = $script_key;
                            $items = array_unique( $items );
                        } else {
                            $items = array_diff( $items, array( $script_key ) );
                        }
                        WPSC_Settings::instance()->update( array( $key => implode( "\n", $items ) ) );
                    }
                } else {
                    $setting_key = $settings_map[ $toggle_action ];
                    $cur = WPSC_Settings::instance()->get( $setting_key, '' );
                    $items = array_filter( array_map( 'trim', explode( "\n", $cur ) ) );
                    $items = array_diff( $items, array( $script_key ) );
                    WPSC_Settings::instance()->update( array( $setting_key => implode( "\n", $items ) ) );
                }
                wp_send_json_success();
                break;

            case 'js_bulk_toggle':
                $raw_scripts = isset( $_POST['scripts'] ) ? $_POST['scripts'] : '';
                $bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : '';
                $scripts = json_decode( wp_unslash( $raw_scripts ), true );
                if ( ! is_array( $scripts ) || ! in_array( $bulk_action, array( 'block', 'defer', 'delay', 'clear' ), true ) ) {
                    wp_send_json_error( __( 'Invalid parameters.', 'wp-static-cache' ) );
                }
                foreach ( $scripts as $s ) {
                    if ( ! $this->is_valid_script_key( $s ) ) {
                        wp_send_json_error( __( 'Invalid script key in selection.', 'wp-static-cache' ) );
                    }
                }
                $settings_map = array(
                    'block' => 'js_remove_list',
                    'defer' => 'js_defer_include',
                    'delay' => 'js_delay_include',
                );
                if ( $bulk_action === 'clear' ) {
                    foreach ( $settings_map as $act => $key ) {
                        $cur = WPSC_Settings::instance()->get( $key, '' );
                        $items = array_filter( array_map( 'trim', explode( "\n", $cur ) ) );
                        $items = array_diff( $items, $scripts );
                        WPSC_Settings::instance()->update( array( $key => implode( "\n", $items ) ) );
                    }
                } else {
                    $setting_key = $settings_map[ $bulk_action ];
                    $cur = WPSC_Settings::instance()->get( $setting_key, '' );
                    $items = array_filter( array_map( 'trim', explode( "\n", $cur ) ) );
                    foreach ( $scripts as $s ) {
                        $items[] = $s;
                    }
                    $items = array_unique( $items );
                    WPSC_Settings::instance()->update( array( $setting_key => implode( "\n", $items ) ) );
                    unset( $settings_map[ $bulk_action ] );
                    foreach ( $settings_map as $act => $key ) {
                        $cur = WPSC_Settings::instance()->get( $key, '' );
                        $it = array_filter( array_map( 'trim', explode( "\n", $cur ) ) );
                        $it = array_diff( $it, $scripts );
                        WPSC_Settings::instance()->update( array( $key => implode( "\n", $it ) ) );
                    }
                }
                wp_send_json_success( array( 'message' => __( 'Bulk action applied.', 'wp-static-cache' ) ) );
                break;

            case 'js_reset_all':
                WPSC_Settings::instance()->update( array(
                    'js_remove_list' => '',
                    'js_defer_include' => '',
                    'js_delay_include' => '',
                ) );
                wp_send_json_success( array(
                    'message' => __( 'All JS optimization settings cleared.', 'wp-static-cache' ),
                ) );
                break;

            case 'js_save_setting':
                $setting_key = isset( $_POST['setting_key'] ) ? sanitize_key( $_POST['setting_key'] ) : '';
                $setting_value = isset( $_POST['setting_value'] ) ? sanitize_text_field( $_POST['setting_value'] ) : '';
                if ( $setting_key === 'js_delay_timeout' ) {
                    $setting_value = max( 1, min( 120, (int) $setting_value ) );
                }
                if ( $setting_key ) {
                    WPSC_Settings::instance()->update( array( $setting_key => $setting_value ) );
                    wp_send_json_success( array( 'message' => __( 'Setting saved.', 'wp-static-cache' ) ) );
                }
                wp_send_json_error( __( 'Invalid setting key.', 'wp-static-cache' ) );
                break;

            case 'js_apply_cache':
                WPSC_Public_Cache::flush_all();
                wp_send_json_success( array(
                    'message' => __( 'JS optimization applied. Public cache flushed. New visitors will see the optimized version.', 'wp-static-cache' ),
                ) );
                break;

            case 'reset_defaults':
                WPSC_Settings::instance()->update( WPSC_Settings::instance()->get_defaults() );
                wp_send_json_success( __( 'All settings have been reset to defaults.', 'wp-static-cache' ) );
                break;

            case 'img_opt_get_stats':
                try {
                    require_once WPSC_INC_DIR . 'class-wpsc-image-optimizer.php';
                    $stats = WPSC_Image_Optimizer::instance()->get_stats();
                    wp_send_json_success( $stats );
                } catch ( Throwable $e ) {
                    wp_send_json_error( $e->getMessage() );
                }
                break;

            case 'img_opt_scan':
                try {
                    require_once WPSC_INC_DIR . 'class-wpsc-image-optimizer.php';
                    $result = WPSC_Image_Optimizer::instance()->scan_media();
                    wp_send_json_success( $result );
                } catch ( Throwable $e ) {
                    wp_send_json_error( $e->getMessage() );
                }
                break;

            case 'img_opt_convert_batch':
                try {
                    require_once WPSC_INC_DIR . 'class-wpsc-image-optimizer.php';
                    $ids = isset( $_POST['ids'] ) ? json_decode( wp_unslash( $_POST['ids'] ), true ) : array();
                    if ( empty( $ids ) || ! is_array( $ids ) ) {
                        wp_send_json_error( __( 'No image IDs provided.', 'wp-static-cache' ) );
                    }
                    $result = WPSC_Image_Optimizer::instance()->convert_batch( $ids );
                    wp_send_json_success( $result );
                } catch ( Throwable $e ) {
                    wp_send_json_error( $e->getMessage() );
                }
                break;

            case 'img_opt_get_deletable':
                try {
                    require_once WPSC_INC_DIR . 'class-wpsc-image-optimizer.php';
                    $result = WPSC_Image_Optimizer::instance()->get_deletable_ids();
                    wp_send_json_success( $result );
                } catch ( Throwable $e ) {
                    wp_send_json_error( $e->getMessage() );
                }
                break;

            case 'img_opt_delete_batch':
                try {
                    require_once WPSC_INC_DIR . 'class-wpsc-image-optimizer.php';
                    $ids = isset( $_POST['ids'] ) ? json_decode( wp_unslash( $_POST['ids'] ), true ) : array();
                    if ( empty( $ids ) || ! is_array( $ids ) ) {
                        wp_send_json_error( __( 'No image IDs provided.', 'wp-static-cache' ) );
                    }
                    $result = WPSC_Image_Optimizer::instance()->delete_converted_batch( $ids );
                    wp_send_json_success( $result );
                } catch ( Throwable $e ) {
                    wp_send_json_error( $e->getMessage() );
                }
                break;

            case 'img_opt_get_thumb_sizes':
                try {
                    require_once WPSC_INC_DIR . 'class-wpsc-image-optimizer.php';
                    $sizes = WPSC_Image_Optimizer::instance()->get_thumb_sizes();
                    wp_send_json_success( $sizes );
                } catch ( Throwable $e ) {
                    wp_send_json_error( $e->getMessage() );
                }
                break;

            case 'img_opt_save_thumb_size':
                try {
                    require_once WPSC_INC_DIR . 'class-wpsc-image-optimizer.php';
                    $name = isset( $_POST['name'] ) ? sanitize_key( $_POST['name'] ) : '';
                    $width = isset( $_POST['width'] ) ? max( 0, (int) $_POST['width'] ) : 0;
                    $height = isset( $_POST['height'] ) ? max( 0, (int) $_POST['height'] ) : 0;
                    $crop = ! empty( $_POST['crop'] );
                    if ( empty( $name ) || ( $width <= 0 && $height <= 0 ) ) {
                        wp_send_json_error( __( 'Invalid size parameters.', 'wp-static-cache' ) );
                    }
                    $sizes = WPSC_Image_Optimizer::instance()->get_thumb_sizes();
                    $sizes[ $name ] = array( 'width' => $width, 'height' => $height, 'crop' => $crop );
                    WPSC_Settings::instance()->update( array( 'img_opt_thumb_sizes' => $sizes ) );
                    $built_in = array( 'thumbnail', 'medium', 'medium_large', 'large' );
                    if ( in_array( $name, $built_in, true ) ) {
                        $w = max( 1, $width );
                        $h = max( 1, $height );
                        update_option( "{$name}_size_w", $w );
                        update_option( "{$name}_size_h", $h );
                        if ( $name === 'thumbnail' ) {
                            update_option( 'thumbnail_crop', $crop ? 1 : 0 );
                        }
                    }
                    wp_send_json_success( array( 'message' => __( 'Thumbnail size saved.', 'wp-static-cache' ) ) );
                } catch ( Throwable $e ) {
                    wp_send_json_error( $e->getMessage() );
                }
                break;

            case 'img_opt_add_thumb_size':
                try {
                    require_once WPSC_INC_DIR . 'class-wpsc-image-optimizer.php';
                    $name = isset( $_POST['name'] ) ? sanitize_key( $_POST['name'] ) : '';
                    $width = isset( $_POST['width'] ) ? max( 0, (int) $_POST['width'] ) : 0;
                    $height = isset( $_POST['height'] ) ? max( 0, (int) $_POST['height'] ) : 0;
                    $crop = ! empty( $_POST['crop'] );
                    if ( empty( $name ) || strlen( $name ) < 2 || strlen( $name ) > 64 ) {
                        wp_send_json_error( __( 'Size name must be 2-64 characters.', 'wp-static-cache' ) );
                    }
                    if ( ! preg_match( '/^[a-z0-9_\-]+$/', $name ) ) {
                        wp_send_json_error( __( 'Size name may only contain lowercase letters, numbers, hyphens, and underscores.', 'wp-static-cache' ) );
                    }
                    if ( $width <= 0 && $height <= 0 ) {
                        wp_send_json_error( __( 'Width or height must be greater than 0.', 'wp-static-cache' ) );
                    }
                    $sizes = WPSC_Image_Optimizer::instance()->get_thumb_sizes();
                    if ( isset( $sizes[ $name ] ) ) {
                        wp_send_json_error( __( 'A size with this name already exists.', 'wp-static-cache' ) );
                    }
                    $sizes[ $name ] = array( 'width' => $width, 'height' => $height, 'crop' => $crop );
                    WPSC_Settings::instance()->update( array( 'img_opt_thumb_sizes' => $sizes ) );
                    wp_send_json_success( array(
                        'message' => sprintf( __( 'Size "%s" added.', 'wp-static-cache' ), esc_html( $name ) ),
                        'name' => $name,
                    ) );
                } catch ( Throwable $e ) {
                    wp_send_json_error( $e->getMessage() );
                }
                break;

            case 'img_opt_delete_thumb_size':
                try {
                    require_once WPSC_INC_DIR . 'class-wpsc-image-optimizer.php';
                    $name = isset( $_POST['name'] ) ? sanitize_key( $_POST['name'] ) : '';
                    $built_in = array( 'thumbnail', 'medium', 'medium_large', 'large' );
                    if ( in_array( $name, $built_in, true ) ) {
                        wp_send_json_error( __( 'Built-in sizes cannot be deleted.', 'wp-static-cache' ) );
                    }
                    if ( empty( $name ) ) {
                        wp_send_json_error( __( 'No size name provided.', 'wp-static-cache' ) );
                    }
                    $sizes = WPSC_Image_Optimizer::instance()->get_thumb_sizes();
                    if ( ! isset( $sizes[ $name ] ) ) {
                        wp_send_json_error( __( 'Size not found.', 'wp-static-cache' ) );
                    }
                    unset( $sizes[ $name ] );
                    WPSC_Settings::instance()->update( array( 'img_opt_thumb_sizes' => $sizes ) );
                    wp_send_json_success( array(
                        'message' => sprintf( __( 'Size "%s" deleted.', 'wp-static-cache' ), esc_html( $name ) ),
                    ) );
                } catch ( Throwable $e ) {
                    wp_send_json_error( $e->getMessage() );
                }
                break;

            case 'get_nginx_config':
                wp_send_json_success( WPSC_Server::get_nginx_config() );
                break;

            default:
                wp_send_json_error( __( 'Unknown action.', 'wp-static-cache' ) );
        }
    }

    public function on_capability_change( $meta_id, $object_id, $meta_key, $_meta_value ) {
        if ( $meta_key === 'wp_capabilities' ) {
            WPSC_Private_Cache::instance()->flush_all();
        }
    }

    public function modify_plugin_row_meta( $links, $plugin_file, $plugin_data, $status ) {
        if ( plugin_basename( WPSC_FILE ) !== $plugin_file ) {
            return $links;
        }
        foreach ( $links as $key => $link ) {
            if ( false !== strpos( $link, 'plugin-information' ) ) {
                $links[ $key ] = '<a href="' . admin_url( 'admin.php?page=wpsc-about' ) . '" class="">' . __( 'View details', 'wp-static-cache' ) . '</a>';
            }
        }
        return $links;
    }

    public function render_about_page() {
        include WPSC_ADMIN_DIR . 'views/about.php';
    }

    private function is_valid_script_key( $key ) {
        if ( empty( $key ) || strlen( $key ) > 1024 ) {
            return false;
        }
        return (bool) preg_match( '/^(https?:\/\/|\/\/|\/)/i', $key ) || (bool) preg_match( '/^[a-f0-9]{32}$/i', $key );
    }
}
