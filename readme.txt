=== Internal Link Manager ===
Contributors: riteshthapa
Tags: internal links, seo, auto link
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically insert internal links into post/page content based on keyword-to-URL rules defined in the admin.

== Description ==

Internal Link Manager allows you to automate your internal linking strategy. By defining keyword-to-URL rules, this plugin will scan your content on the frontend and automatically link matching keywords, helping to improve your SEO without the need to manually edit every post.

### Features
* Add, edit, and manage internal link rules from a clean admin UI.
* Set link title attributes, rel="nofollow", rel="sponsored", and target="_blank".
* Limit maximum links per page and maximum uses per keyword per page.
* Fully compatible with Elementor, Gutenberg, Classic Editor, and WooCommerce.
* Exclude specific posts, pages, or post types from being linked.
* Prevent links from being inserted inside headings, existing links, or images.
* Built with performance in mind using safe HTML parsing and custom database tables.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/internal-link-manager` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the Internal Links menu to configure your settings and create your first link rule.

== Frequently Asked Questions ==

= Will this modify my database content? =
No. The plugin parses the content on-the-fly when rendering the page, leaving your original database content untouched.

= Does it work with Elementor? =
Yes, it is designed to be compatible with Elementor widgets and templates.

== Changelog ==

= 1.0.0 =
* Initial release.
