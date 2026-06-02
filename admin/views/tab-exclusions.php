<form method="post" action="options.php">
    <?php settings_fields( 'wpsc_settings' ); ?>
    <?php
    $fields = WPSC_Settings::instance()->get_fields( 'exclusions' );
    WPSC_Settings::instance()->render_fields( $fields );
    ?>
    <?php submit_button(); ?>
</form>
