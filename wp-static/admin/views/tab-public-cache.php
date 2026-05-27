<?php
$current_method = WPSC_Settings::instance()->get( 'serve_method', 'php' );
?>
<form method="post" action="options.php">
	<?php settings_fields( 'wpsc_settings' ); ?>
	<table class="form-table">
		<?php
		$fields = WPSC_Settings::instance()->get_fields( 'public-cache' );
		WPSC_Settings::instance()->render_fields( $fields );
		?>
	</table>
	<?php submit_button(); ?>
</form>

<div id="wpsc-nginx-config" class="wpsc-server-config" data-method="<?php echo esc_attr( $current_method ); ?>" style="<?php echo 'nginx' === $current_method ? '' : 'display:none;'; ?>">
	<h3><?php esc_html_e( 'Nginx Configuration', 'wp-static-cache' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Copy the following configuration into your nginx server block (not inside a location block).', 'wp-static-cache' ); ?></p>
	<pre id="wpsc-nginx-config-content" class="wpsc-config-code"><?php echo esc_html( WPSC_Server::get_nginx_config() ); ?></pre>
	<button type="button" class="button wpsc-copy-config" data-target="wpsc-nginx-config-content">
		<?php esc_html_e( 'Copy to Clipboard', 'wp-static-cache' ); ?>
	</button>
</div>

<div id="wpsc-htaccess-notice" class="notice notice-info inline" style="<?php echo 'htaccess' === $current_method ? '' : 'display:none;'; ?>">
	<p><?php esc_html_e( '.htaccess mode is active. The rewrite rules have been written to your .htaccess file. Cached pages will be served directly by Apache without loading PHP.', 'wp-static-cache' ); ?></p>
</div>
