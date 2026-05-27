<?php
defined( 'ABSPATH' ) || exit;

class WPSC_JS_Optimizer {
    private static $instance = null;
    private $settings;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = WPSC_Settings::instance();
    }

    private function is_login_page() {
        if ( did_action( 'login_init' ) ) {
            return true;
        }
        $login_url = wp_login_url();
        if ( $login_url ) {
            $login_path = parse_url( $login_url, PHP_URL_PATH );
            $req_path   = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
            if ( $login_path && $req_path ) {
                return untrailingslashit( $login_path ) === untrailingslashit( $req_path );
            }
        }
        return false;
    }

    public function init_hooks() {
        add_filter( 'script_loader_tag', array( $this, 'filter_script_tag' ), 10, 3 );
    }

    public function process_html( $buffer ) {
        if ( isset( $_GET['wpsc_analysis'] ) || $this->is_login_page() ) {
            return $buffer;
        }
        $remove_list = $this->get_list( 'js_remove_list' );
        $delay_list = $this->get_list( 'js_delay_include' );

        if ( empty( $remove_list ) && empty( $delay_list ) ) {
            return $buffer;
        }

        $result = $this->process_scripts( $buffer, $remove_list, $delay_list );
        $buffer = $result['html'];

        if ( $result['has_delayed'] && ! empty( $result['urls'] ) ) {
            $buffer = $this->inject_delay_loader( $buffer, $result['urls'] );
        }

        return $buffer;
    }

	public function filter_script_tag( $tag, $handle, $src ) {
		if ( is_admin() || is_user_logged_in() || $this->is_login_page() || ! $this->settings->get( 'public_cache_enabled', true ) ) {
			return $tag;
		}
		$defer_list = $this->get_list( 'js_defer_include' );

        if ( empty( $defer_list ) ) {
            return $tag;
        }

        if ( ! $this->matches_any( $handle, $src, $tag, $defer_list ) ) {
            return $tag;
        }

        if ( strpos( $tag, 'defer' ) !== false || strpos( $tag, 'async' ) !== false ) {
            return $tag;
        }

        return str_replace( ' src=', ' defer src=', $tag );
    }

    public function analyze_html( $html ) {
        $scripts = array();
        preg_match_all( '/<script\b([^>]*?)>(.*?)<\/script>/is', $html, $matches, PREG_SET_ORDER );
        $seen = array();
        $i = 0;

        $remove_list = $this->get_list( 'js_remove_list' );
        $defer_list = $this->get_list( 'js_defer_include' );
        $delay_list = $this->get_list( 'js_delay_include' );

        foreach ( $matches as $m ) {
            $i++;
            $attrs = $m[1];
            $content = trim( $m[2] );

            $src = '';
            if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $s ) ) {
                $src = $s[1];
            }

            $id = '';
            if ( preg_match( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $d ) ) {
                $id = $d[1];
            }

            $handle = $this->get_script_handle( $id );
            $type = '';
            if ( preg_match( '/\btype\s*=\s*["\']([^"\']+)["\']/i', $attrs, $t ) ) {
                $type = $t[1];
            }

            $is_inline = empty( $src );
            $display = $src ?: '<inline> ' . substr( $content, 0, 60 ) . ( strlen( $content ) > 60 ? '...' : '' );
            $key = $src ?: md5( $content );

            $source = '';
            if ( $is_inline ) {
                $source = 'Inline';
            } elseif ( ! empty( $src ) ) {
                $site_url = home_url();
                $site_host = parse_url( $site_url, PHP_URL_HOST );
                $src_host = parse_url( $src, PHP_URL_HOST );
                if ( $src_host && $src_host !== $site_host ) {
                    $source = 'External';
                } elseif ( strpos( $src, '/wp-content/plugins/' ) !== false ) {
                    $source = 'Plugin';
                } elseif ( strpos( $src, '/wp-content/themes/' ) !== false ) {
                    $source = 'Theme';
                } elseif ( strpos( $src, '/wp-includes/' ) !== false || strpos( $src, '/wp-admin/' ) !== false ) {
                    $source = 'WP Core';
                } else {
                    $source = 'Local';
                }
            }

            if ( in_array( $key, $seen ) ) {
                continue;
            }
            $seen[] = $key;

            $full_tag = $m[0];
            $scripts[] = array(
                'index'    => $i,
                'src'      => $src,
                'id'       => $id,
                'handle'   => $handle,
                'type'     => $type,
                'inline'   => $is_inline,
                'content'  => $is_inline ? $content : '',
                'display'  => $display,
                'key'      => $key,
                'source'   => $source,
                'size'     => $this->get_script_size( $src, $content, $is_inline ),
                'blocked'  => $this->matches_any( $handle, $src, $full_tag, $remove_list ),
                'deferred' => $this->matches_any( $handle, $src, $full_tag, $defer_list ),
                'delayed'  => $this->matches_any( $handle, $src, $full_tag, $delay_list ),
            );
        }

        return $scripts;
    }

    private function process_scripts( $html, $remove_patterns, $delay_patterns ) {
        $has_delayed = false;
        $delayed_urls = array();

        $html = preg_replace_callback(
            '/<script\b([^>]*?)>(.*?)<\/script>/is',
            function ( $m ) use ( $remove_patterns, $delay_patterns, &$has_delayed, &$delayed_urls ) {
                $full_tag = $m[0];
                $attrs = $m[1];
                $content = $m[2];

                $src = '';
                if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $s ) ) {
                    $src = $s[1];
                }

                $id = '';
                if ( preg_match( '/\bid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $d ) ) {
                    $id = $d[1];
                }

                $handle = $this->get_script_handle( $id );

                if ( ! empty( $remove_patterns ) && $this->matches_any( $handle, $src, $full_tag, $remove_patterns ) ) {
                    return '';
                }

                if ( ! empty( $delay_patterns ) ) {
                    $type = '';
                    if ( preg_match( '/\btype\s*=\s*["\']([^"\']+)["\']/i', $attrs, $t ) ) {
                        $type = $t[1];
                    }
                    if ( $type !== 'module' && $this->matches_any( $handle, $src, $full_tag, $delay_patterns ) ) {
                        $has_delayed = true;
                        if ( $src ) {
                            $delayed_urls[] = $src;
                        }
                        return '';
                    }
                }

                return $full_tag;
            },
            $html
        );

        return array(
            'html'        => $html,
            'has_delayed' => $has_delayed,
            'urls'        => array_unique( $delayed_urls ),
        );
    }

    private function inject_delay_loader( $html, $urls ) {
        if ( empty( $urls ) ) {
            return $html;
        }

        $timeout = (int) $this->settings->get( 'js_delay_timeout', 5 );
        if ( $timeout < 1 ) {
            $timeout = 5;
        }
        $timeout_ms = $timeout * 1000;

        $json_urls = wp_json_encode( array_values( $urls ) );

        $loader = '<script id="wpsc-delayed-loader">' .
        '(function(){' .
            'var s=' . $json_urls . ',ld=false;' .
            'function ls(){' .
                'if(ld)return;' .
                'ld=true;' .
                's.forEach(function(u){' .
                    'var e=document.createElement("script");' .
                    'e.src=u;' .
                    'e.async=false;' .
                    'document.body.appendChild(e)' .
                '})' .
            '}' .
            'window.addEventListener("scroll",ls,{once:true,passive:true});' .
            'window.addEventListener("mousemove",ls,{once:true,passive:true});' .
            'window.addEventListener("keydown",ls,{once:true,passive:true});' .
            'window.addEventListener("touchstart",ls,{once:true,passive:true});' .
            'window.addEventListener("click",ls,{once:true,passive:true});' .
            'setTimeout(function(){ls()},' . $timeout_ms . ')' .
        '})();' .
        '</script>';

        $pos = strripos( $html, '</body>' );
        if ( $pos !== false ) {
            $html = substr_replace( $html, $loader . "\n", $pos, 0 );
        } else {
            $html .= "\n" . $loader;
        }

        return $html;
    }

    private function get_script_size( $src, $content, $is_inline ) {
        if ( $is_inline ) {
            return strlen( $content );
        }
        $path = $this->resolve_local_path( $src );
        if ( $path && file_exists( $path ) && is_file( $path ) ) {
            $size = @filesize( $path );
            if ( $size !== false ) {
                return $size;
            }
        }
        return 0;
    }

    private function resolve_local_path( $src ) {
        $src = trim( $src );
        if ( empty( $src ) ) {
            return null;
        }
        $src = preg_replace( '/[?#].*$/', '', $src );
        if ( strpos( $src, '//' ) === 0 ) {
            $src = ( is_ssl() ? 'https:' : 'http:' ) . $src;
        }
        $site_url = home_url();
        $abspath = untrailingslashit( ABSPATH );
        if ( strpos( $src, $site_url ) === 0 ) {
            $relative = substr( $src, strlen( $site_url ) );
            return $abspath . '/' . ltrim( $relative, '/' );
        }
        $site_host = parse_url( $site_url, PHP_URL_HOST );
        $src_host = parse_url( $src, PHP_URL_HOST );
        if ( $src_host && $src_host !== $site_host ) {
            return null;
        }
        if ( strpos( $src, '/' ) === 0 ) {
            return $abspath . $src;
        }
        return null;
    }

    private function get_list( $key ) {
        $raw = $this->settings->get( $key, '' );
        if ( empty( $raw ) ) {
            return array();
        }
        $lines = is_array( $raw ) ? $raw : explode( "\n", $raw );
        $lines = array_map( 'trim', $lines );
        return array_values( array_filter( $lines, 'strlen' ) );
    }

    private function get_script_handle( $id ) {
        if ( empty( $id ) ) {
            return '';
        }
        return preg_replace( '/-js$/', '', $id );
    }

    private function matches_any( $handle, $src, $full_tag, $patterns ) {
        if ( empty( $patterns ) ) {
            return false;
        }
        foreach ( $patterns as $pattern ) {
            if ( empty( $pattern ) ) {
                continue;
            }
            if ( $handle && $handle === $pattern ) {
                return true;
            }
            if ( $src && strpos( $src, $pattern ) !== false ) {
                return true;
            }
            if ( strpos( $full_tag, $pattern ) !== false ) {
                return true;
            }
            if ( $pattern[0] === '/' && @preg_match( $pattern, $full_tag ) ) {
                return true;
            }
        }
        return false;
    }
}
