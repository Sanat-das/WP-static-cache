<form method="post" action="options.php">
    <?php settings_fields( 'wpsc_settings' ); ?>
    <table class="form-table">
        <?php
        $fields = WPSC_Settings::instance()->get_fields( 'auto-flush' );
        WPSC_Settings::instance()->render_fields( $fields );
        ?>
    </table>
    <?php submit_button(); ?>
</form>
