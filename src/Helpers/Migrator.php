<?php
/**
 * One-time migration from the plugin's previous name (UnlimitedSchema).
 * Runs on activation; safe to run repeatedly.
 *
 * @package MercurySchema
 */

namespace MercurySchema\Helpers;

use MercurySchema\Admin\Settings;

class Migrator
{
    public const LEGACY_PLUGIN = 'unlimited-schema/unlimited-schema.php';
    private const LEGACY_META_KEY = '_unlimited_schema_data';

    /** Legacy option => [new option, autoload]. */
    private const OPTIONS = [
        'unlimited_schema_settings' => [Settings::OPTION, true],
        'unlimited_schema_global'   => [GlobalStore::OPTION, true],
    ];

    /**
     * @return array{options: int, meta_rows: int, deactivated_legacy: bool}
     */
    public static function run(): array
    {
        global $wpdb;
        $report = ['options' => 0, 'meta_rows' => 0, 'deactivated_legacy' => false];

        // Two copies active at once would print every schema twice.
        if (function_exists('is_plugin_active') && is_plugin_active(self::LEGACY_PLUGIN)) {
            deactivate_plugins(self::LEGACY_PLUGIN, true);
            $report['deactivated_legacy'] = true;
        }

        foreach (self::OPTIONS as $legacy => [$option, $autoload]) {
            $value = get_option($legacy, null);
            if ($value === null) {
                continue;
            }
            if (get_option($option, null) === null) {
                add_option($option, $value, '', $autoload);
                $report['options']++;
            }
            delete_option($legacy);
        }
        // Definitions are re-seeded from the bundled file; the old copy is not needed.
        delete_option('unlimited_schema_definitions');

        // Rename post schema data in a single query, skipping posts that already have new data.
        $report['meta_rows'] = (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} AS legacy
             LEFT JOIN {$wpdb->postmeta} AS current
               ON current.post_id = legacy.post_id AND current.meta_key = %s
             SET legacy.meta_key = %s
             WHERE legacy.meta_key = %s AND current.meta_id IS NULL",
            PostMetaStore::META_KEY,
            PostMetaStore::META_KEY,
            self::LEGACY_META_KEY
        ));
        if ($report['meta_rows'] > 0) {
            wp_cache_flush();
        }

        return $report;
    }
}
