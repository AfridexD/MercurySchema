<?php
/**
 * Removes plugin options on uninstall. Per-post schema data
 * (_unlimited_schema_data) is kept so reinstalling restores it.
 *
 * @package UnlimitedSchema
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('unlimited_schema_settings');
delete_option('unlimited_schema_definitions');
