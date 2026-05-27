=== WP Static Cache ===
Contributors: sanatdas
Tags: cache, caching, performance, speed, optimization
Requires at least: 5.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 2.2.3
License: GPLv2 or later

Dual-layer caching plugin: disk-based public cache for visitors, isolated per-user private cache for logged-in users, plus smart preload with stale-while-revalidate for instant page delivery.

== Description ==

WP Static Cache is a powerful dual-layer caching plugin for WordPress. It provides disk-based public page caching for non-logged-in visitors and fully isolated per-user private caching for authenticated users — all with a smart stale-while-revalidate preload system.

== Changelog ==

= 2.2.3 =
* Fixed Cache Statistics, Preload Status and System Information panels not loading on the Tools tab.
* Updated default Preload settings: post count to 100, taxonomies to Categories only, max pagination pages per term to 1.
* Disabled all Auto Flush events by default.
* Set JS Optimization default Test URL to the site URL.
* Added Reset All Settings to Defaults button.

= 2.2.2 =
* Added built-in About page for accurate plugin details.
* Fixed Elementor editor compatibility with private cache.
* Fixed URL exclusion patterns with trailing slashes (e.g., /category/lifestyle/).
* Improved exclusion handling for logged-in users (private cache).
* Added elementor-preview to default query string exclusions.
* Updated author and version metadata.

= 2.2.1 =
* Initial stable release.
