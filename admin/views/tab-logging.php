<form method="post" action="options.php">
    <?php settings_fields( 'wpsc_settings' ); ?>
    <table class="form-table">
        <?php
        $fields = WPSC_Settings::instance()->get_fields( 'logging' );
        WPSC_Settings::instance()->render_fields( $fields );
        ?>
    </table>
    <?php submit_button(); ?>
</form>
<hr />
<h3><?php esc_html_e( 'Recent Log Entries', 'wp-static-cache' ); ?></h3>
<div id="wpsc-log-viewer" class="wpsc-info-panel" style="max-width:800px;">
    <pre style="max-height:400px;overflow:auto;font-size:11px;line-height:1.4;background:#222;color:#0f0;padding:12px;border-radius:4px;"><?php
        $entries = WPSC_Logger::get_recent_entries( 100 );
        if ( empty( $entries ) ) {
            echo esc_html( __( 'No log entries yet.', 'wp-static-cache' ) );
        } else {
            echo esc_html( implode( '', $entries ) );
        }
    ?></pre>
    <p>
        <button type="button" class="button wpsc-action-btn" data-action="refresh_log">
            <?php esc_html_e( 'Refresh', 'wp-static-cache' ); ?>
        </button>
        <button type="button" class="button wpsc-action-btn" data-action="download_log">
            <?php esc_html_e( 'Download Log', 'wp-static-cache' ); ?>
        </button>
        <button type="button" class="button wpsc-action-btn" data-action="clear_log">
            <?php esc_html_e( 'Clear Log', 'wp-static-cache' ); ?>
        </button>
        <button type="button" class="button wpsc-action-btn" data-action="test_log">
            <?php esc_html_e( 'Write Test Entry', 'wp-static-cache' ); ?>
        </button>
        <span class="description">
            <?php
            printf(
                __( 'Log size: %s | Entries: %d', 'wp-static-cache' ),
                size_format( WPSC_Logger::get_log_size() ),
                WPSC_Logger::get_log_count()
            );
            ?>
        </span>
    </p>
</div>
