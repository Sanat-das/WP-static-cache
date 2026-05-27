<form method="post" action="options.php">
    <?php settings_fields( 'wpsc_settings' ); ?>
    <table class="form-table">
        <?php
        $fields = WPSC_Settings::instance()->get_fields( 'tools' );
        WPSC_Settings::instance()->render_fields( $fields );
        ?>
    </table>
</form>
