<?php
defined( 'ABSPATH' ) || exit;

class WPSC_Image_Optimizer {
    private static $instance = null;
    private $settings;
    private $gd_webp = false;
    private $gd_avif = false;
    private $imagick_webp = false;
    private $imagick_avif = false;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->settings = WPSC_Settings::instance();
        $this->detect_capabilities();
    }

    private function detect_capabilities() {
        if ( extension_loaded( 'gd' ) ) {
            try {
                $info = gd_info();
                $this->gd_webp = ! empty( $info['WebP Support'] );
                $this->gd_avif = ! empty( $info['AVIF Support'] );
            } catch ( Throwable $e ) {
            }
        }
        if ( extension_loaded( 'imagick' ) ) {
            try {
                $list = Imagick::queryFormats( 'WEBP' );
                $this->imagick_webp = ! empty( $list );
                $list = Imagick::queryFormats( 'AVIF' );
                $this->imagick_avif = ! empty( $list );
            } catch ( Throwable $e ) {
            }
        }
    }

    public function has_webp_support() {
        return $this->gd_webp || $this->imagick_webp;
    }

    public function has_avif_support() {
        return $this->gd_avif || $this->imagick_avif;
    }

    public function get_engine() {
        if ( $this->imagick_webp || $this->imagick_avif ) {
            return 'imagick';
        }
        if ( $this->gd_webp || $this->gd_avif ) {
            return 'gd';
        }
        return 'none';
    }

    public function init_hooks() {
        if ( ! $this->settings->get( 'img_opt_enabled', false ) ) {
            return;
        }
        $this->register_thumb_sizes();
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'on_attachment_metadata' ), 10, 3 );
    }

    public function on_attachment_metadata( $metadata, $attachment_id, $context ) {
        if ( ! $this->settings->get( 'img_opt_enabled', false ) ) {
            return $metadata;
        }
        $file = get_attached_file( $attachment_id );
        if ( ! $file || ! file_exists( $file ) ) {
            return $metadata;
        }
        $mime = get_post_mime_type( $attachment_id );
        if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
            return $metadata;
        }
        $this->convert_single( $file );
        if ( isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
            $dir = dirname( $file );
            foreach ( $metadata['sizes'] as $size ) {
                $size_path = $dir . '/' . $size['file'];
                if ( file_exists( $size_path ) ) {
                    $this->convert_single( $size_path );
                }
            }
        }
        return $metadata;
    }

    public function convert_single( $src_path ) {
        if ( ! file_exists( $src_path ) ) {
            return array();
        }
        $this->maybe_resize_source( $src_path );
        $converted = array();
        if ( $this->settings->get( 'img_opt_webp', true ) && $this->has_webp_support() ) {
            $dest = $src_path . '.webp';
            if ( ! file_exists( $dest ) ) {
                $quality = (int) $this->settings->get( 'img_opt_webp_quality', 82 );
                if ( $this->convert_image( $src_path, $dest, 'webp', $quality ) ) {
                    $converted[] = $dest;
                }
            }
        }
        if ( $this->settings->get( 'img_opt_avif', false ) && $this->has_avif_support() ) {
            $dest = $src_path . '.avif';
            if ( ! file_exists( $dest ) ) {
                $quality = (int) $this->settings->get( 'img_opt_avif_quality', 80 );
                if ( $this->convert_image( $src_path, $dest, 'avif', $quality ) ) {
                    $converted[] = $dest;
                }
            }
        }
        return $converted;
    }

    private function convert_image( $src_path, $dest_path, $format, $quality ) {
        if ( $this->imagick_webp || $this->imagick_avif ) {
            return $this->convert_imagick( $src_path, $dest_path, $format, $quality );
        }
        if ( $this->gd_webp || $this->gd_avif ) {
            return $this->convert_gd( $src_path, $dest_path, $format, $quality );
        }
        return false;
    }

    private function convert_gd( $src_path, $dest_path, $format, $quality ) {
        $info = @getimagesize( $src_path );
        if ( ! $info ) {
            return false;
        }
        $mime = $info['mime'];
        switch ( $mime ) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg( $src_path );
                break;
            case 'image/png':
                $image = @imagecreatefrompng( $src_path );
                break;
            case 'image/gif':
                $image = @imagecreatefromgif( $src_path );
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp( $src_path );
                break;
            default:
                return false;
        }
        if ( ! $image ) {
            return false;
        }
        if ( $mime === 'image/png' ) {
            imagepalettetotruecolor( $image );
            imagealphablending( $image, true );
            imagesavealpha( $image, true );
        }
        $result = false;
        if ( $format === 'webp' ) {
            $result = @imagewebp( $image, $dest_path, $quality );
        } elseif ( $format === 'avif' ) {
            $result = @imageavif( $image, $dest_path, $quality );
        }
        imagedestroy( $image );
        if ( $result ) {
            @chmod( $dest_path, 0644 );
        }
        return $result;
    }

    private function convert_imagick( $src_path, $dest_path, $format, $quality ) {
        try {
            $imagick = new Imagick( $src_path );
            $imagick->setImageFormat( $format );
            $imagick->setImageCompressionQuality( $quality );
            if ( $format === 'webp' ) {
                $imagick->setOption( 'webp:method', '6' );
            }
            $result = $imagick->writeImage( $dest_path );
            $imagick->clear();
            if ( $result ) {
                @chmod( $dest_path, 0644 );
            }
            return $result;
        } catch ( Exception $e ) {
            return false;
        }
    }

    public function register_thumb_sizes() {
        $sizes = $this->get_thumb_sizes();
        foreach ( $sizes as $name => $dim ) {
            if ( $dim['width'] <= 0 && $dim['height'] <= 0 ) {
                continue;
            }
            $w = max( 1, (int) $dim['width'] );
            $h = max( 1, (int) $dim['height'] );
            $crop = ! empty( $dim['crop'] );
            add_image_size( $name, $w, $h, $crop );
            if ( in_array( $name, array( 'thumbnail', 'medium', 'medium_large', 'large' ), true ) ) {
                update_option( "{$name}_size_w", $w );
                update_option( "{$name}_size_h", $h );
                if ( $name === 'thumbnail' ) {
                    update_option( 'thumbnail_crop', $crop ? 1 : 0 );
                }
            }
        }
    }

    public function get_thumb_sizes() {
        $saved = $this->settings->get( 'img_opt_thumb_sizes', array() );
        if ( ! empty( $saved ) && is_array( $saved ) ) {
            return $saved;
        }
        $sizes = array();
        $built_in = array( 'thumbnail', 'medium', 'medium_large', 'large' );
        foreach ( $built_in as $name ) {
            $w = (int) get_option( "{$name}_size_w", 0 );
            $h = (int) get_option( "{$name}_size_h", 0 );
            if ( $w <= 0 && $h <= 0 ) {
                continue;
            }
            $crop = false;
            if ( $name === 'thumbnail' ) {
                $crop = (bool) get_option( 'thumbnail_crop', false );
            }
            $sizes[ $name ] = array( 'width' => $w, 'height' => $h, 'crop' => $crop );
        }
        $additional = wp_get_additional_image_sizes();
        foreach ( $additional as $name => $dim ) {
            if ( isset( $sizes[ $name ] ) ) {
                continue;
            }
            $sizes[ $name ] = array(
                'width'  => (int) $dim['width'],
                'height' => (int) $dim['height'],
                'crop'   => ! empty( $dim['crop'] ),
            );
        }
        $this->settings->update( array( 'img_opt_thumb_sizes' => $sizes ) );
        $this->settings->refresh();
        return $sizes;
    }

    private function maybe_resize_source( $src_path ) {
        $max_w = (int) $this->settings->get( 'img_opt_max_width', 2560 );
        $max_h = (int) $this->settings->get( 'img_opt_max_height', 2560 );
        if ( $max_w <= 0 && $max_h <= 0 ) {
            return;
        }
        $info = @getimagesize( $src_path );
        if ( ! $info ) {
            return;
        }
        list( $orig_w, $orig_h ) = $info;
        $mime = $info['mime'];
        $new_w = $orig_w;
        $new_h = $orig_h;
        if ( $max_w > 0 && $orig_w > $max_w ) {
            $ratio = $max_w / $orig_w;
            $new_w = $max_w;
            $new_h = (int) round( $orig_h * $ratio );
        }
        if ( $max_h > 0 && $new_h > $max_h ) {
            $ratio = $max_h / $new_h;
            $new_h = $max_h;
            $new_w = (int) round( $new_w * $ratio );
        }
        if ( $new_w >= $orig_w && $new_h >= $orig_h ) {
            return;
        }
        if ( $this->imagick_webp || $this->imagick_avif ) {
            $this->maybe_resize_source_imagick( $src_path, $new_w, $new_h );
        } elseif ( $this->gd_webp || $this->gd_avif ) {
            $this->maybe_resize_source_gd( $src_path, $new_w, $new_h, $mime );
        }
    }

    private function maybe_resize_source_gd( $src_path, $new_w, $new_h, $mime ) {
        switch ( $mime ) {
            case 'image/jpeg':
                $image = @imagecreatefromjpeg( $src_path );
                break;
            case 'image/png':
                $image = @imagecreatefrompng( $src_path );
                break;
            case 'image/gif':
                $image = @imagecreatefromgif( $src_path );
                break;
            case 'image/webp':
                $image = @imagecreatefromwebp( $src_path );
                break;
            default:
                return;
        }
        if ( ! $image ) {
            return;
        }
        $orig_w = imagesx( $image );
        $orig_h = imagesy( $image );
        $resized = imagecreatetruecolor( $new_w, $new_h );
        if ( $mime === 'image/png' ) {
            imagealphablending( $resized, false );
            imagesavealpha( $resized, true );
        }
        imagecopyresampled( $resized, $image, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h );
        imagedestroy( $image );
        switch ( $mime ) {
            case 'image/jpeg':
                $quality = apply_filters( 'jpeg_quality', 82, 'image_resize' );
                @imagejpeg( $resized, $src_path, $quality );
                break;
            case 'image/png':
                @imagepng( $resized, $src_path );
                break;
            case 'image/gif':
                @imagegif( $resized, $src_path );
                break;
            case 'image/webp':
                $quality = (int) $this->settings->get( 'img_opt_webp_quality', 82 );
                @imagewebp( $resized, $src_path, $quality );
                break;
        }
        imagedestroy( $resized );
    }

    private function maybe_resize_source_imagick( $src_path, $new_w, $new_h ) {
        try {
            $imagick = new Imagick( $src_path );
            $imagick->resizeImage( $new_w, $new_h, Imagick::FILTER_LANCZOS, 1 );
            $imagick->writeImage( $src_path );
            $imagick->clear();
        } catch ( Exception $e ) {
        }
    }

    public function process_html( $buffer ) {
        if ( ! $this->settings->get( 'img_opt_enabled', false ) ) {
            return $buffer;
        }
        $generate_webp = $this->settings->get( 'img_opt_webp', true );
        $generate_avif = $this->settings->get( 'img_opt_avif', false );
        $skip_classes = $this->get_skip_classes();
        $site_url = home_url();
        $site_host = parse_url( $site_url, PHP_URL_HOST );
        $upload_dir = wp_get_upload_dir();
        $base_url = trailingslashit( $upload_dir['baseurl'] );

        $buffer = preg_replace_callback(
            '/<img\b([^>]*?)>/is',
            function ( $m ) use ( $generate_webp, $generate_avif, $skip_classes, $site_host, $base_url ) {
                $tag = $m[0];
                $attrs = $m[1];
                if ( ! empty( $skip_classes ) ) {
                    if ( preg_match( '/\bclass\s*=\s*["\']([^"\']*)["\']/i', $attrs, $c ) ) {
                        $classes = explode( ' ', $c[1] );
                        foreach ( $classes as $cls ) {
                            if ( in_array( trim( $cls ), $skip_classes, true ) ) {
                                return $tag;
                            }
                        }
                    }
                }
                $src = '';
                if ( preg_match( '/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $s ) ) {
                    $src = $s[1];
                }
                if ( empty( $src ) ) {
                    return $tag;
                }
                if ( strpos( $src, 'data:image/' ) === 0 ) {
                    return $tag;
                }
                $src_host = parse_url( $src, PHP_URL_HOST );
                if ( $src_host && $src_host !== $site_host ) {
                    return $tag;
                }
                $ext = strtolower( pathinfo( $src, PATHINFO_EXTENSION ) );
                if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
                    return $tag;
                }
                $srcset_attr = '';
                $srcset_val = '';
                if ( preg_match( '/\bsrcset\s*=\s*["\']([^"\']+)["\']/i', $attrs, $ss ) ) {
                    $srcset_val = $ss[1];
                }
                $sources = array();
                if ( $generate_avif ) {
                    $avif_src = $this->get_converted_url( $src, 'avif', $base_url );
                    $avif_srcset = $srcset_val ? $this->get_converted_srcset( $srcset_val, 'avif', $base_url ) : '';
                    if ( $avif_src ) {
                        $ss = $avif_src;
                        if ( $avif_srcset ) {
                            $ss = $avif_srcset;
                        }
                        $sources[] = '<source srcset="' . esc_attr( $ss ) . '" type="image/avif">';
                    }
                }
                if ( $generate_webp ) {
                    $webp_src = $this->get_converted_url( $src, 'webp', $base_url );
                    $webp_srcset = $srcset_val ? $this->get_converted_srcset( $srcset_val, 'webp', $base_url ) : '';
                    if ( $webp_src ) {
                        $ss = $webp_src;
                        if ( $webp_srcset ) {
                            $ss = $webp_srcset;
                        }
                        $sources[] = '<source srcset="' . esc_attr( $ss ) . '" type="image/webp">';
                    }
                }
                if ( empty( $sources ) ) {
                    return $tag;
                }
                $new_attrs = $attrs;
                $new_attrs = preg_replace( '/\bsrcset\s*=\s*["\'][^"\']*["\']/i', '', $new_attrs );
                $new_attrs = preg_replace( '/\bsizes\s*=\s*["\'][^"\']*["\']/i', '', $new_attrs );
                $picture = '<picture>';
                foreach ( $sources as $source ) {
                    $picture .= "\n" . $source;
                }
                $picture .= "\n" . '<img' . $new_attrs . '>';
                $picture .= "\n" . '</picture>';
                return $picture;
            },
            $buffer
        );
        $buffer = preg_replace_callback(
            '/data-thumbnail="([^"]+)"/i',
            function ( $m ) use ( $generate_webp, $generate_avif, $base_url ) {
                $url = $m[1];
                if ( $generate_avif ) {
                    $avif = $this->get_converted_url( $url, 'avif', $base_url );
                    if ( $avif ) {
                        return 'data-thumbnail="' . esc_attr( $avif ) . '"';
                    }
                }
                if ( $generate_webp ) {
                    $webp = $this->get_converted_url( $url, 'webp', $base_url );
                    if ( $webp ) {
                        return 'data-thumbnail="' . esc_attr( $webp ) . '"';
                    }
                }
                return $m[0];
            },
            $buffer
        );
        return $buffer;
    }

    private function normalize_image_url( $url, $base_url ) {
        if ( strpos( $url, '//' ) === 0 ) {
            $parsed = parse_url( $base_url );
            $scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] : ( is_ssl() ? 'https' : 'http' );
            $url = $scheme . ':' . $url;
        } elseif ( strpos( $url, '/' ) === 0 ) {
            $parsed = parse_url( $base_url );
            $scheme = isset( $parsed['scheme'] ) ? $parsed['scheme'] : ( is_ssl() ? 'https' : 'http' );
            $host   = isset( $parsed['host'] ) ? $parsed['host'] : '';
            $port   = isset( $parsed['port'] ) ? ':' . $parsed['port'] : '';
            $url    = $scheme . '://' . $host . $port . $url;
        }
        return $url;
    }

    private function get_converted_url( $url, $format, $base_url ) {
        $upload_dir = wp_get_upload_dir();
        $base_path  = untrailingslashit( $upload_dir['basedir'] );
        $url        = $this->normalize_image_url( $url, $base_url );
        $base_path_comp = parse_url( $base_url, PHP_URL_PATH );
        $url_path       = parse_url( $url, PHP_URL_PATH );
        if ( ! $base_path_comp || ! $url_path || strpos( $url_path, $base_path_comp ) !== 0 ) {
            return '';
        }
        $relative   = ltrim( substr( $url_path, strlen( $base_path_comp ) ), '/' );
        $src_path   = $base_path . '/' . $relative;
        if ( ! @is_file( $src_path ) ) {
            return '';
        }
        $converted_path = $src_path . '.' . $format;
        if ( ! @is_file( $converted_path ) ) {
            return '';
        }
        return $url . '.' . $format;
    }

    private function get_converted_srcset( $srcset, $format, $base_url ) {
        $entries = explode( ',', $srcset );
        $new_entries = array();
        foreach ( $entries as $entry ) {
            $entry = trim( $entry );
            if ( empty( $entry ) ) {
                continue;
            }
            $parts = preg_split( '/\s+/', $entry );
            $url = $parts[0];
            $descriptor = isset( $parts[1] ) ? ' ' . $parts[1] : '';
            $converted = $this->get_converted_url( $url, $format, $base_url );
            if ( $converted ) {
                $new_entries[] = $converted . $descriptor;
            } else {
                $new_entries[] = $entry;
            }
        }
        return implode( ', ', $new_entries );
    }

    private function get_skip_classes() {
        $raw = $this->settings->get( 'img_opt_skip_classes', '' );
        if ( empty( $raw ) ) {
            return array();
        }
        $items = is_array( $raw ) ? $raw : explode( ',', $raw );
        $items = array_map( 'trim', $items );
        return array_values( array_filter( $items, 'strlen' ) );
    }

    public function scan_media() {
        @set_time_limit( 120 );
        global $wpdb;
        $check_webp  = $this->settings->get( 'img_opt_webp', true );
        $check_avif  = $this->settings->get( 'img_opt_avif', false );
        $max_per_run = (int) $this->settings->get( 'img_opt_max_per_run', 100 );
        if ( $max_per_run < 1 ) {
            $max_per_run = 100;
        }
        if ( ! $check_webp && ! $check_avif ) {
            return array(
                'total'         => 0,
                'converted'     => 0,
                'pending'       => array(),
                'pending_count' => 0,
            );
        }
        $total = (int) $wpdb->get_var( "
            SELECT COUNT(ID) FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND post_mime_type IN ('image/jpeg','image/png','image/gif','image/webp')
        " );
        $attachments = $wpdb->get_col( "
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND post_mime_type IN ('image/jpeg','image/png','image/gif','image/webp')
            ORDER BY post_date DESC
            LIMIT " . ( $total < 10000 ? $total : 10000 ) . "
        " );
        if ( ! is_array( $attachments ) || ! $total ) {
            return array(
                'total'         => $total ? (int) $total : 0,
                'converted'     => 0,
                'pending'       => array(),
                'pending_count' => 0,
            );
        }
        $converted = 0;
        $checked = array();
        $deletable_ids = array();
        foreach ( $attachments as $id ) {
            $file = get_attached_file( $id );
            if ( ! $file || ! @is_file( $file ) ) {
                continue;
            }
            $has_webp = $check_webp && @is_file( $file . '.webp' );
            $has_avif = $check_avif && @is_file( $file . '.avif' );
            if ( $has_webp || $has_avif ) {
                $converted++;
                if ( count( $deletable_ids ) < $max_per_run ) {
                    $deletable_ids[] = (int) $id;
                }
            } else {
                $checked[] = (int) $id;
            }
        }
        return array(
            'total'           => (int) $total,
            'converted'       => $converted,
            'pending'         => $checked,
            'pending_count'   => count( $checked ),
            'deletable_count' => $converted,
            'deletable_ids'   => $deletable_ids,
        );
    }

    public function convert_batch( $ids ) {
        @set_time_limit( 120 );
        $converted = 0;
        $errors = 0;
        foreach ( $ids as $id ) {
            $id = (int) $id;
            if ( $id <= 0 ) {
                continue;
            }
            $file = get_attached_file( $id );
            if ( ! $file || ! file_exists( $file ) ) {
                $errors++;
                continue;
            }
            $mime = get_post_mime_type( $id );
            if ( ! in_array( $mime, array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ), true ) ) {
                $errors++;
                continue;
            }
            $result = $this->convert_single( $file );
            if ( ! empty( $result ) ) {
                $converted++;
            } else {
                $errors++;
            }
            $metadata = wp_get_attachment_metadata( $id );
            if ( $metadata && isset( $metadata['sizes'] ) ) {
                $dir = dirname( $file );
                foreach ( $metadata['sizes'] as $size ) {
                    $size_path = $dir . '/' . $size['file'];
                    if ( file_exists( $size_path ) ) {
                        $this->convert_single( $size_path );
                    }
                }
            }
        }
        return array(
            'converted' => $converted,
            'errors'    => $errors,
            'processed' => count( $ids ),
        );
    }

    public function get_deletable_ids() {
        @set_time_limit( 120 );
        global $wpdb;
        $ids = $wpdb->get_col( "
            SELECT ID FROM {$wpdb->posts}
            WHERE post_type = 'attachment'
            AND post_mime_type IN ('image/jpeg','image/png','image/gif','image/webp')
            ORDER BY post_date DESC
            LIMIT 10000
        " );
        if ( ! is_array( $ids ) || ! count( $ids ) ) {
            return array( 'total' => 0, 'deletable' => 0, 'ids' => array() );
        }
        $max_per_run = max( 1, (int) $this->settings->get( 'img_opt_max_per_run', 100 ) );
        $deletable = array();
        foreach ( $ids as $id ) {
            $file = get_attached_file( $id );
            if ( ! $file || ! @is_file( $file ) ) {
                continue;
            }
            if ( @is_file( $file . '.webp' ) || @is_file( $file . '.avif' ) ) {
                $deletable[] = (int) $id;
                if ( count( $deletable ) >= $max_per_run ) {
                    break;
                }
            }
        }
        return array(
            'total'     => count( $ids ),
            'deletable' => count( $deletable ),
            'ids'       => $deletable,
        );
    }

    public function delete_converted_batch( $ids ) {
        @set_time_limit( 120 );
        $deleted = 0;
        $errors  = 0;
        foreach ( $ids as $id ) {
            $id = (int) $id;
            if ( $id <= 0 ) {
                continue;
            }
            $file = get_attached_file( $id );
            if ( ! $file || ! @is_file( $file ) ) {
                continue;
            }
            $formats = array( 'webp', 'avif' );
            foreach ( $formats as $format ) {
                $conv = $file . '.' . $format;
                if ( @is_file( $conv ) && @unlink( $conv ) ) {
                    $deleted++;
                }
            }
            $metadata = wp_get_attachment_metadata( $id );
            if ( $metadata && isset( $metadata['sizes'] ) ) {
                $dir = dirname( $file );
                foreach ( $metadata['sizes'] as $size ) {
                    $size_path = $dir . '/' . $size['file'];
                    if ( ! @is_file( $size_path ) ) {
                        continue;
                    }
                    foreach ( $formats as $format ) {
                        $conv = $size_path . '.' . $format;
                        if ( @is_file( $conv ) && @unlink( $conv ) ) {
                            $deleted++;
                        }
                    }
                }
            }
        }
        return array(
            'deleted'   => $deleted,
            'errors'    => $errors,
            'processed' => count( $ids ),
        );
    }

    public function get_stats() {
        $scan = $this->scan_media();
        return array(
            'total'     => $scan['total'],
            'converted' => $scan['converted'],
            'pending'   => $scan['pending_count'],
            'engine'    => $this->get_engine(),
            'webp'      => $this->has_webp_support(),
            'avif'      => $this->has_avif_support(),
        );
    }
}
