<?php
/**
 * Removes plugin settings on uninstall. Schema data (post meta
 * _mercury_schema_data and site-wide schemas) is kept, so reinstalling
 * restores it.
 *
 * @package MercurySchema
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

foreach ([
    'mercury_schema_settings',
    'mercury_schema_definitions',
    'mercury_schema_setup_complete',
    'mercury_schema_preset',
    'mercury_schema_enabled_types',
    'mercury_schema_onboarding_date',
] as $mercury_schema_option) {
    delete_option($mercury_schema_option);
}
delete_transient('mercury_schema_activation_redirect');
