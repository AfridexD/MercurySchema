=== UnlimitedSchema ===
Contributors: afridexd
Tags: schema, structured data, json-ld, rich results, seo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, conflict-free JSON-LD schema markup. One database row per post, no bloat.

== Description ==

UnlimitedSchema adds JSON-LD structured data to your site without slowing it down.

* Eleven built-in types: Article, Product, Event, FAQ, Recipe, Video, Organization, Person, Local Business, Review and Software App.
* Site-wide schemas: describe your organization once and show it on the front page, or add an Article to every post automatically.
* Dynamic values like {{post_title}} and {{featured_image}} keep schema in sync with your content.
* WooCommerce aware: Product schema picks up price, currency, stock status, SKU and rating from the product.
* Live preview of the exact JSON-LD search engines will see, with a one-click Google Rich Results test.
* Clear status on every schema. Incomplete schema is never printed, so it can't hurt your rich results.
* All of a post's schema is stored in one meta entry, read once per page view.
* No page-builder modules, no front-end JavaScript, no external requests.
* A full REST API and filters for developers.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/unlimited-schema` or install it from the Plugins screen.
2. Activate it.
3. Add site-wide schemas under Settings → UnlimitedSchema, or edit any post and use the Schema Markup box.

== Frequently Asked Questions ==

= What's the difference between site-wide and post schemas? =

Site-wide schemas live under Settings → UnlimitedSchema and appear on every page that matches their display rules (front page, single posts and pages, archives, post types, categories). A post's own schema replaces a site-wide schema of the same type on that post.

= Why does a schema say "Incomplete"? =

A required field is empty, often because a dynamic value has nothing to fill in (for example {{featured_image}} on a post without a featured image). Incomplete schema is not printed. Fill the field or set a featured image.

= Does it work alongside Yoast SEO or Rank Math? =

Yes. UnlimitedSchema only prints the schema you add, as separate script tags. Yoast already adds an Article to posts, and WooCommerce adds a Product on classic themes, so use one source per type. Developers can switch off Yoast's Article with the `wpseo_schema_needs_article` filter, or WooCommerce's Product with `woocommerce_structured_data_product`.

= Does it work with Elementor and other page builders? =

Yes. Schema is printed in the page head, independent of how the content was built. There are no builder-specific modules.

= Who can edit schema? =

Editors and administrators can edit schema on posts they're allowed to edit. Authors and contributors can't. Site-wide schemas are for administrators only. Developers can change this with the unlimited_schema_rest_capability and unlimited_schema_global_capability filters.

== Changelog ==

= 1.1.0 =
* New: editors can edit post schemas (previously administrators only). Site-wide schemas stay administrator-only.
* New: site-wide schemas with display rules (front page, singular, archives, post types, categories, author roles).
* New: FAQ, Recipe, Video and Software App types; Product gains aggregate rating.
* New: repeatable fields for FAQ questions and recipe steps.
* New: live JSON-LD preview with copy and "Test in Google" buttons.
* New: WooCommerce price, currency, stock, SKU and rating values for Product schema.
* New: redesigned editor with type picker, status badges, toggle switches, dynamic-value picker, duplicate, and two-step delete.
* Improved: status reflects what will actually print on the post, including empty dynamic values.
* Improved: clearer, field-specific error messages.

= 1.0.0 =
* Initial release.
