# Mercury Schema vs. other schema plugins

Measured on 2026-10-02 from each plugin's current release on WordPress.org (`latest-stable.zip`). Sizes are actual file bytes, not disk blocks.

| Plugin | Version | Zip | Unpacked | Files | PHP files | PHP lines |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| **Mercury Schema** | 1.1.0 | **52 KB** | **163 KB** | **27** | 21 | **2,259** |
| WP SEO Structured Data Schema | latest | 162 KB | 582 KB | 71 | 14 | 6,620 |
| All In One Schema Rich Snippets | 1.7.9 | 592 KB | 1,357 KB | 116 | 19 | 8,612 |
| Schema (by Hesham) | 1.7.9.6 | 1,476 KB | 3,849 KB | 153 | 72 | 16,315 |
| Schema & Structured Data for WP & AMP | 1.67 | 2,015 KB | 7,895 KB | 347 | 83 | 89,204 |

Mercury Schema's zip is 3–39× smaller than these, its unpacked size 4–48× smaller, and it has 3–39× less PHP. (Version 1.0.0 was 33 KB zipped; 1.1.0 added site-wide schemas, four types, repeatable fields, live preview, WooCommerce values and a redesigned editor for 19 KB.)

## Runtime cost

Measured for Mercury Schema on WordPress 7.1 with a post carrying an Article and a Product schema:

- **Schema data:** 0 extra queries. Everything lives in one `_mercury_schema_data` meta row, which WordPress has already loaded with the post.
- **Type definitions:** 1 query (a non-autoloaded option), only on singular pages that have an enabled schema.
- **Dynamic values:** `{{author_name}}` and `{{site_logo}}` make WordPress load the author and logo if the theme hasn't already. Terms and author roles are only loaded when a schema has category or role conditions.
- **Render time:** 2.4 ms cold (including its queries), 0.3 ms warm. The brief's budget is 50 ms. Loading the plugin's class files is extra and depends on the server: it took 50 ms over a slow Docker-on-Windows file mount, and is negligible with OPcache.
- **Front-end assets:** none. No CSS, no JavaScript, no external requests.

Competitor query counts were not measured here. To compare fairly, install each plugin alone on the same site, add equivalent schema, and count queries with Query Monitor.

## Design differences

| | Mercury Schema |
| --- | --- |
| Schema definitions | One JSON file, seeded into the database, extendable by filter. No PHP edits to add a type. |
| Storage | One JSON meta entry per post; site-wide schemas in one autoloaded option. |
| Admin UI | Vanilla JS over a REST API. No build step, no jQuery, no React. Editor JS ships at 27 KB. |
| Page builders | No builder modules. Output is in the page head, independent of how content is built. |
| WooCommerce | Product price, currency, stock, SKU and rating read from the product through the plugin's own token filter. No WooCommerce module is loaded when WooCommerce is absent. |
| Invalid schema | Never printed. The editor shows exactly why ("Incomplete: Thumbnail URL is required"). |
| Extensibility | 14 documented filters and actions; a public REST API. |
| Tests | 59 unit tests (no WordPress needed) + 32 integration tests, run on WordPress 6.0–7.1 and PHP 8.0–8.4. |

## Honest trade-offs

- **Fewer types out of the box.** Eleven types against dozens in the larger plugins. More can be added with a filter, but not through the UI yet.
- **No breadcrumbs or automatic site graph.** SEO suites generate BreadcrumbList and a linked `@graph` automatically; Mercury Schema prints only the schemas you add.
- **Authors can't edit schema by default.** Editors and administrators can; site-wide schemas are administrator-only. Both are filterable.

## How to reproduce

```bash
docker compose run --rm tests php bin/build.php
docker run --rm -v "$PWD/bin:/s" -v "$PWD/build:/build" alpine:3.20 sh /s/measure-competitors.sh
```

The script downloads each plugin's `latest-stable.zip`, unzips it, and counts files, bytes and PHP lines, then measures our release zip the same way.
