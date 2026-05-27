<?php
$plugin_data = wpsc_get_plugin_meta();
$description = $plugin_data['Description'] ?? '';
$changelog   = wpsc_parse_changelog();
$features    = array(
    array(
        'title' => __( 'Public Cache for Visitors', 'wp-static-cache' ),
        'desc'  => __( 'Disk-based page caching served exclusively to non-logged-in visitors. Boosts performance and reduces server load.', 'wp-static-cache' ),
    ),
    array(
        'title' => __( 'Private Cache for Logged-In Users', 'wp-static-cache' ),
        'desc'  => __( 'Isolated per-user cache storage for authenticated users, ensuring personalized content and security.', 'wp-static-cache' ),
    ),
    array(
        'title' => __( 'Stale-While-Revalidate (SWR)', 'wp-static-cache' ),
        'desc'  => __( 'Serve expired cached pages instantly to visitors while regenerating fresh content in the background. Zero waiting during cache rebuilds.', 'wp-static-cache' ),
    ),
    array(
        'title' => __( 'Smart Preload', 'wp-static-cache' ),
        'desc'  => __( 'Background cache warming via sitemaps, post types, taxonomies, menus, and custom URLs.', 'wp-static-cache' ),
    ),
    array(
        'title' => __( 'JavaScript Optimization', 'wp-static-cache' ),
        'desc'  => __( 'Block, defer, or delay scripts on cached pages to improve Core Web Vitals and page speed.', 'wp-static-cache' ),
    ),
    array(
        'title' => __( 'Granular Exclusions', 'wp-static-cache' ),
        'desc'  => __( 'Exclude URLs, query strings, cookies, user agents, post IDs, REST API routes, and RSS feeds from caching.', 'wp-static-cache' ),
    ),
    array(
        'title' => __( 'Intelligent Auto-Flush', 'wp-static-cache' ),
        'desc'  => __( 'Automatically invalidates cache on post publish/update, theme switch, menu changes, plugin activation, and more.', 'wp-static-cache' ),
    ),
    array(
        'title' => __( 'Gzip Compression', 'wp-static-cache' ),
        'desc'  => __( 'Serve pre-compressed gzip files for faster downloads and reduced bandwidth.', 'wp-static-cache' ),
    ),
    array(
        'title' => __( 'Server Config Support', 'wp-static-cache' ),
        'desc'  => __( 'Apache .htaccess and Nginx configuration generation for high-performance cache serving.', 'wp-static-cache' ),
    ),
);
?>
<div class="wrap wpsc-dashboard">
    <h1><?php esc_html_e( 'WP Static Cache', 'wp-static-cache' ); ?></h1>

    <div class="wpsc-card" style="max-width:800px;margin-top:16px;">
        <table style="width:100%;border-collapse:collapse;">
            <tr>
                <td style="padding:12px;width:100px;vertical-align:top;">
                    <div style="width:80px;height:80px;background:#2271b1;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                        <span style="color:#fff;font-size:32px;font-weight:700;">W</span>
                    </div>
                </td>
                <td style="padding:12px;vertical-align:top;">
                    <h2 style="margin:0 0 4px 0;font-size:20px;">WP Static Cache <?php echo esc_html( WPSC_VERSION ); ?></h2>
                    <p style="margin:0 0 8px 0;color:#50575e;font-size:13px;">
                        <?php esc_html_e( 'By', 'wp-static-cache' ); ?>
                        <a href="<?php echo esc_url( $plugin_data['AuthorURI'] ?? 'https://wwinnovators.com' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $plugin_data['Author'] ?? 'Sanat Das' ); ?></a>
                    </p>
                    <p style="margin:0 0 8px 0;color:#50575e;font-size:13px;">
                        <?php echo esc_html( $description ); ?>
                    </p>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;">
                        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=wp-static-cache' ) ); ?>" class="button button-primary">
                            <?php esc_html_e( 'Settings', 'wp-static-cache' ); ?>
                        </a>
                        <a href="<?php echo esc_url( $plugin_data['AuthorURI'] ?? 'https://wwinnovators.com' ); ?>" target="_blank" rel="noopener" class="button">
                            <?php esc_html_e( 'Author Site', 'wp-static-cache' ); ?>
                        </a>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div style="max-width:800px;margin-top:20px;">
        <h3 class="wpsc-section-title"><?php esc_html_e( 'Key Features', 'wp-static-cache' ); ?></h3>
        <div class="wpsc-section" style="background:#fff;border:1px solid #dcdcde;border-top:none;padding:16px;">
            <ul style="margin:0;padding-left:18px;list-style:disc;line-height:2;color:#50575e;">
                <?php foreach ( $features as $feature ) : ?>
                    <li><strong><?php echo esc_html( $feature['title'] ); ?></strong> — <?php echo esc_html( $feature['desc'] ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <h3 class="wpsc-section-title"><?php esc_html_e( 'Changelog', 'wp-static-cache' ); ?></h3>
        <div class="wpsc-section" style="background:#fff;border:1px solid #dcdcde;border-top:none;padding:16px;">
            <?php if ( empty( $changelog ) ) : ?>
                <p style="color:#999;"><?php esc_html_e( 'No changelog available.', 'wp-static-cache' ); ?></p>
            <?php else : ?>
                <?php foreach ( $changelog as $version => $entries ) : ?>
                    <div style="margin-bottom:16px;">
                        <strong style="font-size:14px;"><?php echo esc_html( $version ); ?></strong>
                        <?php if ( $version === WPSC_VERSION ) : ?>
                            <span style="color:#666;font-size:12px;">(<?php echo esc_html( date_i18n( 'F j, Y' ) ); ?>)</span>
                        <?php endif; ?>
                        <?php if ( ! empty( $entries ) ) : ?>
                            <ul style="margin:8px 0 0 0;padding-left:18px;list-style:disc;color:#50575e;line-height:1.8;">
                                <?php foreach ( $entries as $entry ) : ?>
                                    <li><?php echo esc_html( $entry ); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>