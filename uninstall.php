<?php
/**
 * Removes plugin options on uninstall. Per-post schema data
 * (_mercury_schema_data) is kept so reinstalling restores it.
 *
 * @package MercurySchema
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('mercury_schema_settings');
delete_option('mercury_schema_definitions');
