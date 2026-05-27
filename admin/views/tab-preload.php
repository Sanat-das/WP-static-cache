<h3><?php esc_html_e( 'Manual Preload Actions', 'wp-static-cache' ); ?></h3>
<p>
    <button type="button" class="button button-primary wpsc-action-btn" data-action="preload_now">
        <?php esc_html_e( 'Preload Now', 'wp-static-cache' ); ?>
    </button>
    <button type="button" class="button wpsc-action-btn" data-action="stop_preload">
        <?php esc_html_e( 'Stop Preload', 'wp-static-cache' ); ?>
    </button>
</p>
<hr />
<form method="post" action="options.php">
    <?php settings_fields( 'wpsc_settings' ); ?>
    <table class="form-table">
        <?php
        $fields = WPSC_Settings::instance()->get_fields( 'preload' );
        WPSC_Settings::instance()->render_fields( $fields );
        ?>
    </table>
    <?php submit_button(); ?>
</form>
