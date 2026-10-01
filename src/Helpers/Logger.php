<?php
/**
 * Debug logging to the PHP error log. Silent unless WP_DEBUG or the
 * plugin's debug setting is on.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Helpers;

use UnlimitedSchema\Admin\Settings;
use UnlimitedSchema\API\Hooks;

class Logger
{
    public static function enabled(): bool
    {
        $enabled = (defined('WP_DEBUG') && WP_DEBUG) || Settings::get('debug');
        return (bool) apply_filters(Hooks::LOGGING_ENABLED, $enabled);
    }

    public static function log(string $message, array $context = []): void
    {
        if (!self::enabled()) {
            return;
        }
        $line = '[UnlimitedSchema] ' . $message;
        if ($context) {
            $line .= ' ' . wp_json_encode($context);
        }
        error_log($line); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }
}
