<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Server {
	private static $instance = null;

	const MARKER_START = '# BEGIN WP Static Cache';
	const MARKER_END   = '# END WP Static Cache';

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function write() {
		WPSC_Settings::instance()->refresh();
		$method = WPSC_Settings::instance()->get( 'serve_method', 'php' );
		switch ( $method ) {
			case 'htaccess':
				$this->write_htaccess();
				break;
			case 'nginx':
			case 'php':
			default:
				$this->remove_htaccess();
				break;
		}
	}

	public function write_htaccess() {
		$htaccess_path = $this->get_htaccess_path();
		if ( ! $htaccess_path || ! is_writable( dirname( $htaccess_path ) ) ) {
			return;
		}

		$cache_dir  = trailingslashit( WPSC_Settings::instance()->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) );
		$public_dir = $cache_dir . 'public';
		$rel_path   = $this->get_relative_path( $public_dir );

		if ( false === $rel_path ) {
			return;
		}
		$rel_path .= '/';

		$settings       = WPSC_Settings::instance();
		$bypass_cookies = $this->normalize_multi_text( $settings->get( 'exclude_cookies', array() ) );
		$rejected_qs    = $this->normalize_multi_text( $settings->get( 'rejected_qs', array( 'nocache', 'preview', 'embed' ) ) );
		$exclude_urls   = $settings->get( 'exclude_urls', '' );
		$cache_control  = $settings->get( 'cache_control_enabled', true );
		$max_age        = (int) $settings->get( 'cache_control_maxage', 3600 );
		$swr            = (int) $settings->get( 'swr_window', 300 );

		$rules = $this->build_htaccess_rules( $rel_path, $bypass_cookies, $rejected_qs, $cache_control, $max_age, $exclude_urls );
		$this->insert_marker( $htaccess_path, $rules );
		$this->set_public_dir_access( true );
		$this->write_public_htaccess( $cache_control, $max_age, $swr );
	}

	public function remove_htaccess() {
		$path = $this->get_htaccess_path();
		if ( $path && file_exists( $path ) ) {
			$this->remove_marker( $path );
		}
		$this->remove_public_htaccess();
		$this->set_public_dir_access( false );
	}

	public static function get_nginx_config() {
		$instance = self::instance();
		$settings = WPSC_Settings::instance();

		$cache_dir  = trailingslashit( $settings->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) ) . 'public';
		$rel_path   = $instance->get_relative_path( $cache_dir );

		if ( false === $rel_path ) {
			return "# Cache directory is outside the web root.\n# Nginx cannot serve from this location directly.";
		}

		$bypass_cookies = $instance->normalize_multi_text( $settings->get( 'exclude_cookies', array() ) );
		$rejected_qs    = $instance->normalize_multi_text( $settings->get( 'rejected_qs', array( 'nocache', 'preview', 'embed' ) ) );
		$exclude_urls   = $settings->get( 'exclude_urls', '' );
		$cache_control  = $settings->get( 'cache_control_enabled', true );
		$max_age        = (int) $settings->get( 'cache_control_maxage', 3600 );

		$config  = "# WP Static Cache - Nginx Configuration\n";
		$config  .= "# Copy this inside your server block (not location block).\n\n";

		$config .= "set \$wpsc_cache_path \"/{$rel_path}\";\n";
		$config .= "set \$wpsc_cache_serve 1;\n\n";

		$config .= "# Bypass: non-GET/HEAD methods\n";
		$config .= "if (\$request_method !~ ^(GET|HEAD)\$) {\n";
		$config .= "    set \$wpsc_cache_serve 0;\n";
		$config .= "}\n\n";

		$config .= "# Bypass: logged-in users\n";
		$config .= "if (\$http_cookie ~* \"wordpress_logged_in_\") {\n";
		$config .= "    set \$wpsc_cache_serve 0;\n";
		$config .= "}\n\n";

		if ( ! empty( $bypass_cookies ) ) {
			$config .= "# Bypass: known bypass cookies\n";
			$config .= "if (\$http_cookie ~* \"" . implode( '|', array_map( function( $c ) { return preg_quote( $c, '/' ); }, $bypass_cookies ) ) . "\") {\n";
			$config .= "    set \$wpsc_cache_serve 0;\n";
			$config .= "}\n\n";
		}

		if ( ! empty( $rejected_qs ) ) {
			$config .= "# Bypass: rejected query strings\n";
			foreach ( $rejected_qs as $qs ) {
				$qs = trim( $qs );
				if ( '' !== $qs ) {
					$config .= "if (\$query_string ~ \"(^|&)" . preg_quote( $qs, '/' ) . "=\") {\n";
					$config .= "    set \$wpsc_cache_serve 0;\n";
					$config .= "}\n";
				}
			}
			$config .= "if (\$query_string != \"\") {\n";
			$config .= "    set \$wpsc_cache_serve 0;\n";
			$config .= "}\n\n";
		}

		$config .= "# Bypass: preload regeneration requests (let them reach WordPress)\n";
		$config .= "if (\$http_x_wpsc_preload = \"1\") {\n";
		$config .= "    set \$wpsc_cache_serve 0;\n";
		$config .= "}\n\n";

		$excluded_urls = $instance->parse_exclude_urls( $exclude_urls );
		if ( ! empty( $excluded_urls ) ) {
			$config .= "# Bypass: excluded URL patterns\n";
			foreach ( $excluded_urls as $uri_pat ) {
				$config .= "if (\$uri ~ " . $uri_pat . ") {\n";
				$config .= "    set \$wpsc_cache_serve 0;\n";
				$config .= "}\n";
			}
			$config .= "\n";
		}

		$config .= "# Map root / to /index and strip trailing slashes for cache lookup\n";
		$config .= "set \$wpsc_cache_uri \$uri;\n";
		$config .= "if (\$uri = \"/\") {\n";
		$config .= "    set \$wpsc_cache_uri \"/index\";\n";
		$config .= "}\n";
		$config .= "if (\$uri ~ ^(.+)/\$) {\n";
		$config .= "    set \$wpsc_cache_uri \$1;\n";
		$config .= "}\n\n";

		$config .= "# Serve from static cache\n";
		$config .= "location / {\n";

		$config .= "    # Serve regular cached file\n";
		$config .= "    set \$wpsc_html_file \$document_root\$wpsc_cache_path\$wpsc_cache_uri/index.html;\n";
		$config .= "    if (\$wpsc_cache_serve = 1) {\n";
		$config .= "        if (-f \$wpsc_html_file) {\n";
		$config .= "            add_header X-Cache HIT;\n";
		if ( $cache_control ) {
			$config .= "            add_header Cache-Control \"public, max-age={$max_age}, stale-while-revalidate=300\";\n";
		}
		$config .= "            try_files \$wpsc_html_file =404;\n";
		$config .= "        }\n";
		$config .= "    }\n\n";

		$config .= "    # Fallback to WordPress\n";
		$config .= "    try_files \$uri \$uri/ /index.php?\$args;\n";
		$config .= "}\n";

		return $config;
	}

	private function get_htaccess_path() {
		if ( ABSPATH ) {
			$path = ABSPATH . '.htaccess';
			if ( file_exists( $path ) || is_writable( dirname( $path ) ) ) {
				return $path;
			}
		}
		return false;
	}

	private function get_relative_path( $absolute_dir ) {
		$absolute_dir = wp_normalize_path( $absolute_dir );
		$abspath      = wp_normalize_path( ABSPATH );

		if ( strpos( $absolute_dir, $abspath ) !== 0 ) {
			return false;
		}

		$rel = substr( $absolute_dir, strlen( $abspath ) );
		$rel = trim( $rel, '/' );
		return $rel;
	}

	private function build_htaccess_rules( $rel_path, $bypass_cookies, $rejected_qs, $cache_control, $max_age, $exclude_urls = '' ) {
		$rules  = "<IfModule mod_rewrite.c>\n";
		$rules .= "RewriteEngine On\n\n";

		$rules .= "# Bypass: non-GET/HEAD methods\n";
		$rules .= "RewriteCond %{REQUEST_METHOD} !^(GET|HEAD)\$ [NC]\n";
		$rules .= "RewriteRule .* - [E=WPSC_BYPASS:1]\n\n";

		$rules .= "# Bypass: logged-in users\n";
		$rules .= "RewriteCond %{HTTP_COOKIE} wordpress_logged_in_ [NC]\n";
		$rules .= "RewriteRule .* - [E=WPSC_BYPASS:1]\n\n";

		$all_cookies = $this->normalize_multi_text( $bypass_cookies );
		if ( ! empty( $all_cookies ) ) {
			$rules .= "# Bypass: known bypass cookies\n";
			$last = array_pop( $all_cookies );
			foreach ( $all_cookies as $c ) {
				$rules .= "RewriteCond %{HTTP_COOKIE} " . preg_quote( $c, '#' ) . " [NC,OR]\n";
			}
			$rules .= "RewriteCond %{HTTP_COOKIE} " . preg_quote( $last, '#' ) . " [NC]\n";
			$rules .= "RewriteRule .* - [E=WPSC_BYPASS:1]\n\n";
		}

		$all_rejected = $this->normalize_multi_text( $rejected_qs );
		if ( ! empty( $all_rejected ) ) {
			$rules .= "# Bypass: rejected query strings\n";
			$qs_pat = '(^|&)(' . implode( '|', array_map( function( $s ) { return preg_quote( $s, '#' ); }, $all_rejected ) ) . ')=';
			$rules .= "RewriteCond %{QUERY_STRING} {$qs_pat} [NC,OR]\n";
			$rules .= "RewriteCond %{QUERY_STRING} .+\n";
			$rules .= "RewriteRule .* - [E=WPSC_BYPASS:1]\n\n";
		}

		$rules .= "# Bypass: preload regeneration requests (let them reach WordPress)\n";
		$rules .= "RewriteCond %{HTTP:X-WPSC-Preload} 1 [NC]\n";
		$rules .= "RewriteRule .* - [E=WPSC_BYPASS:1]\n\n";

		$excluded_urls = $this->parse_exclude_urls( $exclude_urls );
		if ( ! empty( $excluded_urls ) ) {
			$rules .= "# Bypass: excluded URL patterns\n";
			$last_pat = array_pop( $excluded_urls );
			foreach ( $excluded_urls as $uri_pat ) {
				$rules .= "RewriteCond %{REQUEST_URI} " . $uri_pat . " [NC,OR]\n";
			}
			$rules .= "RewriteCond %{REQUEST_URI} " . $last_pat . " [NC]\n";
			$rules .= "RewriteRule .* - [E=WPSC_BYPASS:1]\n\n";
		}

		$rules .= "# Normalize REQUEST_URI: strip leading and trailing slashes for clean cache path\n";
		$rules .= "RewriteCond %{REQUEST_URI} ^/(.*[^/])?/?\$\n";
		$rules .= "RewriteRule .* - [E=WPSC_CACHE_URI:%1]\n\n";

		$rules .= "# Serve plain HTML (root)\n";
		$rules .= "RewriteCond %{ENV:WPSC_BYPASS} !1\n";
		$rules .= "RewriteRule ^\$ /{$rel_path}index/index.html [L,E=WPSC_CACHE_HIT:1]\n\n";

		$rules .= "# Serve plain HTML (non-root)\n";
		$rules .= "RewriteCond %{ENV:WPSC_BYPASS} !1\n";
		$rules .= "RewriteCond %{DOCUMENT_ROOT}/{$rel_path}%{ENV:WPSC_CACHE_URI}/index.html -f\n";
		$rules .= "RewriteRule .* /{$rel_path}%{ENV:WPSC_CACHE_URI}/index.html [L,E=WPSC_CACHE_HIT:1]\n\n";

		$rules .= "</IfModule>\n\n";

		return $rules;
	}

	private function parse_exclude_urls( $value ) {
		if ( is_string( $value ) ) {
			$lines = explode( "\n", $value );
		} elseif ( is_array( $value ) ) {
			$lines = $value;
		} else {
			return array();
		}
		$patterns = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line === '' ) {
				continue;
			}
			if ( @preg_match( $line, '' ) !== false ) {
				$patterns[] = $line;
			} else {
				$patterns[] = preg_quote( $line, '#' );
			}
		}
		return $patterns;
	}

	private function normalize_multi_text( $value ) {
		if ( is_string( $value ) ) {
			$parts = explode( ',', $value );
		} elseif ( is_array( $value ) ) {
			$parts = $value;
		} else {
			return array();
		}
		$parts = array_map( 'trim', $parts );
		$parts = array_filter( $parts, 'strlen' );
		return array_values( $parts );
	}

	private function set_public_dir_access( $allow ) {
		$cache_dir = trailingslashit( WPSC_Settings::instance()->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) );
		$htaccess  = $cache_dir . '.htaccess';
		if ( $allow ) {
			if ( file_exists( $htaccess ) ) {
				$content = file_get_contents( $htaccess );
				if ( strpos( $content, 'Deny from all' ) !== false || strpos( $content, 'Require all denied' ) !== false ) {
					unlink( $htaccess );
				}
			}
		} else {
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Deny from all\n" );
			}
		}
	}

	private function write_public_htaccess( $cache_control, $max_age, $swr ) {
		$cache_dir  = trailingslashit( WPSC_Settings::instance()->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) );
		$public_dir = $cache_dir . 'public';
		if ( ! is_dir( $public_dir ) ) {
			wp_mkdir_p( $public_dir );
		}
		$rules  = "<IfModule mod_headers.c>\n";
		$rules .= "Header set X-Cache HIT\n";
		$rules .= "</IfModule>\n";
		file_put_contents( $public_dir . '/.htaccess', $rules );
	}

	private function remove_public_htaccess() {
		$cache_dir = trailingslashit( WPSC_Settings::instance()->get( 'cache_dir', WPSC_CACHE_DIR_DEFAULT ) );
		$htaccess  = $cache_dir . 'public/.htaccess';
		if ( file_exists( $htaccess ) ) {
			unlink( $htaccess );
		}
	}

	private function insert_marker( $path, $rules ) {
		$this->remove_marker( $path );
		if ( ! file_exists( $path ) ) {
			file_put_contents( $path, '' );
		}
		$content     = file_get_contents( $path );
		$new_content = self::MARKER_START . "\n" . trim( $rules ) . "\n" . self::MARKER_END . "\n\n" . ltrim( $content );
		file_put_contents( $path, $new_content );
	}

	private function remove_marker( $path ) {
		if ( ! file_exists( $path ) ) {
			return;
		}
		$content = file_get_contents( $path );
		$pattern = '/' . preg_quote( self::MARKER_START, '/' ) . '.*?' . preg_quote( self::MARKER_END, '/' ) . '\s*/s';
		$new     = preg_replace( $pattern, '', $content );
		if ( $new !== $content ) {
			file_put_contents( $path, $new );
		}
	}
}
