<div class="wpsc-dashboard-grid">
    <div class="wpsc-card wpsc-card-status">
        <h3><?php esc_html_e( 'Cache Status', 'wp-static-cache' ); ?></h3>
        <div id="wpsc-dash-status" class="wpsc-dash-section">
            <p class="wpsc-loading"><?php esc_html_e( 'Loading...', 'wp-static-cache' ); ?></p>
        </div>
    </div>

    <div class="wpsc-card wpsc-card-actions">
        <h3><?php esc_html_e( 'Quick Actions', 'wp-static-cache' ); ?></h3>
        <div class="wpsc-dash-actions">
            <button type="button" class="button button-primary wpsc-action-btn" data-action="flush_all">
                <?php esc_html_e( 'Flush All Cache', 'wp-static-cache' ); ?>
            </button>
            <button type="button" class="button wpsc-action-btn" data-action="preload_now">
                <?php esc_html_e( 'Run Preload Now', 'wp-static-cache' ); ?>
            </button>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'logging', $_SERVER['REQUEST_URI'] ) ); ?>" class="button">
                <?php esc_html_e( 'View Log', 'wp-static-cache' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'tab', 'tools', $_SERVER['REQUEST_URI'] ) ); ?>" class="button">
                <?php esc_html_e( 'More Tools', 'wp-static-cache' ); ?>
            </a>
        </div>
    </div>

    <div class="wpsc-card wpsc-card-health">
        <h3><?php esc_html_e( 'System Health', 'wp-static-cache' ); ?></h3>
        <div id="wpsc-dash-system" class="wpsc-dash-section">
            <p class="wpsc-loading"><?php esc_html_e( 'Loading...', 'wp-static-cache' ); ?></p>
        </div>
    </div>

    <div class="wpsc-card wpsc-card-stats">
        <h3><?php esc_html_e( 'Cache Statistics', 'wp-static-cache' ); ?></h3>
        <div id="wpsc-dash-stats" class="wpsc-dash-section">
            <p class="wpsc-loading"><?php esc_html_e( 'Loading...', 'wp-static-cache' ); ?></p>
        </div>
    </div>

    <div class="wpsc-card wpsc-card-preload">
        <h3><?php esc_html_e( 'Preload Status', 'wp-static-cache' ); ?></h3>
        <div id="wpsc-dash-preload" class="wpsc-dash-section">
            <p class="wpsc-loading"><?php esc_html_e( 'Loading...', 'wp-static-cache' ); ?></p>
        </div>
    </div>

    <div class="wpsc-card wpsc-card-recent">
        <h3><?php esc_html_e( 'Recent Activity', 'wp-static-cache' ); ?></h3>
        <div id="wpsc-dash-recent" class="wpsc-dash-section">
            <pre class="wpsc-log-preview"><?php
                $entries = WPSC_Logger::get_recent_entries( 8 );
                if ( empty( $entries ) ) {
                    esc_html_e( 'No log entries yet. Enable logging in the Logging tab.', 'wp-static-cache' );
                } else {
                    echo esc_html( implode( '', $entries ) );
                }
            ?></pre>
        </div>
    </div>
</div>
