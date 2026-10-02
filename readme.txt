=== UnlimitedSchema ===
Contributors: afridexd
Tags: schema, structured data, json-ld, rich results, seo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, conflict-free JSON-LD schema markup. One database row per post, no bloat.

== Description ==

UnlimitedSchema adds JSON-LD structured data to your posts and pages without slowing your site down.

* Seven built-in types: Article, Product, Event, Organization, Person, LocalBusiness, Review.
* Dynamic values like {{post_title}} and {{featured_image}} keep schema in sync with your content.
* All of a post's schema is stored in one meta entry, read once per page view.
* Invalid schema is never printed, so it can't hurt your rich results.
* A full REST API and filters for developers.
* No page-builder modules, no front-end JavaScript, no external requests.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/unlimited-schema` or install it from the Plugins screen.
2. Activate it.
3. Edit any post and use the Schema Markup box.

== Frequently Asked Questions ==

= Does it work alongside Yoast SEO or Rank Math? =

Yes. UnlimitedSchema only prints the schema you add, as separate script tags. Yoast already adds an Article to posts, and WooCommerce adds a Product on classic themes, so use one source per type. Developers can switch off Yoast's Article with the `wpseo_schema_needs_article` filter, or WooCommerce's Product with `woocommerce_structured_data_product`.

= Does it work with Elementor and other page builders? =

Yes. Schema is printed in the page head, independent of how the content was built. There are no builder-specific modules.

= Who can edit schema? =

Users with the manage_options capability (administrators). Developers can change this with the unlimited_schema_rest_capability filter.

== Changelog ==

= 1.0.0 =
* Initial release.
