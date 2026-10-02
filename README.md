# UnlimitedSchema

Lightweight, conflict-free JSON-LD schema markup for WordPress.

- **Small.** About 20 PHP files, no runtime dependencies, no build step.
- **One meta row per post.** All of a post's schemas live in a single `_unlimited_schema_data` JSON entry.
- **REST-first.** The admin UI is a thin vanilla-JS client of the REST API. Anything the UI does, you can script.
- **Filterable.** Definitions, conditions, token values and final JSON-LD all pass through filters.
- **Testable.** `src/Core` is plain PHP with no WordPress calls and is covered by unit tests.

Requires PHP 8.0+ and WordPress 6.0+.

## Installation

1. Copy this folder to `wp-content/plugins/unlimited-schema`.
2. Activate **UnlimitedSchema** in *Plugins*.
3. Optional: choose post types and output location in *Settings → UnlimitedSchema*.

## Usage

Open a post. In the **Schema Markup** box, pick a type and click **Add schema**. The new schema is pre-filled with dynamic values such as `{{post_title}}`. Edit fields, click **Validate**, then **Save**. The schema renders as a `<script type="application/ld+json">` tag on the post's page.

Built-in types: Article, Product, Event, Organization, Person, LocalBusiness, Review.

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

Add your own with the `unlimited_schema_token_values` filter.

### Display conditions

Each schema has optional conditions: `post_types`, `categories` (slugs or IDs), `user_roles` (roles of the post's **author**), and `post_ids`. An empty list means no restriction; every non-empty list must match.

### Rendering rules

A schema renders only when it is enabled, its type is known, its conditions match, and it passes full validation after tokens are resolved. Invalid schemas are skipped silently and logged when `WP_DEBUG` or the debug setting is on.

## REST API

Namespace `unlimited-schema/v1`. Every endpoint requires the `manage_options` capability (filterable), and per-post endpoints also require `edit_post` on that post. Use cookie auth with an `X-WP-Nonce: wp_rest` nonce, or Application Passwords.

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/schemas` | List types: `{ types: [ { type, label, fields, nested } ] }` |
| GET | `/schema-types` | Types keyed by name: `{ Article: { label, fields, nested } }` |
| GET | `/schemas/{post_id}` | `{ post_id, version, schemas: [...] }` |
| POST | `/schemas/{post_id}` | Create. Body `{ type, data?, conditions?, enabled? }`. Returns `201 { success, schema }`. Omitted or empty `data` gets the type's defaults. |
| PUT | `/schemas/{post_id}/{schema_id}` | Update. Any of `type`, `data`, `conditions`, `enabled`. Returns `{ success, schema }`. |
| DELETE | `/schemas/{post_id}/{schema_id}` | Returns `{ success, deleted }`. |
| POST | `/validate` | Full validation. Body `{ type, data }`. Returns `{ valid, errors: [ { field, message } ] }`. |

Saving runs a partial validation: field types are checked, but required fields may be empty so you can save drafts. Errors come back as `400` with `data.errors`.

```bash
curl -u admin:APP_PASSWORD -X POST http://localhost:8080/wp-json/unlimited-schema/v1/schemas/123 \
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

Type definitions are seeded from `assets/schema-definitions.json` into the `unlimited_schema_definitions` option (not autoloaded) on activation and whenever the plugin version changes. Custom types stored in that option are kept on reseed.

### Field definitions

```json
"price": { "type": "number", "label": "Price", "path": "offers.price" }
```

- `type`: `string`, `text`, `url`, `integer`, `number`, `date`, `enum`, `array`
- `required`, `label`, `description`, `default`, `options` (for `enum`)
- `path`: dot path into the JSON-LD output. Intermediate objects get their `@type` from the definition's `nested` map, for example `"nested": { "offers": "Offer" }`.

## Hooks

| Hook | Type | Arguments |
| --- | --- | --- |
| `unlimited_schema_definitions` | filter | `array $definitions` |
| `unlimited_schema_type_definition` | filter | `array $definition, string $type` |
| `unlimited_schema_output_enabled` | filter | `bool $enabled` |
| `unlimited_schema_should_render` | filter | `bool $render, Schema $schema, int $post_id` |
| `unlimited_schema_json_ld_output` | filter | `array $json_ld, int $post_id, Schema $schema` |
| `unlimited_schema_token_values` | filter | `array $values, WP_Post $post` |
| `unlimited_schema_condition_context` | filter | `array $context, WP_Post $post` |
| `unlimited_schema_rest_capability` | filter | `string $capability` |
| `unlimited_schema_post_types` | filter | `string[] $post_types` |
| `unlimited_schema_logging_enabled` | filter | `bool $enabled` |
| `unlimited_schema_schema_saved` | action | `array $schema, int $post_id` |
| `unlimited_schema_schema_deleted` | action | `string $schema_id, int $post_id` |

Example: add a `Recipe` type without touching the plugin.

```php
add_filter('unlimited_schema_definitions', function ($types) {
    $types['Recipe'] = [
        'label'  => 'Recipe',
        'fields' => [
            'name'  => ['type' => 'string', 'required' => true, 'default' => '{{post_title}}'],
            'image' => ['type' => 'url', 'default' => '{{featured_image}}'],
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

**Duplicate types.** UnlimitedSchema only prints what you add, but other plugins print their own. Yoast always adds an `Article` to posts, and WooCommerce adds a `Product` on classic themes. Pick one source per type. To turn off theirs:

```php
add_filter('wpseo_schema_needs_article', '__return_false');              // Yoast Article
add_filter('woocommerce_structured_data_product', '__return_empty_array'); // WooCommerce Product
```

To turn off all of ours on a page, return `false` from `unlimited_schema_output_enabled`.

## Development

```bash
docker compose up -d                                   # WordPress at http://localhost:8080
docker compose run --rm tests composer install
docker compose run --rm tests composer test            # unit tests
docker compose run --rm tests composer test:integration
```

Layout:

```
src/Core       pure PHP: Schema, SchemaType, Validator, ConditionEvaluator
src/Frontend   SchemaRegistry (definitions), SchemaOutput (JSON-LD rendering)
src/API        REST routes, controller, hook names
src/Admin      metabox, settings page
src/Helpers    DataMapper (tokens), Sanitizer, PostMetaStore, Logger
admin/         vanilla JS/CSS for the metabox
```

## License

GPLv2 or later.
