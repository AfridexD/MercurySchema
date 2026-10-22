=== Mercury Schema ===
Contributors: mohammadafride
Tags: schema, structured data, json-ld, rich results, seo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.0
Stable tag: 1.0.1
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

= Works with =

Tested with the block editor, the Classic Editor, Elementor and other popular page builders, WooCommerce, and popular caching and speed plugins. The help guide lists exactly which ones.

= Use one source of schema =

Many SEO plugins add some schema of their own. Search engines should get each kind of schema from one place, so Mercury Schema tells you when it finds a popular SEO or schema plugin on your site, and the help guide shows how to turn off the overlap.

= Privacy =

Mercury Schema does not collect personal data, set cookies, track visitors or contact any outside service. Schema is built on your own server from content you already have. The "Test in Google" buttons only open Google's Rich Results Test when you click them.

= Source code =

All code ships unminified. Development happens on [GitHub](https://github.com/AfridexD/MercurySchema), where you can report issues and follow changes.

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

= Does it work alongside Yoast SEO, Rank Math or another SEO plugin? =

Yes, they run side by side without errors. Most SEO plugins also add some schema (often Website, Web Page, Breadcrumbs, Organization and Article), so use one source per type: turn off schema in your SEO plugin where it has a setting, or switch off the same types under Mercury Schema → Schema Types. Mercury Schema shows a warning when it finds a popular SEO or schema plugin, and the [help guide](https://afridexd.github.io/MercurySchema/#other-schema) lists what each one adds and how to turn it off. WooCommerce also adds Product schema on classic themes; developers can switch that off with the `woocommerce_structured_data_product` filter.

= Does it work with caching plugins? =

Yes. Schema is data for search engines, not a script that runs, and caching plugins' minify, combine and "delay JavaScript" options leave it alone. After changing schema, clear your cache so visitors see the new version.

= Does it work with Elementor and other page builders? =

Yes. Schema is printed in the page head, independent of how the content was built. In the Elementor editor, click the Mercury Schema (M) button in the top bar to edit the page's schema without leaving Elementor. With other builders, use the Schema Markup box on the WordPress edit screen.

= Who can edit schema? =

Editors and administrators can edit schema on posts they're allowed to edit. Authors and contributors can't. Site-wide schemas, schema types and the setup wizard are for administrators only. Developers can change this with the mercury_schema_rest_capability and mercury_schema_global_capability filters.

== Screenshots ==

1. The one-minute setup wizard: choose Basic, Smart or Custom.
2. Add schema to any post or page from the Schema Markup box.
3. Fill in an FAQ. The status shows Ready when the schema is complete.
4. Preview the exact JSON-LD that search engines will see.
5. Schema Types: switch each type on or off. Changes save instantly.
6. Site-wide schemas, such as your Organization, with display rules.
7. Edit schema without leaving the Elementor editor.

== Changelog ==

= 1.0.1 =
* New: a warning when a popular SEO or schema plugin also adds schema, with a link to how to keep one source.
* New: links to the help guide on the Plugins screen and at the end of the setup wizard.
* Fix: page text from shortcode-based page builders (such as WPBakery and Divi) and Brizy now comes through in descriptions.
* Fix: a required-field error now clears as soon as you fill in the field.
* Change: the setup notice can be dismissed.
* Change: upgrading from UnlimitedSchema moves your data but no longer switches the old plugin off for you.
* Tested with WordPress 7.1 and PHP 8.0 to 8.4.

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

== Upgrade Notice ==

= 1.0.1 =
Warns about duplicate schema from other SEO plugins, keeps page-builder text in descriptions, and lets you dismiss the setup notice.
