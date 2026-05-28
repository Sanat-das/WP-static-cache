<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Dropin {
    public static function write( $old_settings = null, $new_settings = null ) {
        $template_path = WPSC_DROPIN_DIR . 'advanced-cache.php';
        if ( ! file_exists( $template_path ) ) {
            return;
        }
        $content = file_get_contents( $template_path );
        $settings = WPSC_Settings::instance();
        $settings->refresh();

        $old_exclude   = $settings->get( 'exclude_urls', '' );
        $cache_dir     = $settings->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT );

        $replacements = array(
            'WPSC_CACHE_DIR_PLACEHOLDER' => $cache_dir,
            'WPSC_CACHEABLE_QS_PLACEHOLDER' => implode( '|', (array) $settings->get( 'cacheable_qs', array() ) ),
            'WPSC_EXCLUDE_PARAMETERS_PLACEHOLDER' => $settings->get( 'exclude_parameters', true ) ? 'true' : 'false',
            'WPSC_EXCLUDE_COOKIES_PLACEHOLDER' => implode( '|', (array) $settings->get( 'exclude_cookies', array() ) ),
            'WPSC_EXCLUDE_URLS_PLACEHOLDER' => implode( "\n", (array) $settings->get( 'exclude_urls', array() ) ),
            'WPSC_EXCLUDE_UA_PLACEHOLDER' => implode( '|', (array) $settings->get( 'exclude_user_agents', array() ) ),
            'WPSC_MAX_FILE_SIZE_PLACEHOLDER' => (int) $settings->get( 'max_cache_file_size', 5120 ),
            'WPSC_LOG_ENABLED_PLACEHOLDER' => $settings->get( 'logging_enabled', false ) ? 'true' : 'false',
            'WPSC_SWR_WINDOW_PLACEHOLDER' => (int) $settings->get( 'swr_window', 300 ),
        );
        foreach ( $replacements as $key => $value ) {
            $content = str_replace( "{{{$key}}}", $value, $content );
        }
		$content = preg_replace( '/\{\{[A-Z_]+\}\}/', '', $content );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}
		file_put_contents( WPSC_DROPIN_PATH, $content );

		if ( function_exists( 'opcache_invalidate' ) ) {
			opcache_invalidate( WPSC_DROPIN_PATH, true );
		}

		WPSC_Server::instance()->write();

		$new_exclude = is_array( $new_settings ) && isset( $new_settings['exclude_urls'] )
			? $new_settings['exclude_urls']
			: $old_exclude;
		self::delete_excluded_public_cache( $old_exclude, $new_exclude, $cache_dir );
	}

	private static function delete_excluded_public_cache( $old_exclude, $new_exclude, $cache_dir ) {
		$old_patterns = self::parse_lines( $old_exclude );
		$new_patterns = self::parse_lines( $new_exclude );
		$added = array_diff( $new_patterns, $old_patterns );
		if ( empty( $added ) ) {
			return;
		}
		$public_dir = trailingslashit( $cache_dir ) . 'public';
		if ( ! is_dir( $public_dir ) ) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $public_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		$public_len = strlen( $public_dir );
		$to_delete = array();
		foreach ( $iterator as $path ) {
			if ( $path->isFile() && $path->getFilename() === 'index.html' ) {
				$uri = substr( $path->getPath(), $public_len );
				$uri = str_replace( '/index', '/', $uri );
				if ( $uri === '' || $uri === false ) {
					$uri = '/';
				}
				foreach ( $added as $pattern ) {
					if ( @preg_match( $pattern, $uri ) || strpos( $uri, $pattern ) !== false || strpos( $uri . '/', $pattern ) !== false ) {
						$to_delete[] = $path->getPath();
						break;
					}
				}
			}
		}
		foreach ( $to_delete as $dir ) {
			@unlink( $dir . '/index.html' );
			$meta = $dir . '/.meta.json';
			if ( file_exists( $meta ) ) { @unlink( $meta ); }
			$gz = $dir . '/index.html.gz';
			if ( file_exists( $gz ) ) { @unlink( $gz ); }
			@rmdir( $dir );
		}
	}

	private static function parse_lines( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'trim', $value ), 'strlen' ) );
		}
		return array_values( array_filter( array_map( 'trim', explode( "\n", (string) $value ) ), 'strlen' ) );
	}

    public static function is_installed() {
        if ( ! file_exists( WPSC_DROPIN_PATH ) ) {
            return false;
        }
        $content = file_get_contents( WPSC_DROPIN_PATH );
        return is_string( $content ) && strpos( $content, 'WP Static Cache' ) !== false;
    }

    public static function ensure_wp_cache_constant() {
        $config_path = ABSPATH . 'wp-config.php';
        if ( ! file_exists( $config_path ) ) {
            $config_path = dirname( ABSPATH ) . '/wp-config.php';
        }
        if ( ! file_exists( $config_path ) ) {
            return;
        }
        $content = file_get_contents( $config_path );
        if ( ! is_string( $content ) ) {
            return;
        }
        $pattern = '/define\s*\(\s*[\'"]WP_CACHE[\'"]\s*,\s*(true|false)\s*\)\s*;/i';
        if ( preg_match( $pattern, $content, $matches ) ) {
            if ( strtolower( $matches[1] ) === 'false' ) {
                $content = preg_replace( $pattern, "define( 'WP_CACHE', true );", $content, 1 );
                file_put_contents( $config_path, $content );
            }
            return;
        }
        $marker = "/* That's all, stop editing!";
        $pos = strpos( $content, $marker );
        if ( $pos === false ) {
            $marker = "require_once(ABSPATH . 'wp-settings.php')";
            $pos = strpos( $content, $marker );
        }
        if ( $pos !== false ) {
            $new_content = substr( $content, 0, $pos ) . "define( 'WP_CACHE', true );\n\n" . substr( $content, $pos );
            file_put_contents( $config_path, $new_content );
        }
    }
}
