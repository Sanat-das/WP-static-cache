<div class="wpsc-settings-section" style="margin-bottom:16px;padding:12px 16px;background:#f0f6fc;border:1px solid #c3d9f0;border-radius:4px;">
    <label for="js_delay_timeout"><strong><?php esc_html_e( 'Delay Timeout (seconds):', 'wp-static-cache' ); ?></strong></label>
    <input type="number" id="js_delay_timeout" class="small-text" value="<?php echo esc_attr( WPSC_Settings::instance()->get( 'js_delay_timeout', 5 ) ); ?>" min="1" max="120" />
    <span class="description"><?php esc_html_e( 'Timeout before delayed scripts load automatically.', 'wp-static-cache' ); ?></span>
    <span id="wpsc-timeout-saved" style="display:none;color:#00a32a;margin-left:8px;"><?php esc_html_e( 'Saved', 'wp-static-cache' ); ?></span>
</div>

<div class="wpsc-test-url-section">
    <h2><?php esc_html_e( 'Test URL', 'wp-static-cache' ); ?></h2>
    <div class="wpsc-test-url-row">
        <label for="wpsc-test-url"><strong><?php esc_html_e( 'URL:', 'wp-static-cache' ); ?></strong></label>
        <input type="url" id="wpsc-test-url" class="regular-text code" value="<?php echo esc_url( WPSC_Settings::instance()->get( 'js_test_url', home_url() ) ); ?>" placeholder="https://example.com/page" />
        <button type="button" class="button button-primary" id="wpsc-analyze-btn"><?php esc_html_e( 'Analyze', 'wp-static-cache' ); ?></button>
        <span class="spinner" id="wpsc-analyze-spinner"></span>
    </div>

    <div id="wpsc-test-results" style="display:none;">
        <div class="wpsc-test-status" id="wpsc-test-status"></div>

        <div class="wpsc-bulk-row">
            <label><input type="checkbox" id="wpsc-select-all" /> <?php esc_html_e( 'Select All', 'wp-static-cache' ); ?></label>
            <select id="wpsc-bulk-action">
                <option value=""><?php esc_html_e( 'Bulk Actions', 'wp-static-cache' ); ?></option>
                <option value="block"><?php esc_html_e( 'Block', 'wp-static-cache' ); ?></option>
                <option value="defer"><?php esc_html_e( 'Defer', 'wp-static-cache' ); ?></option>
                <option value="delay"><?php esc_html_e( 'Delay', 'wp-static-cache' ); ?></option>
                <option value="clear"><?php esc_html_e( 'Clear', 'wp-static-cache' ); ?></option>
            </select>
            <button type="button" class="button" id="wpsc-apply-bulk"><?php esc_html_e( 'Apply Bulk', 'wp-static-cache' ); ?></button>
        </div>

        <div class="wpsc-toolbar-row">
            <select id="wpsc-filter-source">
                <option value="all"><?php esc_html_e( 'All Sources', 'wp-static-cache' ); ?></option>
                <option value="Inline"><?php esc_html_e( 'Inline', 'wp-static-cache' ); ?></option>
                <option value="Plugin"><?php esc_html_e( 'Plugin', 'wp-static-cache' ); ?></option>
                <option value="Theme"><?php esc_html_e( 'Theme', 'wp-static-cache' ); ?></option>
                <option value="WP Core"><?php esc_html_e( 'WP Core', 'wp-static-cache' ); ?></option>
                <option value="Local"><?php esc_html_e( 'Local', 'wp-static-cache' ); ?></option>
                <option value="External"><?php esc_html_e( 'External', 'wp-static-cache' ); ?></option>
            </select>
            <select id="wpsc-filter-status">
                <option value="all"><?php esc_html_e( 'All Statuses', 'wp-static-cache' ); ?></option>
                <option value="none"><?php esc_html_e( 'None', 'wp-static-cache' ); ?></option>
                <option value="blocked"><?php esc_html_e( 'Blocked', 'wp-static-cache' ); ?></option>
                <option value="deferred"><?php esc_html_e( 'Deferred', 'wp-static-cache' ); ?></option>
                <option value="delayed"><?php esc_html_e( 'Delayed', 'wp-static-cache' ); ?></option>
            </select>
            <span class="wpsc-toolbar-sep"></span>
            <button type="button" class="button" id="wpsc-open-url"><?php esc_html_e( 'Test in Browser', 'wp-static-cache' ); ?></button>
            <button type="button" class="button button-primary" id="wpsc-apply-cache"><?php esc_html_e( 'Apply to Cache', 'wp-static-cache' ); ?></button>
            <button type="button" class="button" id="wpsc-reset-all" style="color:#d63638;border-color:#d63638;"><?php esc_html_e( 'Reset All', 'wp-static-cache' ); ?></button>
            <span id="wpsc-notices" style="display:inline-block;margin-left:8px;"></span>
        </div>

        <table class="wp-list-table widefat striped" id="wpsc-script-table">
            <thead>
                <tr>
                    <th scope="col" class="check-column"></th>
                    <th scope="col" class="sortable" data-sort="index" width="40"><?php esc_html_e( '#', 'wp-static-cache' ); ?><span class="sort-indicator"></span></th>
                    <th scope="col" class="sortable" data-sort="display"><?php esc_html_e( 'Script', 'wp-static-cache' ); ?><span class="sort-indicator"></span></th>
                    <th scope="col" class="sortable" data-sort="source"><?php esc_html_e( 'Source', 'wp-static-cache' ); ?><span class="sort-indicator"></span></th>
                    <th scope="col" class="sortable" data-sort="status"><?php esc_html_e( 'Status', 'wp-static-cache' ); ?><span class="sort-indicator"></span></th>
                    <th scope="col" class="sortable" data-sort="size"><?php esc_html_e( 'Size', 'wp-static-cache' ); ?><span class="sort-indicator"></span></th>
                    <th scope="col" width="200"><?php esc_html_e( 'Actions', 'wp-static-cache' ); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<script>
jQuery(function($) {
    var url = $('#wpsc-test-url').val();
    if (url) {
        setTimeout(function() {
            $('#wpsc-analyze-btn').trigger('click');
        }, 0);
    }
});
</script>
