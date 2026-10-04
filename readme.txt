=== Mercury Schema ===
Contributors: afridexd
Tags: schema, structured data, json-ld, rich results, seo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight JSON-LD structured data with a one-minute setup. No front-end scripts, one database row per post.

== Description ==

Mercury Schema adds JSON-LD structured data to your site so search engines and AI answers understand it, without slowing your site down.

* A one-minute setup wizard. Pick Basic, Smart or Custom and the site-wide basics (organization, website, pages and breadcrumbs) are set up for you.
* 22 schema types: Organization, Website, Web Page, Breadcrumbs, Person, Site Navigation, Article, Blog Posting, News Article, FAQ, How-To, Video, Image, Recipe, Product, Review, Service, Software App, Local Business, Event, Course and Job Posting.
* Breadcrumbs and navigation are built automatically from your page hierarchy and menu.
* Site-wide schemas with display rules (front page, posts and pages, archives, post types, categories, author roles). A post's own schema replaces a site-wide one of the same type.
* Dynamic values like {{post_title}} and {{featured_image}} keep schema in sync with your content.
* WooCommerce aware: Product schema picks up price, currency, stock status, SKU and rating from the product.
* Live preview of the exact JSON-LD search engines will see, with a one-click Google Rich Results test.
* Clear status on every schema. Incomplete schema is never printed, so it can't hurt your rich results.
* No page-builder modules, no front-end JavaScript, no external requests.
* A full REST API and filters for developers.

New to schema? The [step-by-step help guide](https://afridexd.github.io/MercurySchema/) walks you through setup with screenshots.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/mercury-schema` or install it from the Plugins screen.
2. Activate it. The setup wizard opens automatically.
3. Choose a preset. Then fine-tune site-wide schemas under Mercury Schema → Site-wide Schemas, or edit any post and use the Schema Markup box.

== Frequently Asked Questions ==

= What do the setup presets do? =

Basic switches on Organization, Website, Web Page, Breadcrumbs and Article. Smart adds Person, Blog Posting, FAQ, How-To, Video, Product, Review and Local Business. Custom lets you choose. Finishing setup adds site-wide Organization, Website, Web Page, Breadcrumbs and Article schemas for you if their types are on. You can change any of this later under Mercury Schema → Schema Types.

= What happens when I switch a schema type off? =

It disappears from the editor and stops printing on your site. Any schema you already saved for that type is kept, and comes back when you switch the type on again.

= What's the difference between site-wide and post schemas? =

Site-wide schemas live under Mercury Schema → Site-wide Schemas and appear on every page that matches their display rules. A post's own schema replaces a site-wide schema of the same type on that post.

= Why does a schema say "Incomplete"? =

A required field is empty, often because a dynamic value has nothing to fill in (for example {{featured_image}} on a post without a featured image). Incomplete schema is not printed. Fill the field or set a featured image.

= Does it work alongside Yoast SEO or Rank Math? =

Yes. Mercury Schema only prints the schema you add, as separate script tags. Yoast already adds an Article to posts, and WooCommerce adds a Product on classic themes, so use one source per type. Developers can switch off Yoast's Article with the `wpseo_schema_needs_article` filter, or WooCommerce's Product with `woocommerce_structured_data_product`.

= Does it work with Elementor and other page builders? =

Yes. Schema is printed in the page head, independent of how the content was built. In the Elementor editor, click the Mercury Schema (M) button in the top bar to edit the page's schema without leaving Elementor. With other builders, use the Schema Markup box on the WordPress edit screen.

= Who can edit schema? =

Editors and administrators can edit schema on posts they're allowed to edit. Authors and contributors can't. Site-wide schemas, schema types and the setup wizard are for administrators only. Developers can change this with the mercury_schema_rest_capability and mercury_schema_global_capability filters.

== Changelog ==

= 1.0.0 =
* First public release.
* Setup wizard with Basic, Smart and Custom presets that adds the site-wide basics for you.
* 22 schema types, including automatically generated breadcrumbs and site navigation.
* Site-wide schemas with display rules; a post's own schema replaces a site-wide one of the same type.
* Schema editor for the block editor, the classic editor and Elementor, with live preview, dynamic values and repeatable fields.
* Schema Types screen to switch types on and off; switched-off types keep their data.
* WooCommerce price, currency, stock, SKU and rating values for Product schema.
* Status badges show whether each schema will print; incomplete schema is never printed.
* Editors and administrators edit post schemas; site-wide schemas and setup are administrator-only.
* Password-protected posts never reveal their content through structured data.
