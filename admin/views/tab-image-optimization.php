<?php
$opt = WPSC_Image_Optimizer::instance();
$engine = $opt->get_engine();
?>
<div class="wpsc-section-group">
    <h3 class="wpsc-section-title"><?php esc_html_e( 'System Info', 'wp-static-cache' ); ?></h3>
    <table class="form-table">
        <tr><td><?php esc_html_e( 'Engine', 'wp-static-cache' ); ?></td><td><strong><?php echo esc_html( ucfirst( $engine ) ); ?></strong></td></tr>
        <tr><td><?php esc_html_e( 'WebP Support', 'wp-static-cache' ); ?></td><td><span class="wpsc-dash-status-badge <?php echo $opt->has_webp_support() ? 'active' : 'inactive'; ?>"><?php echo $opt->has_webp_support() ? 'Available' : 'Not available'; ?></span></td></tr>
        <tr><td><?php esc_html_e( 'AVIF Support', 'wp-static-cache' ); ?></td><td><span class="wpsc-dash-status-badge <?php echo $opt->has_avif_support() ? 'active' : 'inactive'; ?>"><?php echo $opt->has_avif_support() ? 'Available' : 'Not available'; ?></span></td></tr>
        <tr><td><?php esc_html_e( 'Storage', 'wp-static-cache' ); ?></td><td><strong><?php echo esc_html( 'WordPress uploads directory (alongside originals)' ); ?></strong></td></tr>
    </table>
</div>

<?php
$img_fields = WPSC_Settings::instance()->get_fields( 'image-optimization' );
$bulk_field_keys = array( 'img_opt_max_per_run', 'img_opt_skip_classes' );
$form_fields = array_diff_key( $img_fields, array_flip( $bulk_field_keys ) );
?>
<form method="post" action="options.php">
    <?php
    settings_fields( 'wpsc_settings' );
    WPSC_Settings::instance()->render_fields( $form_fields );
    submit_button();
    ?>
</form>

<div class="wpsc-section-group">
    <h3 class="wpsc-section-title"><?php esc_html_e( 'Bulk Optimizer', 'wp-static-cache' ); ?></h3>

    <?php
    $settings = WPSC_Settings::instance()->get_all();
    ?>
    <table class="form-table">
    <?php foreach ( $bulk_field_keys as $k ) :
        $def = $img_fields[ $k ];
        $value = isset( $settings[ $k ] ) ? $settings[ $k ] : ( $def['default'] ?? '' );
        $desc = $def['desc'] ?? '';
    ?>
        <tr>
            <th scope="row"><label for="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $def['label'] ); ?></label></th>
            <td>
                <?php if ( $def['type'] === 'number' ) : ?>
                    <input type="number" name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $k ); ?>]" id="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( $value ); ?>" class="small-text" <?php
                        foreach ( ( $def['attrs'] ?? array() ) as $attr => $v ) {
                            echo esc_attr( $attr ) . '="' . esc_attr( $v ) . '" ';
                        }
                    ?>/>
                <?php else : ?>
                    <input type="text" name="<?php echo esc_attr( WPSC_SETTINGS_KEY ); ?>[<?php echo esc_attr( $k ); ?>]" id="<?php echo esc_attr( $k ); ?>" value="<?php echo esc_attr( is_array( $value ) ? implode( ', ', $value ) : $value ); ?>" class="regular-text" />
                <?php endif; ?>
                <?php if ( $desc ) : ?>
                    <p class="description"><?php echo esc_html( $desc ); ?></p>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </table>

    <div id="wpsc-img-opt-summary" style="margin-bottom:12px;">
        <span class="description"><?php esc_html_e( 'Scan your media library to find unconverted images.', 'wp-static-cache' ); ?></span>
    </div>

    <div class="wpsc-test-url-row">
        <button type="button" class="button" id="wpsc-img-scan-btn"><?php esc_html_e( 'Scan Media Library', 'wp-static-cache' ); ?></button>
        <button type="button" class="button button-primary" id="wpsc-img-optimize-btn" disabled><?php esc_html_e( 'Optimize All', 'wp-static-cache' ); ?></button>
        <button type="button" class="button" id="wpsc-img-stop-btn" disabled style="color:#d63638;border-color:#d63638;"><?php esc_html_e( 'Stop', 'wp-static-cache' ); ?></button>
        <button type="button" class="button" id="wpsc-img-delete-btn" disabled style="color:#d63638;border-color:#d63638;"><?php esc_html_e( 'Delete Converted', 'wp-static-cache' ); ?></button>
        <span class="spinner" id="wpsc-img-spinner"></span>
    </div>

    <div id="wpsc-img-progress" style="display:none;margin-top:12px;">
        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
            <span id="wpsc-img-progress-text"><?php esc_html_e( 'Starting...', 'wp-static-cache' ); ?></span>
            <span id="wpsc-img-progress-pct">0%</span>
        </div>
        <div style="background:#f0f0f1;border-radius:4px;height:24px;overflow:hidden;">
            <div id="wpsc-img-progress-bar" style="background:#2271b1;height:100%;width:0%;transition:width 0.3s;"></div>
        </div>
        <p id="wpsc-img-current-file" class="description" style="margin-top:4px;"></p>
    </div>

    <div id="wpsc-img-results" style="display:none;margin-top:12px;padding:12px;background:#f0f6fc;border:1px solid #c3d9f0;border-radius:4px;"></div>
</div>

<hr style="margin:24px 0" />

<div class="wpsc-test-url-section">
    <h2><?php esc_html_e( 'Thumbnail Sizes', 'wp-static-cache' ); ?></h2>
    <p class="description"><?php esc_html_e( 'View and edit all registered WordPress image thumbnail sizes. Changes apply to new uploads only. Existing images are not affected.', 'wp-static-cache' ); ?></p>

    <div id="wpsc-thumb-msg" style="display:none;padding:8px 12px;margin-bottom:12px;border-radius:4px;"></div>

    <table class="wp-list-table widefat fixed striped" style="margin-bottom:12px;">
        <thead>
            <tr>
                <th style="width:22%;"><?php esc_html_e( 'Name', 'wp-static-cache' ); ?></th>
                <th style="width:15%;"><?php esc_html_e( 'Width (px)', 'wp-static-cache' ); ?></th>
                <th style="width:15%;"><?php esc_html_e( 'Height (px)', 'wp-static-cache' ); ?></th>
                <th style="width:15%;"><?php esc_html_e( 'Hard Crop', 'wp-static-cache' ); ?></th>
                <th style="width:33%;"><?php esc_html_e( 'Actions', 'wp-static-cache' ); ?></th>
            </tr>
        </thead>
        <tbody id="wpsc-thumb-tbody">
            <tr><td colspan="5"><span class="spinner is-active" style="float:none;margin:0;"></span> <?php esc_html_e( 'Loading...', 'wp-static-cache' ); ?></td></tr>
        </tbody>
    </table>

    <div style="background:#f6f7f7;padding:12px;border:1px solid #dcdcde;border-radius:4px;">
        <strong><?php esc_html_e( 'Add New Size', 'wp-static-cache' ); ?></strong>
        <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <input type="text" id="wpsc-new-thumb-name" placeholder="<?php esc_attr_e( 'Name (e.g. my-custom-size)', 'wp-static-cache' ); ?>" style="width:200px;" />
            <input type="number" id="wpsc-new-thumb-width" placeholder="<?php esc_attr_e( 'Width', 'wp-static-cache' ); ?>" min="1" style="width:80px;" />
            <input type="number" id="wpsc-new-thumb-height" placeholder="<?php esc_attr_e( 'Height', 'wp-static-cache' ); ?>" min="1" style="width:80px;" />
            <label><input type="checkbox" id="wpsc-new-thumb-crop" /> <?php esc_html_e( 'Hard Crop', 'wp-static-cache' ); ?></label>
            <button type="button" class="button button-primary" id="wpsc-thumb-add-btn"><?php esc_html_e( 'Add Size', 'wp-static-cache' ); ?></button>
            <span class="spinner" id="wpsc-thumb-spinner"></span>
        </div>
    </div>
</div>

<script>
jQuery(function($) {
    var scanning = false;
    var converting = false;
    var deleting = false;
    var stopped = false;
    var pendingIds = [];
    var deletableIds = [];
    var maxPerRun = parseInt($('input[name="wpsc_settings[img_opt_max_per_run]"]').val(), 10) || 100;
    var batchSize = 25;

    function setButtons(scan, optimize, stop, del) {
        $('#wpsc-img-scan-btn').prop('disabled', !scan);
        $('#wpsc-img-optimize-btn').prop('disabled', !optimize);
        $('#wpsc-img-stop-btn').prop('disabled', !stop);
        $('#wpsc-img-delete-btn').prop('disabled', !del);
    }

    function updateProgress(processed, total, currentFile, verb) {
        verb = verb || 'converted';
        var pct = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
        $('#wpsc-img-progress-text').text(processed + ' / ' + total + ' ' + verb);
        $('#wpsc-img-progress-pct').text(pct + '%');
        $('#wpsc-img-progress-bar').css('width', pct + '%');
        if (currentFile) {
            $('#wpsc-img-current-file').text(currentFile);
        }
    }

    $('#wpsc-img-scan-btn').on('click', function() {
        if (scanning) return;
        scanning = true;
        setButtons(false, false, false, false);
        $('#wpsc-img-spinner').addClass('is-active');
        $('#wpsc-img-results').hide();

        $.post(wpscAdmin.ajaxUrl, {
            action: 'wpsc_action',
            wpsc_action: 'img_opt_scan',
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            scanning = false;
            $('#wpsc-img-spinner').removeClass('is-active');
            if (!resp.success) {
                $('#wpsc-img-results').html('<span style="color:#d63638">Error: ' + resp.data + '</span>').show();
                setButtons(true, false, false, false);
                return;
            }
            var r = resp.data;
            var msg = 'Total images: <strong>' + r.total + '</strong> | Converted: <strong>' + r.converted + '</strong> | Pending: <strong>' + r.pending_count + '</strong>';
            if (r.deletable_count > 0) {
                msg += ' | <a href="#" id="wpsc-img-show-deletable" style="color:#d63638;">Delete ' + r.deletable_count + ' converted</a>';
            }
            $('#wpsc-img-results').html(msg).show();

            pendingIds = r.pending;
            deletableIds = r.deletable_ids || [];

            if (r.pending_count > 0) {
                var limit = Math.min(r.pending_count, maxPerRun);
                var btnText = 'Optimize All (newest ' + limit + ')';
                $('#wpsc-img-optimize-btn').text(btnText).prop('disabled', false);
            } else {
                $('#wpsc-img-optimize-btn').text('Optimize All').prop('disabled', true);
            }
            setButtons(true, r.pending_count > 0, false, r.deletable_count > 0);
        }).fail(function(jqXHR, textStatus, errorThrown) {
            scanning = false;
            $('#wpsc-img-spinner').removeClass('is-active');
            var msg = 'Scan failed.';
            if (textStatus) msg += ' Status: ' + textStatus;
            if (errorThrown) msg += ' (' + errorThrown + ')';
            if (jqXHR && jqXHR.responseText) {
                msg += '<br><small style="color:#999">' + jqXHR.responseText.substring(0, 300) + '</small>';
            }
            $('#wpsc-img-results').html('<span style="color:#d63638">' + msg + '</span>').show();
            setButtons(true, true, false, false);
        });
    });

    $('#wpsc-img-optimize-btn').on('click', function() {
        if (converting || pendingIds.length === 0) return;
        converting = true;
        stopped = false;
        setButtons(false, false, true, false);
        $('#wpsc-img-progress').show();
        $('#wpsc-img-results').hide();

        var ids = pendingIds.slice(0, maxPerRun);
        var total = ids.length;
        var processed = 0;
        var errors = 0;
        var batchIndex = 0;
        updateProgress(0, total, '', 'converted');

        function processBatch() {
            if (stopped) {
                converting = false;
                setButtons(true, pendingIds.length > 0, false, deletableIds.length > 0);
                $('#wpsc-img-progress-text').text('Stopped at ' + processed + ' / ' + total);
                return;
            }
            var batch = ids.slice(batchIndex * batchSize, (batchIndex + 1) * batchSize);
            if (batch.length === 0) {
                converting = false;
                setButtons(true, false, false, deletableIds.length > 0);
                $('#wpsc-img-progress-text').text('Done! ' + processed + ' converted' + (errors > 0 ? ', ' + errors + ' errors' : ''));
                var finalMsg = 'Completed: <strong>' + processed + '</strong> converted';
                if (errors > 0) finalMsg += ', <strong>' + errors + '</strong> errors';
                var remaining = pendingIds.length - total;
                if (remaining > 0) {
                    finalMsg += '. <strong>' + remaining + '</strong> images remain (run again to convert more).';
                } else {
                    finalMsg += '. All pending images processed.';
                }
                $('#wpsc-img-results').html(finalMsg).show();
                return;
            }
            $.post(wpscAdmin.ajaxUrl, {
                action: 'wpsc_action',
                wpsc_action: 'img_opt_convert_batch',
                ids: JSON.stringify(batch),
                _ajax_nonce: wpscAdmin.nonce
            }, function(resp) {
                if (resp.success) {
                    processed += resp.data.processed;
                    errors += resp.data.errors;
                } else {
                    errors += batch.length;
                }
                batchIndex++;
                updateProgress(processed, total, batch[0] ? 'Processing...' : '', 'converted');
                setTimeout(processBatch, 300);
            }).fail(function() {
                errors += batch.length;
                batchIndex++;
                updateProgress(processed, total, '', 'converted');
                setTimeout(processBatch, 500);
            });
        }

        processBatch();
    });

    function startDelete() {
        if (deleting || deletableIds.length === 0) return;
        var count = deletableIds.length;
        if (!confirm('Delete ' + count + ' converted WebP/AVIF file' + (count > 1 ? 's' : '') + '? This cannot be undone.')) {
            return;
        }
        deleting = true;
        stopped = false;
        setButtons(false, false, true, false);
        $('#wpsc-img-progress').show();
        $('#wpsc-img-results').hide();

        var ids = deletableIds.slice();
        var total = ids.length;
        var deleted = 0;
        var errors = 0;
        var batchIndex = 0;
        updateProgress(0, total, '', 'deleted');

        function processBatch() {
            if (stopped) {
                deleting = false;
                setButtons(true, false, false, false);
                $('#wpsc-img-progress-text').text('Stopped at ' + deleted + ' / ' + total);
                return;
            }
            var batch = ids.slice(batchIndex * batchSize, (batchIndex + 1) * batchSize);
            if (batch.length === 0) {
                deleting = false;
                setButtons(true, false, false, false);
                var msg = 'Deleted: <strong>' + deleted + '</strong> files';
                if (errors > 0) msg += ', <strong>' + errors + '</strong> errors';
                msg += '. <a href="#" id="wpsc-img-rescan-after-delete">Rescan</a>';
                $('#wpsc-img-results').html(msg).show();
                return;
            }
            $.post(wpscAdmin.ajaxUrl, {
                action: 'wpsc_action',
                wpsc_action: 'img_opt_delete_batch',
                ids: JSON.stringify(batch),
                _ajax_nonce: wpscAdmin.nonce
            }, function(resp) {
                if (resp.success) {
                    deleted += resp.data.deleted;
                }
                batchIndex++;
                updateProgress(deleted, total, '', 'deleted');
                setTimeout(processBatch, 300);
            }).fail(function() {
                batchIndex++;
                updateProgress(deleted, total, '', 'deleted');
                setTimeout(processBatch, 500);
            });
        }

        processBatch();
    }

    $('#wpsc-img-delete-btn').on('click', startDelete);

    $(document).on('click', '#wpsc-img-show-deletable', function(e) {
        e.preventDefault();
        startDelete();
    });

    $(document).on('click', '#wpsc-img-rescan-after-delete', function(e) {
        e.preventDefault();
        $('#wpsc-img-scan-btn').trigger('click');
    });

    $('#wpsc-img-stop-btn').on('click', function() {
        stopped = true;
        $(this).prop('disabled', true);
    });

    $(document).on('change', 'input[name="wpsc_settings[img_opt_max_per_run]"]', function() {
        maxPerRun = parseInt($(this).val(), 10) || 100;
        if (pendingIds.length > 0) {
            var limit = Math.min(pendingIds.length, maxPerRun);
            $('#wpsc-img-optimize-btn').text('Optimize All (newest ' + limit + ')');
        }
    });

    /* ---------- Thumbnail Sizes ---------- */
    var builtInSizes = ['thumbnail', 'medium', 'medium_large', 'large'];

    function showThumbMsg(msg, type) {
        var $msg = $('#wpsc-thumb-msg');
        $msg.text(msg).removeClass('notice-success notice-error').addClass('notice-' + (type || 'success')).show();
        setTimeout(function() { $msg.fadeOut(); }, 3000);
    }

    function renderThumbRows(sizes) {
        var $tbody = $('#wpsc-thumb-tbody');
        if (!sizes || Object.keys(sizes).length === 0) {
            $tbody.html('<tr><td colspan="5">No thumbnail sizes found.</td></tr>');
            return;
        }
        var html = '';
        $.each(sizes, function(name, dim) {
            var isBuiltIn = $.inArray(name, builtInSizes) !== -1;
            var disabledAttr = isBuiltIn ? ' disabled' : '';
            html += '<tr data-name="' + escHtml(name) + '">';
            html += '<td><strong>' + escHtml(name) + '</strong>' + (isBuiltIn ? ' <span class="description">(built-in)</span>' : '') + '</td>';
            html += '<td><input type="number" class="thumb-w" value="' + dim.width + '" min="0" style="width:70px;" /></td>';
            html += '<td><input type="number" class="thumb-h" value="' + dim.height + '" min="0" style="width:70px;" /></td>';
            html += '<td><input type="checkbox" class="thumb-crop" ' + (dim.crop ? 'checked' : '') + ' /></td>';
            html += '<td>';
            html += '<button type="button" class="button button-small thumb-update-btn">Update</button> ';
            if (!isBuiltIn) {
                html += '<button type="button" class="button button-small thumb-delete-btn" style="color:#d63638;border-color:#d63638;">Delete</button>';
            }
            html += '</td>';
            html += '</tr>';
        });
        $tbody.html(html);
    }

    function escHtml(str) {
        return $('<span>').text(str).html();
    }

    function loadThumbSizes() {
        $('#wpsc-thumb-tbody').html('<tr><td colspan="5"><span class="spinner is-active" style="float:none;margin:0;"></span> Loading...</td></tr>');
        $.post(wpscAdmin.ajaxUrl, {
            action: 'wpsc_action',
            wpsc_action: 'img_opt_get_thumb_sizes',
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            if (resp.success) {
                renderThumbRows(resp.data);
            } else {
                $('#wpsc-thumb-tbody').html('<tr><td colspan="5" style="color:#d63638;">Error: ' + resp.data + '</td></tr>');
            }
        }).fail(function() {
            $('#wpsc-thumb-tbody').html('<tr><td colspan="5" style="color:#d63638;">Failed to load thumbnail sizes.</td></tr>');
        });
    }

    $(document).on('click', '.thumb-update-btn', function() {
        var $row = $(this).closest('tr');
        var name = $row.data('name');
        var width = parseInt($row.find('.thumb-w').val(), 10) || 0;
        var height = parseInt($row.find('.thumb-h').val(), 10) || 0;
        var crop = $row.find('.thumb-crop').is(':checked') ? 1 : 0;
        var $btn = $(this).prop('disabled', true).text('Saving...');
        $.post(wpscAdmin.ajaxUrl, {
            action: 'wpsc_action',
            wpsc_action: 'img_opt_save_thumb_size',
            name: name,
            width: width,
            height: height,
            crop: crop,
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            $btn.prop('disabled', false).text('Update');
            if (resp.success) {
                showThumbMsg('Size "' + name + '" saved.');
            } else {
                showThumbMsg(resp.data || 'Save failed.', 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Update');
            showThumbMsg('Save request failed.', 'error');
        });
    });

    $(document).on('click', '.thumb-delete-btn', function() {
        var $row = $(this).closest('tr');
        var name = $row.data('name');
        if (!confirm('Delete size "' + name + '"?')) return;
        var $btn = $(this).prop('disabled', true).text('Deleting...');
        $.post(wpscAdmin.ajaxUrl, {
            action: 'wpsc_action',
            wpsc_action: 'img_opt_delete_thumb_size',
            name: name,
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            if (resp.success) {
                $row.fadeOut(300, function() { $(this).remove(); });
                showThumbMsg(resp.data.message || 'Size deleted.');
            } else {
                $btn.prop('disabled', false).text('Delete');
                showThumbMsg(resp.data || 'Delete failed.', 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Delete');
            showThumbMsg('Delete request failed.', 'error');
        });
    });

    $('#wpsc-thumb-add-btn').on('click', function() {
        var name = $.trim($('#wpsc-new-thumb-name').val());
        var width = parseInt($('#wpsc-new-thumb-width').val(), 10) || 0;
        var height = parseInt($('#wpsc-new-thumb-height').val(), 10) || 0;
        var crop = $('#wpsc-new-thumb-crop').is(':checked') ? 1 : 0;
        if (!name) { showThumbMsg('Please enter a size name.', 'error'); return; }
        if (!/^[a-z0-9_\-]+$/.test(name)) { showThumbMsg('Name may only contain lowercase letters, numbers, hyphens, and underscores.', 'error'); return; }
        if (width <= 0 && height <= 0) { showThumbMsg('Width or height must be greater than 0.', 'error'); return; }
        var $btn = $(this).prop('disabled', true).text('Adding...');
        $('#wpsc-thumb-spinner').addClass('is-active');
        $.post(wpscAdmin.ajaxUrl, {
            action: 'wpsc_action',
            wpsc_action: 'img_opt_add_thumb_size',
            name: name,
            width: width,
            height: height,
            crop: crop,
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            $btn.prop('disabled', false).text('Add Size');
            $('#wpsc-thumb-spinner').removeClass('is-active');
            if (resp.success) {
                $('#wpsc-new-thumb-name, #wpsc-new-thumb-width, #wpsc-new-thumb-height').val('');
                $('#wpsc-new-thumb-crop').prop('checked', false);
                showThumbMsg(resp.data.message || 'Size added.');
                loadThumbSizes();
            } else {
                showThumbMsg(resp.data || 'Add failed.', 'error');
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('Add Size');
            $('#wpsc-thumb-spinner').removeClass('is-active');
            showThumbMsg('Add request failed.', 'error');
        });
    });

    loadThumbSizes();
});
</script>