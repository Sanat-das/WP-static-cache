(function($) {
    'use strict';
    $(function() {
        $(".wpsc-action-btn[data-action]").not("[data-action=refresh_log],[data-action=clear_log],[data-action=download_log],[data-action=test_log],[data-action=preload_now]").on("click", function(e) {
            e.preventDefault();
            var btn = $(this);
            var action = btn.data("action");
            var originalText = btn.text();
            var confirmMsg = btn.data("confirm");
            if (confirmMsg && !confirm(confirmMsg)) {
                return;
            }
            btn.addClass("wpsc-processing").prop("disabled", true).text("Processing...");
            $.post(wpscAdmin.ajaxUrl, {
                action: "wpsc_action",
                wpsc_action: action,
                _ajax_nonce: wpscAdmin.nonce
            }, function(response) {
                if (response.success) {
                    btn.text("Done").addClass("button-primary");
                } else {
                    btn.text("Error").addClass("button-primary");
                }
                setTimeout(function() {
                    btn.text(originalText).prop("disabled", false).removeClass("button-primary wpsc-processing");
                }, 2000);
                loadDashboard();
            }).fail(function() {
                btn.text("Error").addClass("button-primary");
                setTimeout(function() {
                    btn.text(originalText).prop("disabled", false).removeClass("button-primary wpsc-processing");
                }, 3000);
            });
        });
        function setProcessing(btn, busy, text) {
            if (busy) {
                btn.addClass("wpsc-processing").prop("disabled", true).text(text);
            } else {
                btn.removeClass("wpsc-processing").prop("disabled", false).text(text);
            }
        }
        function handleLogAction(e) {
            var btn = this;
            e.preventDefault();
            e.stopPropagation();
            var confirmMsg = btn.getAttribute("data-confirm");
            if (confirmMsg) {
                try { if (!confirm(confirmMsg)) { return; } } catch(ex) {}
            }
            var action = btn.getAttribute("data-action");
            var $btn = $(btn);
            if (action === "refresh_log") {
                setProcessing($btn, true, "Refreshing...");
                $.post(wpscAdmin.ajaxUrl, { action: "wpsc_action", wpsc_action: "refresh_log", _ajax_nonce: wpscAdmin.nonce }, function(resp) {
                    if (resp.success) { $("#wpsc-log-viewer").find("pre").text(resp.data || ""); }
                    setProcessing($btn, false, "Refresh");
                }).fail(function() { setProcessing($btn, false, "Refresh"); });
            } else if (action === "clear_log") {
                setProcessing($btn, true, "Clearing...");
                $.post(wpscAdmin.ajaxUrl, { action: "wpsc_action", wpsc_action: "clear_log", _ajax_nonce: wpscAdmin.nonce }, function(resp) {
                    if (resp.success) { $("#wpsc-log-viewer").find("pre").text("Log cleared."); }
                    setProcessing($btn, false, "Clear Log");
                }).fail(function() { setProcessing($btn, false, "Clear Log"); });
            } else if (action === "download_log") {
                setProcessing($btn, true, "Preparing...");
                $.post(wpscAdmin.ajaxUrl, { action: "wpsc_action", wpsc_action: "download_log", _ajax_nonce: wpscAdmin.nonce }, function(resp) {
                    if (resp.success && resp.data && resp.data.url) { window.location.href = resp.data.url; }
                    else { setProcessing($btn, false, "No log file"); }
                }).fail(function() { setProcessing($btn, false, "Download Log"); });
            } else if (action === "test_log") {
                setProcessing($btn, true, "Writing...");
                $.post(wpscAdmin.ajaxUrl, { action: "wpsc_action", wpsc_action: "test_log", _ajax_nonce: wpscAdmin.nonce }, function() {
                    $btn.text("Written! Refreshing...");
                    $.post(wpscAdmin.ajaxUrl, { action: "wpsc_action", wpsc_action: "refresh_log", _ajax_nonce: wpscAdmin.nonce }, function(r2) {
                        if (r2.success) { $("#wpsc-log-viewer").find("pre").text(r2.data || ""); }
                        setProcessing($btn, false, "Write Test Entry");
                    }).fail(function() { setProcessing($btn, false, "Write Test Entry"); });
                }).fail(function() { setProcessing($btn, false, "Write Test Entry"); });
            }
        }
        var allBtns = document.querySelectorAll("button.wpsc-action-btn[data-action]");
        for (var i = 0; i < allBtns.length; i++) {
            var btn = allBtns[i];
            var a = btn.getAttribute("data-action");
            if (a === "refresh_log" || a === "clear_log" || a === "download_log" || a === "test_log") {
                btn.onclick = null;
                btn.addEventListener("click", handleLogAction);
            }
        }
        var preloadTimer = null;
        function resetPreloadBtns(orig) {
            if (preloadTimer && typeof preloadTimer === "number") { clearInterval(preloadTimer); }
            preloadTimer = null;
            var bs = document.querySelectorAll("button.wpsc-action-btn[data-action=preload_now]");
            for (var j = 0; j < bs.length; j++) {
                bs[j].removeAttribute("data-processing");
                var $b = $(bs[j]);
                var o = orig || bs[j].getAttribute("data-orig") || "Preload Now";
                $b.text("Done").addClass("button-primary");
                (function($b2, o2) {
                    setTimeout(function() {
                        $b2.text(o2).prop("disabled", false).removeClass("button-primary wpsc-processing");
                        loadDashboard();
                    }, 2000);
                })($b, o);
            }
            loadDashboard();
        }
        var preloadNowBtns = document.querySelectorAll("button.wpsc-action-btn[data-action=preload_now]");
        for (var j = 0; j < preloadNowBtns.length; j++) {
            (function(btn) {
                btn.setAttribute("data-orig", $(btn).text());
                btn.onclick = null;
                btn.addEventListener("click", function(e) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    if (preloadTimer) { return; }
                    preloadTimer = "active";
                    var bs = document.querySelectorAll("button.wpsc-action-btn[data-action=preload_now]");
                    for (var k = 0; k < bs.length; k++) { bs[k].setAttribute("data-processing", "1"); }
                    var $btn = $(btn);
                    var orig = btn.getAttribute("data-orig") || "Preload Now";
                    setProcessing($btn, true, "Starting...");
                    $.post(wpscAdmin.ajaxUrl, { action: "wpsc_action", wpsc_action: "preload_now", _ajax_nonce: wpscAdmin.nonce }, function(resp) {
                        if (!resp.success) {
                            preloadTimer = null;
                            setProcessing($btn, false, "Error");
                            setTimeout(function() { setProcessing($btn, false, orig); btn.removeAttribute("data-processing"); }, 3000);
                            return;
                        }
                        for (var k = 0; k < bs.length; k++) { $(bs[k]).text("Preloading..."); }
                        preloadTimer = setInterval(function() {
                            $.post(wpscAdmin.ajaxUrl, { action: "wpsc_action", wpsc_action: "get_stats", _ajax_nonce: wpscAdmin.nonce }, function(s) {
                                if (!s.success) { return; }
                                var d = s.data, p = d.preload;
                                var q = p ? p.queue_size : 0;
                                for (var k = 0; k < bs.length; k++) { $(bs[k]).text("Preloading... (" + q + ")"); }
                                statsCache = d;
                                renderDashboard(d);
                                renderInfoPanels(d);
                                if (q === 0 && (!p || !p.is_running)) {
                                    resetPreloadBtns(orig);
                                }
                            });
                        }, 2000);
                    }).fail(function() {
                        preloadTimer = null;
                        setProcessing($btn, false, "Error");
                        setTimeout(function() { setProcessing($btn, false, orig); btn.removeAttribute("data-processing"); }, 3000);
                    });
                });
            })(preloadNowBtns[j]);
        }
        var stopBtn = document.querySelector("button.wpsc-action-btn[data-action=stop_preload]");
        if (stopBtn) {
            stopBtn.onclick = null;
            stopBtn.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopImmediatePropagation();
                var $btn = $(stopBtn);
                var orig = $btn.text();
                setProcessing($btn, true, "Stopping...");
                if (preloadTimer && typeof preloadTimer === "number") { clearInterval(preloadTimer); }
                preloadTimer = null;
                var bs = document.querySelectorAll("button.wpsc-action-btn[data-action=preload_now]");
                for (var k = 0; k < bs.length; k++) { bs[k].removeAttribute("data-processing"); $(bs[k]).text("Stopped"); }
                $.post(wpscAdmin.ajaxUrl, { action: "wpsc_action", wpsc_action: "stop_preload", _ajax_nonce: wpscAdmin.nonce }, function(resp) {
                    setProcessing($btn, false, resp.success ? "Stopped" : "Error");
                    setTimeout(function() {
                        setProcessing($btn, false, orig);
                        loadDashboard();
                        for (var k = 0; k < bs.length; k++) {
                            var o = bs[k].getAttribute("data-orig") || "Preload Now";
                            $(bs[k]).text(o).prop("disabled", false).removeClass("wpsc-processing");
                        }
                    }, 2000);
                }).fail(function() {
                    setProcessing($btn, false, "Error");
                    setTimeout(function() { setProcessing($btn, false, orig); }, 3000);
                });
            });
        }

        var statsCache = null;
        var statsRequestInFlight = false;

        function loadDashboard() {
            if ($("#wpsc-dash-status").length === 0) { return; }
            statsRequestInFlight = true;
            $.post(wpscAdmin.ajaxUrl, {
                action: "wpsc_action",
                wpsc_action: "get_stats",
                _ajax_nonce: wpscAdmin.nonce
            }, function(response) {
                statsRequestInFlight = false;
                if (!response.success) { return; }
                statsCache = response.data;
                renderDashboard(statsCache);
                renderInfoPanels(statsCache);
            });
        }

        function renderDashboard(d) {
            var c = d.cache, p = d.preload, s = d.system, st = d.settings;
            if (d.recent_activity) { $("#wpsc-dash-recent .wpsc-log-preview").text(d.recent_activity); }

            var pubActive = st ? st.public_cache_enabled : false;
            var privEnabled = st ? st.private_cache_enabled : false;
            var privActive = st ? st.private_cache_active : false;
            var dropinOk = st ? st.dropin_installed : false;
            var privDisplay = privEnabled ? "Enabled (Disk)" : "Inactive";
            var privBadgeClass = privEnabled ? "active" : "inactive";

            var statusHtml = "<table>" +
                "<tr><td>Public Cache</td><td><span class=\"wpsc-dash-status-badge " + (pubActive ? "active" : "inactive") + "\">" + (pubActive ? "Active" : "Inactive") + "</span></td></tr>" +
                "<tr><td>Private Cache</td><td><span class=\"wpsc-dash-status-badge " + privBadgeClass + "\">" + privDisplay + "</span></td></tr>" +
                "<tr><td>Drop-in</td><td><span class=\"wpsc-dash-status-badge " + (dropinOk ? "active" : "inactive") + "\">" + (dropinOk ? "Installed" : "Missing") + "</span></td></tr>" +
                "<tr><td>Cache Files</td><td><strong>" + (c ? c.total_files : 0) + "</strong></td></tr>" +
                "<tr><td>Cache Size</td><td><strong>" + formatBytes(c ? c.total_size : 0) + "</strong></td></tr>" +
                "</table>";
            $("#wpsc-dash-status").html(statusHtml);

            var healthHtml = "<table>" +
                "<tr><td>PHP Version</td><td>" + (s ? s.php_version : "N/A") + "</td></tr>" +
                "<tr><td>WordPress</td><td>" + (s ? s.wp_version : "N/A") + "</td></tr>" +
                "<tr><td>Object Cache</td><td>" + (s ? s.cache_backend : "N/A") + "</td></tr>" +
                "<tr><td>Server</td><td>" + (s ? s.server : "N/A") + "</td></tr>" +
                "<tr><td>Disk Free</td><td>" + (s ? s.disk_free : "N/A") + "</td></tr>" +
                "<tr><td>Memory Limit</td><td>" + (s ? s.memory_limit : "N/A") + "</td></tr>" +
                "</table>";
            $("#wpsc-dash-system").html(healthHtml);

            var statsHtml = "<table>" +
                "<tr><td>Homepage</td><td>" + (c ? c.by_type.home : 0) + "</td></tr>" +
                "<tr><td>Posts/Pages</td><td>" + (c ? c.by_type.single : 0) + "</td></tr>" +
                "<tr><td>Archives</td><td>" + (c ? c.by_type.archive + " (" + c.unique_archives + " unique)" : 0) + "</td></tr>" +
                "<tr><td>Other</td><td>" + (c ? c.by_type.other : 0) + "</td></tr>" +
                "<tr><td>Total</td><td><strong>" + (c ? c.total_files : 0) + "</strong> files (" + formatBytes(c ? c.total_size : 0) + ")</td></tr>" +
                "</table>";
            $("#wpsc-dash-stats").html(statsHtml);

            var preloadHtml = "<table>" +
                "<tr><td>Queue</td><td><strong>" + (p ? p.queue_size : 0) + "</strong> URLs</td></tr>" +
                "<tr><td>Status</td><td>" + (p && p.is_running ? "<span style=\"color:green;font-weight:600\">Running</span>" : "<span>Idle</span>") + "</td></tr>" +
                "<tr><td>Last Run</td><td>" + (p && p.last_run ? new Date(p.last_run * 1000).toLocaleString() : "Never") + "</td></tr>" +
                "</table>";
            $("#wpsc-dash-preload").html(preloadHtml);
        }

        function loadInfoPanels() {
            var panels = $(".wpsc-info-panel").filter(function() {
                return $(this).closest("#wpsc-log-viewer").length === 0;
            });
            if (panels.length === 0) { return; }
            if (statsCache) {
                renderInfoPanels(statsCache);
                return;
            }
            if (statsRequestInFlight) { return; }
            $.post(wpscAdmin.ajaxUrl, {
                action: "wpsc_action",
                wpsc_action: "get_stats",
                _ajax_nonce: wpscAdmin.nonce
            }, function(response) {
                if (!response.success) { return; }
                statsCache = response.data;
                renderInfoPanels(statsCache);
            });
        }

        function renderInfoPanels(stats) {
            $(".wpsc-info-panel").each(function() {
                var panel = $(this);
                var panelType = panel.data("panel");
                if (panel.closest("#wpsc-log-viewer").length) { return; }
                var html = "";
                if (panelType === "cache_stats" && stats.cache) {
                    var c = stats.cache;
                    html = "<table><tr><td>Total Cached Files</td><td><span class=\"wpsc-stat-number\">" + c.total_files + "</span></td></tr>";
                    html += "<tr><td>Total Cache Size</td><td>" + formatBytes(c.total_size) + "</td></tr>";
                    html += "<tr><td>Homepage</td><td>" + c.by_type.home + "</td></tr>";
                    html += "<tr><td>Posts/Pages</td><td>" + c.by_type.single + "</td></tr>";
                    html += "<tr><td>Archives</td><td>" + c.by_type.archive + " (" + c.unique_archives + " unique)</td></tr>";
                    html += "<tr><td>Other</td><td>" + c.by_type.other + "</td></tr>";
                    html += "<tr><td>Largest File</td><td>" + (c.largest_file.name ? c.largest_file.name.split("/").pop() + " (" + formatBytes(c.largest_file.size) + ")" : "N/A") + "</td></tr>";
                    html += "<tr><td>Oldest Entry</td><td>" + (c.oldest < 9999999999 ? new Date(c.oldest * 1000).toLocaleString() : "N/A") + "</td></tr>";
                    html += "<tr><td>Newest Entry</td><td>" + (c.newest > 0 ? new Date(c.newest * 1000).toLocaleString() : "N/A") + "</td></tr></table>";
                } else if (panelType === "preload_status" && stats.preload) {
                    var p = stats.preload;
                    html = "<table><tr><td>Queue Size</td><td><span class=\"wpsc-stat-number\">" + p.queue_size + "</span></td></tr>";
                    html += "<tr><td>Status</td><td>" + (p.is_running ? "<span style=\"color:green;font-weight:600\">Running</span>" : "<span>Idle</span>") + "</td></tr>";
                    html += "<tr><td>Last Run</td><td>" + (p.last_run ? new Date(p.last_run * 1000).toLocaleString() : "Never") + "</td></tr></table>";
                } else if (panelType === "system_info" && stats.system) {
                    var s = stats.system;
                    html = "<table>";
                    $.each(s, function(k, v) {
                        var label = k.replace(/_/g, " ").replace(/\b\w/g, function(l) { return l.toUpperCase(); });
                        html += "<tr><td>" + label + "</td><td>" + v + "</td></tr>";
                    });
                    html += "</table>";
                } else {
                    html = "<p>No data available.</p>";
                }
                panel.html(html);
            });
        }

        loadDashboard();
        loadInfoPanels();
        $(".nav-tab-wrapper a").on("click", function() {
            setTimeout(loadDashboard, 100);
            setTimeout(loadInfoPanels, 100);
        });
        // ============================================
        // Server method: show/hide nginx config & htaccess notice
        // ============================================
        $(document).on("change", "#serve_method", function() {
            var method = $(this).val();
            if (method === "nginx") {
                $("#wpsc-nginx-config").show();
                $("#wpsc-htaccess-notice").hide();
                $.post(wpscAdmin.ajaxUrl, {
                    action: "wpsc_action",
                    wpsc_action: "get_nginx_config",
                    _ajax_nonce: wpscAdmin.nonce
                }, function(resp) {
                    if (resp.success && resp.data) {
                        $("#wpsc-nginx-config-content").text(resp.data);
                    }
                });
            } else if (method === "htaccess") {
                $("#wpsc-nginx-config").hide();
                $("#wpsc-htaccess-notice").show();
            } else {
                $("#wpsc-nginx-config").hide();
                $("#wpsc-htaccess-notice").hide();
            }
        });

        // Copy-to-clipboard for nginx config
        $(document).on("click", ".wpsc-copy-config", function() {
            var targetId = $(this).data("target");
            var text = $("#" + targetId).text();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    var btn = $(this);
                    $(this).text("Copied!").addClass("button-primary");
                    setTimeout(function() { btn.text("Copy to Clipboard").removeClass("button-primary"); }, 2000);
                }.bind(this)).catch(function() {
                    prompt("Press Ctrl+C to copy:", text);
                });
            } else {
                prompt("Press Ctrl+C to copy:", text);
            }
        });

		$(document).on("click", "#wp-admin-bar-wpsc-cache .wpsc-flush-action > a", function(e) {
            e.preventDefault();
            var item = $(this).closest("li");
            var action = item.attr("id").replace("wp-admin-bar-wpsc-cache-", "");
            var original = $(this).text();
            $(this).text("Flushing...");
            $.post(wpscAdmin.ajaxUrl, {
                action: "wpsc_action",
                wpsc_action: action,
                _ajax_nonce: wpscAdmin.nonce
            }, function(response) {
                item.find("> a").text(response.success ? "Done ✓" : "Error ✗");
                setTimeout(function() { item.find("> a").text(original); }, 2000);
                loadDashboard();
            }).fail(function() {
                item.find("> a").text("Error ✗");
                setTimeout(function() { item.find("> a").text(original); }, 3000);
            });
        });
        $(document).on("click", "#wp-admin-bar-wpsc-cache .wpsc-flush-current-url > a", function(e) {
            e.preventDefault();
            var item = $(this).closest("li");
            var original = $(this).text();
            $(this).text("Flushing...");
            $.post(wpscAdmin.ajaxUrl, {
                action: "wpsc_action",
                wpsc_action: "flush_current_url",
                current_url: window.location.href,
                _ajax_nonce: wpscAdmin.nonce
            }, function(response) {
                item.find("> a").text(response.success ? "Done ✓" : "Error ✗");
                setTimeout(function() { item.find("> a").text(original); }, 2000);
                loadDashboard();
            }).fail(function() {
                item.find("> a").text("Error ✗");
                setTimeout(function() { item.find("> a").text(original); }, 3000);
            });
        });
    });

    function formatBytes(bytes) {
        if (bytes === 0) return "0 B";
        var k = 1024, sizes = ["B", "KB", "MB", "GB"], i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
    }

    // ============================================
    // JS Optimization Test Tool
    // ============================================
    var scriptsData = [];

    $(document).on("click", "#wpsc-analyze-btn", function() {
        var url = $("#wpsc-test-url").val().trim();
        if (!url) return;
        var btn = $(this).prop("disabled", true);
        $("#wpsc-analyze-spinner").addClass("is-active");
        $("#wpsc-test-results").hide();
        $("#wpsc-test-status").html("Fetching " + url + "...");

        $.post(wpscAdmin.ajaxUrl, {
            action: "wpsc_action",
            wpsc_action: "js_analyze_url",
            target_url: url,
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            btn.prop("disabled", false);
            $("#wpsc-analyze-spinner").removeClass("is-active");
            if (!resp.success) {
                $("#wpsc-test-status").html('<span style="color:#d63638">Error: ' + resp.data + "</span>");
                return;
            }
            scriptsData = resp.data;
            renderResults();
            $("#wpsc-test-results").show();
        }).fail(function(jqXHR, textStatus) {
            btn.prop("disabled", false);
            $("#wpsc-analyze-spinner").removeClass("is-active");
            $("#wpsc-test-status").html('<span style="color:#d63638">AJAX error: ' + textStatus + "</span>");
        });
    });

    function getFilteredData() {
        var sourceFilter = $("#wpsc-filter-source").val();
        var statusFilter = $("#wpsc-filter-status").val();
        var data = scriptsData;
        if (sourceFilter !== "all" || statusFilter !== "all") {
            data = scriptsData.filter(function(s) {
                if (sourceFilter !== "all" && s.source !== sourceFilter) return false;
                if (statusFilter !== "all") {
                    if (statusFilter === "none" && (s.blocked || s.deferred || s.delayed)) return false;
                    if (statusFilter === "blocked" && !s.blocked) return false;
                    if (statusFilter === "deferred" && !s.deferred) return false;
                    if (statusFilter === "delayed" && !s.delayed) return false;
                }
                return true;
            });
        }
        if (sortKey) {
            data = data.slice().sort(function(a, b) {
                var va, vb;
                if (sortKey === "status") {
                    var getStatusVal = function(s) {
                        if (s.blocked) return 3;
                        if (s.deferred) return 2;
                        if (s.delayed) return 1;
                        return 0;
                    };
                    va = getStatusVal(a);
                    vb = getStatusVal(b);
                } else if (sortKey === "size") {
                    va = a.size || 0;
                    vb = b.size || 0;
                } else if (sortKey === "index") {
                    va = parseInt(a.index, 10);
                    vb = parseInt(b.index, 10);
                } else {
                    va = (a[sortKey] || "").toLowerCase();
                    vb = (b[sortKey] || "").toLowerCase();
                }
                if (va < vb) return sortDir === "asc" ? -1 : 1;
                if (va > vb) return sortDir === "asc" ? 1 : -1;
                return 0;
            });
        }
        return data;
    }

    function renderResults() {
        if (!scriptsData || !scriptsData.length) {
            $("#wpsc-script-table tbody").empty();
            updateStatus();
            return;
        }
        var data = getFilteredData();
        if (!data.length) {
            $("#wpsc-script-table tbody").empty();
            updateStatus();
            return;
        }
        var tbody = $("#wpsc-script-table tbody").empty();
        for (var n = 0; n < data.length; n++) {
            var s = data[n];
            var statusText = "None";
            var statusClass = "none";
            if (s.blocked) { statusText = "Blocked"; statusClass = "blocked"; }
            else if (s.deferred) { statusText = "Deferred"; statusClass = "deferred"; }
            else if (s.delayed) { statusText = "Delayed"; statusClass = "delayed"; }

            var sizeBytes = parseInt(s.size, 10);
            var sizeText = sizeBytes > 0 ? formatBytes(sizeBytes) : "?";

            var tr = $("<tr>").append(
                $("<th>", {scope:"row","class":"check-column"}).append(
                    $("<input>", {type:"checkbox","class":"wpsc-script-check", value: s.key})
                ),
                $("<td>").text(s.index),
                $("<td>").append(
                    $("<code>").text(s.display),
                    s.handle ? $("<br>") : "",
                    s.handle ? $("<small>").text("handle: " + s.handle) : ""
                ),
                $("<td>").text(s.source || ""),
                $("<td>").append(
                    $("<span>", {"class":"wpsc-status-badge " + statusClass}).text(statusText)
                ),
                $("<td>").text(sizeText),
                $("<td>", {"class":"wpsc-actions-cell"}).append(
                    $("<button>", {"class":"wpsc-action-text" + (s.blocked ? " active" : ""), "data-key": s.key, "data-action": "block"}).text("Block"),
                    $("<button>", {"class":"wpsc-action-text" + (s.deferred ? " active" : ""), "data-key": s.key, "data-action": "defer"}).text("Defer"),
                    $("<button>", {"class":"wpsc-action-text" + (s.delayed ? " active" : ""), "data-key": s.key, "data-action": "delay"}).text("Delay")
                )
            );
            tbody.append(tr);
        }
        updateStatus();
    }

    function updateStatus() {
        var total = scriptsData.length;
        var filtered = getFilteredData();
        var blocked = filtered.filter(function(s) { return s.blocked; }).length;
        var deferred = filtered.filter(function(s) { return s.deferred; }).length;
        var delayed = filtered.filter(function(s) { return s.delayed; }).length;
        var hasDeferred = filtered.some(function(s) { return s.deferred; });
        var hasDelayed = filtered.some(function(s) { return s.delayed; });
        var label = filtered.length + "/" + total + " scripts";
        $("#wpsc-test-status").html(label + " &middot; " + blocked + ' <span style="color:#d63638">blocked</span> &middot; ' + deferred + ' <span style="color:#00a32a">deferred</span> &middot; ' + delayed + ' <span style="color:#dba617">delayed</span>');
        var $notices = $("#wpsc-notices");
        var hasConflict = filtered.some(function(s) { return s.deferred && s.delayed; });
        if (hasConflict) {
            if ($notices.find(".wpsc-conflict-notice").length === 0) {
                $notices.html('<div class="notice notice-warning inline wpsc-conflict-notice"><p>Some scripts are in both defer and delay lists. Delay takes precedence — these scripts will be delayed, not deferred. Remove individual scripts from one list to resolve.</p></div>');
            }
        } else {
            $notices.find(".wpsc-conflict-notice").remove();
        }
    }

    $(document).on("change", "#wpsc-filter-source, #wpsc-filter-status", function() {
        renderResults();
    });

    $(document).on("click", "#wpsc-script-table .wpsc-action-text", function() {
        var btn = $(this);
        var key = btn.data("key");
        var action = btn.data("action");
        var newState = btn.hasClass("active") ? 0 : 1;

        btn.prop("disabled", true).addClass("wpsc-processing");
        $.post(wpscAdmin.ajaxUrl, {
            action: "wpsc_action",
            wpsc_action: "js_toggle_script_action",
            script_key: key,
            toggle_action: action,
            state: newState,
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            btn.prop("disabled", false).removeClass("wpsc-processing");
            if (resp.success) {
                var s = scriptsData.find(function(x) { return x.key === key; });
                if (s) {
                    s.blocked = action === "block" && newState;
                    s.deferred = action === "defer" && newState;
                    s.delayed = action === "delay" && newState;
                }
                renderResults();
            } else {
                btn.prop("disabled", false).removeClass("wpsc-processing");
                $("#wpsc-notices").html('<div class="notice notice-error inline"><p>Error: ' + resp.data + "</p></div>");
            }
        }).fail(function(jqXHR, textStatus) {
            btn.prop("disabled", false).removeClass("wpsc-processing");
            $("#wpsc-notices").html('<div class="notice notice-error inline"><p>AJAX error: ' + textStatus + "</p></div>");
        });
    });

    $(document).on("change", "#wpsc-select-all", function() {
        $(".wpsc-script-check").prop("checked", $(this).is(":checked"));
    });

    var sortKey = null;
    var sortDir = null;
    $(document).on("click", "#wpsc-script-table th.sortable", function() {
        var th = $(this);
        var key = th.data("sort");
        sortDir = (sortKey === key && sortDir === "asc") ? "desc" : "asc";
        sortKey = key;

        $("#wpsc-script-table th.sortable .sort-indicator").text("");
        th.find(".sort-indicator").text(sortDir === "asc" ? " ▲" : " ▼");
        renderResults();
    });

    $(document).on("click", "#wpsc-apply-bulk", function() {
        var action = $("#wpsc-bulk-action").val();
        if (!action) return;
        var checked = $(".wpsc-script-check:checked").map(function() { return $(this).val(); }).get();
        if (!checked.length) return;

        var btn = $(this).prop("disabled", true).text("Applying...").addClass("wpsc-processing");
        var prev = [];
        checked.forEach(function(key) {
            var s = scriptsData.find(function(x) { return x.key === key; });
            if (!s) return;
            prev.push({ key: key, blocked: s.blocked, deferred: s.deferred, delayed: s.delayed });
            s.blocked = action === "block";
            s.deferred = action === "defer";
            s.delayed = action === "delay";
        });
        renderResults();

        $.post(wpscAdmin.ajaxUrl, {
            action: "wpsc_action",
            wpsc_action: "js_bulk_toggle",
            scripts: JSON.stringify(checked),
            bulk_action: action,
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            btn.prop("disabled", false).text("Apply").removeClass("wpsc-processing");
            $("#wpsc-select-all").prop("checked", false);
            if (!resp.success) {
                prev.forEach(function(o) {
                    var s = scriptsData.find(function(x) { return x.key === o.key; });
                    if (s) { s.blocked = o.blocked; s.deferred = o.deferred; s.delayed = o.delayed; }
                });
                renderResults();
                $("#wpsc-notices").html('<div class="notice notice-error inline"><p>Error: ' + resp.data + "</p></div>");
            }
        }).fail(function(jqXHR, textStatus) {
            btn.prop("disabled", false).text("Apply").removeClass("wpsc-processing");
            $("#wpsc-select-all").prop("checked", false);
            prev.forEach(function(o) {
                var s = scriptsData.find(function(x) { return x.key === o.key; });
                if (s) { s.blocked = o.blocked; s.deferred = o.deferred; s.delayed = o.delayed; }
            });
            renderResults();
            $("#wpsc-notices").html('<div class="notice notice-error inline"><p>AJAX error: ' + textStatus + "</p></div>");
        });
    });

    $(document).on("click", "#wpsc-apply-cache", function() {
        var btn = $(this).prop("disabled", true).text("Saving...");
        $.post(wpscAdmin.ajaxUrl, {
            action: "wpsc_action",
            wpsc_action: "js_apply_cache",
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            btn.prop("disabled", false).text("Apply to Cache");
            if (resp.success) {
                $("#wpsc-notices").html('<div class="notice notice-success inline"><p>' + resp.data.message + "</p></div>");
                loadDashboard();
            } else {
                $("#wpsc-notices").html('<div class="notice notice-error inline"><p>Error: ' + (resp.data || "Unknown error") + "</p></div>");
            }
        }).fail(function(jqXHR, textStatus) {
            btn.prop("disabled", false).text("Apply to Cache");
            $("#wpsc-notices").html('<div class="notice notice-error inline"><p>AJAX error: ' + textStatus + "</p></div>");
        });
    });

    $(document).on("click", "#wpsc-open-url", function() {
        var url = $("#wpsc-test-url").val().trim();
        if (url) {
            var sep = url.indexOf("?") === -1 ? "?" : "&";
            window.open(url + sep + "wpsc_optimized_test=1", "_blank");
        }
    });

    $(document).on("click", "#wpsc-reset-all", function() {
        if (!confirm("Clear all JS optimization settings (block, defer, delay)?")) return;
        var btn = $(this).prop("disabled", true).text("Resetting...");
        $.post(wpscAdmin.ajaxUrl, {
            action: "wpsc_action",
            wpsc_action: "js_reset_all",
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            btn.text("Reset All").prop("disabled", false);
            if (resp.success) {
                scriptsData.forEach(function(s) { s.blocked = false; s.deferred = false; s.delayed = false; });
                renderResults();
                $("#wpsc-notices").html('<div class="notice notice-success inline"><p>' + resp.data.message + "</p></div>");
            } else {
                $("#wpsc-notices").html('<div class="notice notice-error inline"><p>Error: ' + resp.data + "</p></div>");
            }
        }).fail(function(jqXHR, textStatus) {
            btn.text("Reset All").prop("disabled", false);
            $("#wpsc-notices").html('<div class="notice notice-error inline"><p>AJAX error: ' + textStatus + "</p></div>");
        });
    });

    $(document).on("change", "#js_delay_timeout", function() {
        var val = parseInt($(this).val(), 10);
        if (isNaN(val) || val < 1) val = 5;
        if (val > 120) val = 120;
        $(this).val(val);
        $.post(wpscAdmin.ajaxUrl, {
            action: "wpsc_action",
            wpsc_action: "js_save_setting",
            setting_key: "js_delay_timeout",
            setting_value: val,
            _ajax_nonce: wpscAdmin.nonce
        }, function(resp) {
            if (resp.success) {
                $("#wpsc-timeout-saved").show().delay(2000).fadeOut();
            }
        });
    });
})(jQuery);
