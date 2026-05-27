<div class="wrap wpsc-dashboard">
    <h1><?php esc_html_e( 'WP Static Cache', 'wp-static-cache' ); ?></h1>
    <?php settings_errors(); ?>
    <?php
    $tabs = WPSC_Settings::instance()->get_tabs();
    $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
    ?>
    <h2 class="nav-tab-wrapper">
        <?php foreach ( $tabs as $slug => $title ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'tab', $slug ) ); ?>" class="nav-tab <?php echo $current_tab === $slug ? 'nav-tab-active' : ''; ?>" data-tab="<?php echo esc_attr( $slug ); ?>">
                <?php echo esc_html( $title ); ?>
            </a>
        <?php endforeach; ?>
    </h2>
    <div class="wpsc-tab-content">
        <?php
        $tab_file = WPSC_ADMIN_DIR . 'views/tab-' . $current_tab . '.php';
        if ( file_exists( $tab_file ) ) {
            include $tab_file;
        } else {
            echo '<p>' . esc_html__( 'Tab not found.', 'wp-static-cache' ) . '</p>';
        }
        ?>
    </div>
</div>
