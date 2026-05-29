# WP Static Cache — Admin Tabs Reference

Complete reference — all 10 admin tabs + the About page.

---

## Overview

**WP Static Cache** is a dual-layer WordPress caching plugin that serves **disk-based public pages** to non-logged-in visitors and **fully isolated private pages** to authenticated users, keyed per `role_hash`. It features granular **exclusion rules**, automatic cache invalidation via **event-driven flush**, and **JavaScript optimization** for Core Web Vitals.

Its standout feature is the **Stale-While-Revalidate (SWR) preload** — expired pages are served instantly to visitors while fresh content is regenerated in the background, giving **zero waiting time during cache rebuilds**. This sets it apart from most caching plugins that delete expired cache immediately, causing a performance spike on the next request.

**Tab order** (registered in `class-wpsc-settings.php:23–32`): Dashboard → General → Public Cache → Private Cache → ⭐ **Preload** (USP) → Exclusions → Auto Flush → Logging → JS Optimization → Tools.

---

### 1. Dashboard
`tab-dashboard.php`

The main overview page. Six AJAX-loaded cards show the entire plugin at a glance:

- **Cache Status** — Whether public & private cache are active
- **Quick Actions** — Flush All Cache, Run Preload Now, View Log, More Tools
- **System Health** — `WP_CACHE` constant, `advanced-cache.php` drop-in, directory permissions
- **Cache Statistics** — File count, disk usage, hit/miss ratios
- **Preload Status** — Queue progress bar and current URL being crawled
- **Recent Activity** — Last 8 raw log entries

<img width="1753" height="834" alt="image" src="https://github.com/user-attachments/assets/4bef4aec-d0b1-49a5-8ae2-2df16ea0745f" />

---

### 2. General
`tab-general.php`

Core global plugin settings rendered dynamically by `WPSC_Settings::render_fields()` from the `'general'` field group:

- Cache directory path (default: `/wp-content/cache/wpsc/`)
- Default cache expiry TTL
- Cache variation (mobile detection, scheme, query string handling)
- Gzip pre-compression toggle

  <img width="1743" height="901" alt="image" src="https://github.com/user-attachments/assets/76962b30-7bb2-4ca0-b99a-84330730b980" />


---

### 3. Public Cache
`tab-public-cache.php`

Disk-based page caching for **non-logged-in visitors only**.

- **Serve method**: `PHP` (default via drop-in), `htaccess` (Apache rewrites skip PHP), `nginx` (generates nginx map block rules)
- Cache expiry, Gzip, purge-on-update settings
- **Nginx config** panel with copy-to-clipboard (shown when method = nginx)
- **.htaccess notice** confirming rules written (shown when method = htaccess)

  <img width="1743" height="899" alt="image" src="https://github.com/user-attachments/assets/a2a62e6a-6d88-462f-9e41-7f2759da6af6" />


---

### 4. Private Cache
`tab-private-cache.php`

Per-user isolated cache for **logged-in users**. Keyed by `md5(roles|user_id)` so content is never shared.

- **Status badge** — Enabled/Disabled with colour coding
- **Object Cache detection** — Shows whether `wp_cache_*` (Redis/Memcached) is available; if active, pages served from memory
- Enable/disable toggle, role-based serving, expiry settings
  
<img width="1742" height="895" alt="image" src="https://github.com/user-attachments/assets/aa2e5ee4-7d90-4e25-8b1f-7eb40cd8e736" />


---

### ⭐ 5. Preload
`tab-preload.php`

> **Unique Selling Point** — This is the plugin's standout feature. Most caching plugins delete expired cache immediately, causing a performance spike on the next request. WP Static Cache serves the stale page instantly and regenerates in the background — zero waiting time during cache expiry.

Background cache warmer with **Stale-While-Revalidate (SWR)**:

- **Manual buttons**: "Preload Now" and "Stop Preload"
- **Preload sources**: sitemap, post types, taxonomies, menu items, custom URLs
- **Post count** per feed (default: 100), taxonomy selection (default: Categories only)
- **Max pagination pages** per term (default: 1)
- **SWR window** — seconds stale content is served while regenerating (default: 300)

<img width="1747" height="906" alt="image" src="https://github.com/user-attachments/assets/f572f2d2-959c-47f9-a7f4-13a659fecb95" />


---

### 6. Exclusions
`tab-exclusions.php`

Granular rules preventing specific requests from being cached or served from cache:

- **URL patterns** — glob-style patterns (`/checkout/*`, `/cart/*`)
- **Query strings** — parameters that bypass cache
- **Cookies** — if present, skip cache
- **User agents** — bots/crawlers to exclude
- **Post IDs** — specific pages/posts never cached
- **REST API routes** — exclude specific API namespaces
- **RSS feeds** — toggle feed caching

<img width="1748" height="904" alt="image" src="https://github.com/user-attachments/assets/095630b8-a04c-404d-8bf0-7e043633d638" />


---

### 7. Auto Flush
`tab-auto-flush.php`

Intelligent cache invalidation — each event toggled independently:

- Post published / updated / deleted
- Comment posted / approved / unapproved
- Theme switched
- Plugin activated / deactivated
- Menus modified
- Widgets added / removed / updated
- User logged in / logged out (private cache)

<img width="1744" height="904" alt="image" src="https://github.com/user-attachments/assets/d2759780-7090-4939-aab7-4fe67eeadcb6" />

---

### 8. Logging
`tab-logging.php`

Debugging and monitoring centre. All cache operations (serve, miss, flush, preload) logged to a rolling file.

- **Settings**: enable logging, log level (info/warning/error), max log file size
- **Live viewer** — terminal-style `<pre>` block (green-on-black) showing last 100 entries
- **Actions**: Refresh, Download Log, Clear Log, Write Test Entry
- **Metadata**: current log file size & total entry count

<img width="1752" height="902" alt="image" src="https://github.com/user-attachments/assets/47900f70-e940-414a-9782-8eb1081e1f52" />


---

### 9. JS Optimization
`tab-js-optimization.php`

JavaScript performance tool applied **only to public cached pages**. Analyze a URL, discover all scripts, then block/defer/delay them.

- **Delay Timeout** — inline number input (1–120s) for delayed script auto-load
- **Test URL** — enter a URL and click "Analyze" to fetch & parse all scripts
- **Script table** (sortable, filterable): #, Script, Source (Inline/Plugin/Theme/WP Core/Local/External), Status (None/Blocked/Deferred/Delayed), Size, Actions
- **Bulk actions**: Block, Defer, Delay, Clear
- **Toolbar**: Filter by source/status, "Test in Browser", "Apply to Cache", "Reset All"
- Auto-analyzes the site URL on page load if no test run yet

<img width="1728" height="906" alt="image" src="https://github.com/user-attachments/assets/add45d7f-f280-4ba2-9920-f8d63c339c1d" />


---

### 10. Tools
`tab-tools.php`

Utility panel (no Save button — all panels are read-only or action-based):

- **Cache Statistics** — file count, disk usage, cache hit/miss
- **Preload Status** — live queue progress
- **System Information** — PHP version, server, WordPress constants, disk free space
- **Reset All Settings to Defaults** — restores every plugin option to default

<img width="1742" height="900" alt="image" src="https://github.com/user-attachments/assets/4185873d-30c7-414f-bc1f-768630abe5b2" />


---

### + About
`about.php`

Plugin information page (linked from Dashboard, not a tab):

- Plugin logo, name + version, author with link
- **9 Key Features** — Public Cache, Private Cache, SWR, Smart Preload, JS Optimization, Granular Exclusions, Intelligent Auto-Flush, Gzip Compression, Server Config Support
- **Changelog** — parsed from `readme.txt` with version headings + date

---

*Plugin version 2.2.3 — 11 views — 10 settings sections — 1 informational page*
