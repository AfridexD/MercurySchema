<?php
/**
 * Detects other plugins that print schema, so the admin screens can warn
 * about duplicate structured data. Detection is by plugin folder, which
 * survives renamed main files and covers the Pro editions.
 *
 * @package MercurySchema
 */

namespace MercurySchema\Admin;

use MercurySchema\API\Hooks;

class OtherSchemaSources
{
    /** Plugin folder => name shown to the user. */
    public const PLUGINS = [
        'wordpress-seo'                      => 'Yoast SEO',
        'wordpress-seo-premium'              => 'Yoast SEO',
        'seo-by-rank-math'                   => 'Rank Math SEO',
        'seo-by-rank-math-pro'               => 'Rank Math SEO',
        'all-in-one-seo-pack'                => 'All in One SEO',
        'all-in-one-seo-pack-pro'            => 'All in One SEO',
        'wp-seopress'                        => 'SEOPress',
        'autodescription'                    => 'The SEO Framework',
        'slim-seo'                           => 'Slim SEO',
        'squirrly-seo'                       => 'Squirrly SEO',
        'wp-schema-pro'                      => 'Schema Pro',
        'schema-and-structured-data-for-wp'  => 'Schema & Structured Data for WP',
        'schema'                             => 'Schema',
        'all-in-one-schemaorg-rich-snippets' => 'All In One Schema Rich Snippets',
    ];

    /** Where the help guide explains how to keep one source of schema. */
    public const DOCS = AdminController::DOCS_URL . '#other-schema';

    /**
     * Names of active plugins that also print schema.
     *
     * @return string[]
     */
    public static function active(): array
    {
        $plugins = (array) get_option('active_plugins', []);
        if (is_multisite()) {
            $plugins = array_merge($plugins, array_keys((array) get_site_option('active_sitewide_plugins', [])));
        }
        $found = [];
        foreach ($plugins as $file) {
            $folder = strtok((string) $file, '/');
            if (isset(self::PLUGINS[$folder])) {
                $found[] = self::PLUGINS[$folder];
            }
        }
        return array_values(array_unique((array) apply_filters(Hooks::OTHER_SOURCES, $found)));
    }

    /** "Yoast SEO", "Yoast SEO and Rank Math SEO", "A, B and C". */
    public static function listNames(array $names): string
    {
        if (count($names) < 2) {
            return (string) reset($names);
        }
        $last = array_pop($names);
        /* translators: 1: comma-separated plugin names, 2: last plugin name */
        return sprintf(__('%1$s and %2$s', 'mercury-schema'), implode(', ', $names), $last);
    }

    /**
     * The warning's parts, or null when no other source is active.
     *
     * @return array{title: string, text: string, link: string, url: string}|null
     */
    public static function warning(): ?array
    {
        $names = self::active();
        if (!$names) {
            return null;
        }
        return [
            'title' => sprintf(
                /* translators: %s: plugin name(s), e.g. "Yoast SEO" */
                _n('%s also adds schema to your pages.', '%s also add schema to your pages.', count($names), 'mercury-schema'),
                self::listNames($names)
            ),
            'text'  => __('Search engines should get each kind of schema from one place only. Turn off schema in the other plugin, or switch off the matching types under Schema Types.', 'mercury-schema'),
            'link'  => __('How to avoid duplicate schema ↗', 'mercury-schema'),
            'url'   => self::DOCS,
        ];
    }

    /** The warning shown on Mercury Schema's own screens, or nothing. */
    public static function renderNotice(): void
    {
        $w = self::warning();
        if (!$w) {
            return;
        }
        printf(
            '<div class="ms-sources" role="note"><span class="dashicons dashicons-warning" aria-hidden="true"></span><p><strong>%s</strong> %s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p></div>',
            esc_html($w['title']),
            esc_html($w['text']),
            esc_url($w['url']),
            esc_html($w['link'])
        );
    }
}
