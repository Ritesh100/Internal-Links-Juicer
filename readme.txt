=== OP Internal Link Manager ===
Contributors: riteshthapa
Tags: internal links, seo, auto link, internal linking, link building
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically insert internal links into post/page content based on keyword-to-URL rules defined in the admin.

== Description ==

OP Internal Link Manager allows you to automate your internal linking strategy. By defining keyword-to-URL rules, this plugin will scan your content on the frontend and automatically link matching keywords, helping to improve your SEO without the need to manually edit every post.

### Features
* Add, edit, and manage internal link rules from a clean admin UI.
* Set link title attributes, rel="nofollow", rel="sponsored", and target="_blank".
* Limit maximum links per page and maximum uses per keyword per page.
* Fully compatible with Elementor, Gutenberg, Classic Editor, and WooCommerce.
* Exclude specific posts, pages, or post types from being linked.
* Prevent links from being inserted inside headings, existing links, or images.
* Built with performance in mind using safe HTML parsing and custom database tables.
* Supports pluralization matching for broader keyword coverage.
* ACF compatibility for custom field content linking.
* Open links in new tab and nofollow defaults.
* Debug mode for troubleshooting link insertion.
* Native GitHub update checks using release or tag versions.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/op-internal-link-manager` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Internal Links menu to configure your settings and create your first link rule.

== Frequently Asked Questions ==

= Will this modify my database content? =
No. The plugin parses the content on-the-fly when rendering the page, leaving your original database content untouched.

= Does it work with Elementor? =
Yes, it is designed to be compatible with Elementor widgets and templates.

== Changelog ==

= 1.0.3 =
* Release: 2026-05-07
* Fixed settings tab links after the plugin slug rename
* Updated Targeting, Exclusions, and Advanced tabs to use the registered admin page slug
* Synced remaining settings tab text domain strings with op-internal-link-manager

= 1.0.2 =
* Release: 2026-05-07
* Renamed plugin slug to op-internal-link-manager for consistency
* Added native GitHub update support, with GitHub Updater plugin headers retained
* Updated readme.txt with proper changelog format and expanded descriptions
* Updated WordPress tested up to version 6.8
* Improved feature descriptions in readme
* Fixed text domain consistency across all files
* Minor code cleanup and formatting

= 1.0.1 =
* Release: 2026-05-06
* Fixed plugin rename from internal-link-manager to op-internal-link-juicer
* Updated all text domain references
* Admin UI improvements

= 1.0.0 =
* Release: 2026-01-01
* Initial release
* Core internal linking functionality
* Admin interface for managing link rules
* Elementor compatibility
* WooCommerce compatibility
* Exclusion settings for posts, headings, and existing links
* Global link limits and defaults
