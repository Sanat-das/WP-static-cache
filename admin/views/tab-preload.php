<p id="wpsc-preload-buttons" style="display:none;">
    <button type="button" class="button button-primary wpsc-action-btn" data-action="preload_now">
        <?php esc_html_e( 'Preload Now', 'wp-static-cache' ); ?>
    </button>
    <button type="button" class="button wpsc-action-btn" data-action="stop_preload">
        <?php esc_html_e( 'Stop Preload', 'wp-static-cache' ); ?>
    </button>
</p>
<form method="post" action="options.php">
    <?php settings_fields( 'wpsc_settings' ); ?>
    <?php
    $fields = WPSC_Settings::instance()->get_fields( 'preload' );
    WPSC_Settings::instance()->render_fields( $fields );
    ?>
    <?php submit_button(); ?>
</form>

<script>
jQuery(function($) {
    $('#wpsc-preload-buttons').insertAfter($('.wpsc-section-group').has('.wpsc-section-title:contains("Manual Preload Actions")').find('.wpsc-section-title')).show();
});
</script>
