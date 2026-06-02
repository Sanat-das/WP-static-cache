# WP Static Cache — AI Agent Instructions

This document defines the core architectural invariants of this plugin. **Every AI agent modifying this codebase must preserve these features.**

---

## Key Features (Invariants)

### 1. Public Cache for Visitors Only
Public cache (`class-wpsc-public-cache.php` + `advanced-cache.php` drop-in) is served **exclusively to non-logged-in visitors** to improve website performance and reduce server load. No authenticated user should ever receive a public cached page.

### 2. Private Cache for Logged-In Users
Private cache (`class-wpsc-private-cache.php`) is available **only for authenticated users**, with fully isolated cache storage for each individual user (keyed by `role_hash` = `md5(roles|user_id)`) to ensure personalized content and security. Never merge or share private cache storage across users.

### 3. JavaScript Optimization for Public Cache Only
JavaScript optimization (`class-wpsc-js-optimizer.php`) is applied **only to public cached pages** via `WPSC_Public_Cache::buffer_callback()`. It must **never** affect logged-in users or private cache pages, to prevent compatibility or functionality issues in admin or user-specific areas.

### 4. Smart Preload with Stale-While-Revalidate (SWR)
Preloading (`class-wpsc-preload.php`) works **only for public cache**. When enabled, expired public cache entries are not deleted immediately. Instead, during a configurable SWR window (`swr_window`, default 300s), the stale cached version is served instantly to visitors while a fresh version is regenerated in the background. This ensures fast page delivery even during cache regeneration.

### 5. Dual Preload Queue (Priority + Main)
Post events (publish, update, delete, trash, comment changes) and manual admin flush actions are added to a **separate priority queue** (`wpsc_preload_queue_priority`) that is drained **before** the main queue on every cron tick. This ensures time-sensitive content changes are regenerated immediately rather than waiting behind hundreds of stale-scan URLs. Callers use `queue_urls($urls, true)` for priority items and `queue_urls($urls)` (or `false`) for routine preload.

### 6. Per-Taxonomy Cache TTL Overrides
Each public taxonomy can have its own TTL setting (`taxonomy_ttl_{slug}`), overriding the global `taxonomy_ttl`. When empty, the global value is inherited. This is surfaced in the General settings tab under "Cache Duration" as a "Per-Taxonomy TTL" subsection. The page type classifier (`wpsc_get_page_type()`) returns `taxonomy:{slug}` for term archive pages, and `wpsc_get_ttl_for_page_type()` checks the per-taxonomy setting before falling back to the global value.

---

## Release Workflow

When the user requests a version bump or new release, the AI agent **must** update all of the following in sync:

### Required Updates on Every Release

| File | What to Update | Where |
|---|---|---|
| `wp-static-cache.php` | `Version:` header comment (line 6) | Plugin header |
| `wp-static-cache.php` | `WPSC_VERSION` constant (line 17) | Must match the header exactly |
| `readme.txt` | `Stable tag:` (line 7) | Must match |
| `readme.txt` | Add new `= X.Y.Z =` section under `== Changelog ==` | With bullet points describing changes since last release |

### Changelog Quality

- Each changelog entry should be a **single, descriptive sentence** starting with a past-tense verb (Added, Fixed, Improved, Updated, Removed)
- Group related changes under the same version heading
- If the about page (`admin/views/about.php`) hardcodes any version-specific text, update it to match

### Example

For a release from `2.2.2` to `2.2.3` with a bug fix:

1. `wp-static-cache.php:6` — `Version: 2.2.2` → `Version: 2.2.3`
2. `wp-static-cache.php:17` — `'2.2.2'` → `'2.2.3'`
3. `readme.txt:7` — `Stable tag: 2.2.2` → `Stable tag: 2.2.3`
4. `readme.txt` — Add after the `= 2.2.2 =` block:
   ```
   = 2.2.3 =
   * Fixed cache collision when multiple users share the same role.
   ```
