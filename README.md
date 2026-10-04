# Mercury Schema

<p>
  <a href="https://github.com/AfridexD/MercurySchema/releases/latest/download/Mercury-Schema.zip"><img src=".github/download-button.svg" alt="Download Mercury Schema (latest version)" width="372" height="64"></a>
</p>

[All releases and changelogs](https://github.com/AfridexD/MercurySchema/releases). Install: *Plugins → Add New → Upload Plugin* in WordPress.

**New to schema?** The [step-by-step help guide](https://afridexd.github.io/MercurySchema/) covers installing, the setup wizard and adding schema to your posts, with screenshots.

Lightweight, conflict-free JSON-LD schema markup for WordPress, with a one-minute setup wizard.

- **Small.** About 30 PHP files, no runtime dependencies, no build step. The release zip is about 75 KB.
- **One meta row per post.** All of a post's schemas live in a single `_mercury_schema_data` JSON entry.
- **REST-first.** The admin UI is a thin vanilla-JS client of the REST API. Anything the UI does, you can script.
- **Filterable.** Definitions, conditions, token values and final JSON-LD all pass through filters.
- **Testable.** `src/Core` is plain PHP with no WordPress calls and is covered by unit tests.

Requires PHP 8.0+ and WordPress 6.0+.

## Installation

1. Copy this folder to `wp-content/plugins/mercury-schema`.
2. Activate **Mercury Schema** in *Plugins*. The setup wizard opens.
3. Optional: choose post types and output location in *Mercury Schema → Settings*.

## Usage

**Setup wizard** (*Mercury Schema → Setup Wizard* until finished; afterwards **Run setup wizard again** on Schema Types):

1. **Getting Started**, with **Skip** to apply Basic at once.
2. **Configuration**: Basic (Organization, WebSite, WebPage, BreadcrumbList, Article), Smart (Basic plus Person, BlogPosting, FAQPage, HowTo, VideoObject, Product, Review, LocalBusiness) or Custom.
3. **Schemas** (Custom only): switch each of the 22 types on or off.
4. **Done**: finishing adds site-wide Organization and WebSite (front page), WebPage (posts and pages), BreadcrumbList (posts, pages and archives) and Article (posts) for every enabled type that has no site-wide schema yet.

**Schema Types** (*Mercury Schema → Schema Types*): the same toggles, saved instantly. A switched-off type disappears from the editor and stops printing; its saved schemas are kept.

**On a post:** in the **Schema Markup** box, click **Add schema** and pick a type. It is pre-filled with dynamic values such as `{{post_title}}`. Each schema card has three tabs:

- **Fields:** edit values. The `{ }` button inserts a dynamic value at the cursor. FAQ questions and recipe steps are repeatable rows you can add, reorder and remove.
- **Display rules:** optional conditions (post types, categories, author roles).
- **Preview:** the exact JSON-LD that will print, with copy and **Test in Google** buttons.

The badge on each card says whether it will print: **Ready**, **Incomplete** (a required field is empty, for example `{{featured_image}}` on a post without one), **Disabled**, or **Unsaved**.

**In Elementor:** the Elementor editor hides WordPress meta boxes, so Mercury Schema adds an **M** button to Elementor's top bar (via `elementorV2.editorAppBar.utilitiesMenu`; on older Elementor versions, an item in the panel menu). It opens the same editor in a dialog. This loads only inside the Elementor editor, for users and post types that have the Schema Markup box.

**Site-wide:** *Mercury Schema → Site-wide Schemas* uses the same editor. Site-wide schemas appear on every page matching their rules, including a **Show on** rule for the front page, single posts and pages, or archives. New Organization, Local Business and Person schemas default to the front page; content types default to single posts. A post's own schema replaces a site-wide schema of the same type on that post.

Built-in types (22), grouped as in the wizard:

- **Site foundations:** Organization, WebSite, WebPage, BreadcrumbList, Person, SiteNavigationElement
- **Content:** Article, BlogPosting, NewsArticle, FAQPage, HowTo, VideoObject, ImageObject, Recipe
- **Commerce:** Product (with Offer and AggregateRating), Review, Service, SoftwareApplication
- **Local & events:** LocalBusiness, Event, Course, JobPosting

**Generated types.** BreadcrumbList builds the trail from the page hierarchy: page parents, the post's first category with its parents, a custom post type's archive, or the current category, tag, author or post-type archive. It is skipped where the trail would only be "Home". SiteNavigationElement outputs an `ItemList` of the main menu's top-level links, from the classic menu at the chosen location (falling back to the first menu) or the block theme's latest navigation. Their settings (home label, menu location) are `config` fields that are never printed.

### Dynamic values

Any field can contain tokens that are resolved when the page renders:

| Token | Value |
| --- | --- |
| `{{post_title}}` | Post title |
| `{{post_excerpt}}` | Excerpt, or the first 55 words of the content |
| `{{post_url}}` | Permalink |
| `{{post_date}}`, `{{post_modified}}` | ISO 8601 dates (GMT) |
| `{{featured_image}}` | Featured image URL |
| `{{author_name}}`, `{{author_url}}` | Post author |
| `{{site_name}}`, `{{site_description}}`, `{{home_url}}` | Site info |
| `{{site_logo}}` | Custom logo, falling back to the site icon |
| `{{post_content}}` | Post content as plain text (up to 5,000 characters), e.g. for job descriptions |
| `{{site_language}}` | Site language, e.g. `en-US` |

With WooCommerce active, Product schemas default to `{{product_price}}`, `{{product_currency}}`, `{{product_availability}}`, `{{product_sku}}`, `{{product_rating}}` and `{{product_review_count}}`, read from the product.

Post tokens are empty on pages without a post (front page of posts, archives). Add your own tokens with `mercury_schema_token_values` (values) and `mercury_schema_tokens` (labels for the picker).

### Display conditions

Each schema has optional conditions: `post_types`, `categories` (slugs or IDs), `user_roles` (roles of the post's **author**), `post_ids`, and `locations` (`front_page`, `singular`, `archive`). An empty list means no restriction; every non-empty list must match.

### Rendering rules

On each page, site-wide schemas are merged with the current post's own schemas; a post schema replaces any site-wide schema of the same type. A schema renders only when it is enabled, its type is known, its conditions match, and it passes full validation after tokens are resolved. Invalid schemas are skipped silently and logged when `WP_DEBUG` or the debug setting is on.

## REST API

Namespace `mercury-schema/v1`. Post endpoints require `edit_others_posts` (editors and administrators, not authors) plus `edit_post` on that post; filter with `mercury_schema_rest_capability`. The `/global` endpoints require `manage_options` (administrators); filter with `mercury_schema_global_capability`. Use cookie auth with an `X-WP-Nonce: wp_rest` nonce, or Application Passwords.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/schemas` | List types: `{ types: [ { type, label, description, icon, group, generator, fields, nested, enabled } ] }` |
| GET | `/schema-types` | Types keyed by name: `{ Article: { label, description, icon, fields, nested } }` |
| GET | `/schemas/{post_id}` | `{ post_id, version, schemas, status, site_wide }`. `status` maps each schema ID to `{ valid, errors }` with tokens resolved for this post; `site_wide` lists the site-wide schemas whose rules match it. |
| POST | `/schemas/{post_id}` | Create. Body `{ type, data?, conditions?, enabled? }`. Returns `201 { success, schema, status }`. Omitted or empty `data` gets the type's defaults. |
| PUT | `/schemas/{post_id}/{schema_id}` | Update. Any of `type`, `data`, `conditions`, `enabled`. Returns `{ success, schema, status }`. |
| DELETE | `/schemas/{post_id}/{schema_id}` | Returns `{ success, deleted }`. |
| GET, POST | `/global` | Site-wide schemas; same shapes as the per-post routes. |
| PUT, DELETE | `/global/{schema_id}` | Update or delete a site-wide schema. |
| POST | `/preview` | Body `{ type, data, post_id? }`. Returns `{ json_ld, valid, errors }` with tokens resolved, without saving. |
| POST | `/validate` | Full validation, tokens counted as filled. Body `{ type, data }`. Returns `{ valid, errors: [ { field, message } ] }`. |
| GET | `/setup` | Admin only. `{ completed, preset, enabled_types, completed_at, types: [ { type, label, description, icon, group, enabled } ], presets }`. `enabled_types` is `null` until a choice is saved (then every type is on). |
| POST | `/setup` | Admin only. Body `{ preset?, enabled_types?, complete? }`. A preset without `enabled_types` applies that preset's types. `complete: true` finishes setup and returns `site_wide_added`. |
| POST | `/setup/reset` | Admin only. Shows the wizard again; schemas and enabled types are kept. |

Limits: 50 schemas per post (and site-wide), 2,000 characters per field (10,000 for long text), 100 items per list.

Saving runs a partial validation: field types are checked, but required fields may be empty so you can save drafts. Errors come back as `400` with `data.errors`. Errors inside repeatable fields use dotted paths such as `questions.1.answer`.

```bash
curl -u admin:APP_PASSWORD -X POST http://localhost:8080/wp-json/mercury-schema/v1/schemas/123 \
  -H "Content-Type: application/json" \
  -d '{"type":"Article","data":{"headline":"Test","author":"{{author_name}}"}}'
```

## Storage

```json
{
  "version": "1.0",
  "schemas": [
    {
      "id": "article-1a2b3c4d",
      "type": "Article",
      "enabled": true,
      "data": { "headline": "{{post_title}}", "author": "{{author_name}}" },
      "conditions": { "post_types": [], "categories": [], "user_roles": [], "post_ids": [] }
    }
  ]
}
```

Site-wide schemas use the same document shape in the autoloaded `mercury_schema_global` option, so they cost no extra query.

Type definitions are seeded from `assets/schema-definitions.json` into the `mercury_schema_definitions` option (not autoloaded) on activation and whenever the plugin version changes. Custom types stored in that option are kept on reseed.

### Field definitions

```json
"price": { "type": "number", "label": "Price", "path": "offers.price" }
```

- `type`: `string`, `text`, `url`, `integer`, `number`, `date`, `duration` (ISO 8601, e.g. `PT30M`), `enum`, `array`, `objects`
- `required`, `label`, `description`, `default`, `options` (for `enum`), `maxLength` (soft counter in the editor)
- `path`: dot path into the JSON-LD output. Intermediate objects get their `@type` from the definition's `nested` map, for example `"nested": { "offers": "Offer" }`.
- Type-level `label`, `description` and `icon` (a Dashicons name) drive the type picker.

An `objects` field is a repeatable group. Its value is a list of items; each item is built from `itemFields` into an object of type `itemType`:

```json
"questions": {
  "type": "objects", "label": "Questions", "path": "mainEntity", "itemLabel": "Question",
  "itemType": "Question", "itemNested": { "acceptedAnswer": "Answer" },
  "itemFields": {
    "question": { "type": "string", "required": true, "path": "name" },
    "answer":   { "type": "text",   "required": true, "path": "acceptedAnswer.text" }
  }
}
```

## Hooks

| Hook | Type | Arguments |
| --- | --- | --- |
| `mercury_schema_definitions` | filter | `array $definitions` |
| `mercury_schema_type_definition` | filter | `array $definition, string $type` |
| `mercury_schema_output_enabled` | filter | `bool $enabled` |
| `mercury_schema_should_render` | filter | `bool $render, Schema $schema, int $post_id` |
| `mercury_schema_json_ld_output` | filter | `array $json_ld, int $post_id, Schema $schema` |
| `mercury_schema_token_values` | filter | `array $values, WP_Post\|null $post` |
| `mercury_schema_tokens` | filter | `array $tokens` (name => label, for the editor's picker) |
| `mercury_schema_condition_context` | filter | `array $context, WP_Post\|null $post` |
| `mercury_schema_rest_capability` | filter | `string $capability` (post schemas; default `edit_others_posts`) |
| `mercury_schema_global_capability` | filter | `string $capability` (site-wide schemas; default `manage_options`) |
| `mercury_schema_post_types` | filter | `string[] $post_types` |
| `mercury_schema_logging_enabled` | filter | `bool $enabled` |
| `mercury_schema_schema_saved` | action | `array $schema, int $post_id` |
| `mercury_schema_schema_deleted` | action | `string $schema_id, int $post_id` |
| `mercury_schema_types_updated` | action | `string[] $enabled_types, string[]\|null $previous` |

Example: add a `Course` type without touching the plugin.

```php
add_filter('mercury_schema_definitions', function ($types) {
    $types['Course'] = [
        'label'       => 'Course',
        'description' => 'An online or in-person course.',
        'icon'        => 'welcome-learn-more',
        'nested'      => ['provider' => 'Organization'],
        'fields'      => [
            'name'        => ['type' => 'string', 'required' => true, 'label' => 'Course name', 'default' => '{{post_title}}'],
            'description' => ['type' => 'text', 'required' => true, 'label' => 'Description', 'default' => '{{post_excerpt}}'],
            'provider'    => ['type' => 'string', 'label' => 'Provider', 'path' => 'provider.name', 'default' => '{{site_name}}'],
        ],
    ];
    return $types;
});
```

## Compatibility

Tested on WordPress 7.1 with Yoast SEO 28.6, Elementor 4.3 and WooCommerce 11.1 active together. Results:

- Activates, deactivates and reactivates cleanly. Schema data and settings survive.
- The metabox works in the block editor, the classic editor and on WooCommerce product screens.
- No PHP notices, warnings or JavaScript errors from the plugin.
- Our JSON-LD prints as separate tags next to Yoast's `@graph` and is unaffected by Elementor pages.

**Duplicate types.** Mercury Schema only prints what you add, but other plugins print their own. Yoast always adds an `Article` to posts, and WooCommerce adds a `Product` on classic themes. Pick one source per type. To turn off theirs:

```php
add_filter('wpseo_schema_needs_article', '__return_false');              // Yoast Article
add_filter('woocommerce_structured_data_product', '__return_empty_array'); // WooCommerce Product
```

To turn off all of ours on a page, return `false` from `mercury_schema_output_enabled`.

## Releasing

Releases are automatic. To ship a new version:

1. Bump the version in `mercury-schema.php` (both the `Version:` header and `MERCURY_SCHEMA_VERSION`) and the `Stable tag:` in `readme.txt`.
2. Add a `= X.Y.Z =` section to the changelog in `readme.txt`.
3. Push to `main`.

`.github/workflows/release.yml` then checks that the three version numbers match, runs the unit tests, builds the zip with `bin/build.php`, and publishes GitHub Release `vX.Y.Z` with the changelog as notes. Pushes that don't change the version publish nothing. The download button links to `releases/latest/download/Mercury-Schema.zip`, which GitHub always resolves to the newest release. The zip's inner folder is `mercury-schema`, the plugin's WordPress slug.

## Development

```bash
docker compose up -d                                   # WordPress at http://localhost:8080
docker compose run --rm tests composer install
docker compose run --rm tests composer test            # unit tests
docker compose run --rm tests composer test:integration
docker compose run --rm cli sh wp-content/plugins/mercury-schema/bin/dev-setup.sh   # seed site (admin/admin, local only)
```

Other WordPress/PHP versions (the test library is matched to the image's core automatically):

```bash
WP_IMAGE=wordpress:6.0-php8.0-apache docker compose build tests
docker compose run --rm tests sh bin/test-all.sh
```

Verified: WordPress 6.0 on PHP 8.0, and WordPress 7.1 on PHP 8.2, 8.3 and 8.4.

Layout:

```
src/Core       pure PHP: Schema, SchemaType, Validator, ConditionEvaluator
src/Frontend   SchemaRegistry (definitions), SchemaOutput (JSON-LD rendering), Generators (breadcrumbs, navigation)
src/API        REST routes, schema and setup controllers, hook names
src/Admin      menu and pages (Pages), metabox and assets, settings form, brand mark
src/Helpers    DataMapper (tokens), Sanitizer, PostMetaStore, GlobalStore, SetupState, Migrator, Logger
src/Integrations  WooCommerce tokens; Elementor editor button (each loaded only when that plugin is active)
admin/         vanilla JS/CSS: editor-ui (schema editor), setup (wizard and Schema Types)
```

`php bin/build.php` makes the release zip. It strips indentation and comment-only lines from the JS and CSS (keeping line breaks, so behaviour cannot change); the editor JS ships at about 27 KB.

## License

GPLv2 or later.
