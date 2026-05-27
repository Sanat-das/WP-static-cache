<?php
$enabled = wpsc_is_private_cache_enabled();
$oc_available = wp_using_ext_object_cache();
$oc_label = $oc_available ? __( 'Active', 'wp-static-cache' ) : __( 'Not detected', 'wp-static-cache' );
?>
<div class="wpsc-status-panel" style="margin-bottom: 20px;">
    <h3 style="margin-top: 0;"><?php esc_html_e( 'Private Cache', 'wp-static-cache' ); ?></h3>
    <table>
        <tr>
            <td><?php esc_html_e( 'Status', 'wp-static-cache' ); ?></td>
            <td>
                <?php if ( $enabled ) : ?>
                    <span class="wpsc-dash-status-badge active"><?php esc_html_e( 'Enabled', 'wp-static-cache' ); ?></span>
                <?php else : ?>
                    <span class="wpsc-dash-status-badge inactive"><?php esc_html_e( 'Disabled', 'wp-static-cache' ); ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td><?php esc_html_e( 'Object Cache (wp_cache_*)', 'wp-static-cache' ); ?></td>
            <td>
                <?php if ( $oc_available ) : ?>
                    <span style="color:#00a32a;font-weight:600;"><?php echo esc_html( $oc_label ); ?></span>
                    <p class="description" style="margin:2px 0 0;"><?php esc_html_e( 'Private pages will be served from memory for even faster performance.', 'wp-static-cache' ); ?></p>
                <?php else : ?>
                    <span style="color:#999;"><?php echo esc_html( $oc_label ); ?></span>
                    <p class="description" style="margin:2px 0 0;"><?php esc_html_e( 'Install a caching plugin like Redis Object Cache for faster in-memory serving.', 'wp-static-cache' ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</div>

<p><?php esc_html_e( 'The private cache stores full HTML pages for logged-in users, keyed by their role. When a user with the same role visits the same page, the cached version is served instead of generating it from scratch. Content changes automatically invalidate the cache.', 'wp-static-cache' ); ?></p>

<form method="post" action="options.php">
    <?php settings_fields( 'wpsc_settings' ); ?>
    <?php
    $fields = WPSC_Settings::instance()->get_fields( 'private-cache' );
    WPSC_Settings::instance()->render_fields( $fields );
    ?>
    <?php submit_button(); ?>
</form>
