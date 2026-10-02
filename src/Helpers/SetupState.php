<?php
/**
 * Setup-wizard state and the list of enabled schema types.
 *
 * @package MercurySchema
 */

namespace MercurySchema\Helpers;

class SetupState
{
    public const COMPLETE = 'mercury_schema_setup_complete';
    public const PRESET = 'mercury_schema_preset';
    public const ENABLED_TYPES = 'mercury_schema_enabled_types';
    public const COMPLETED_AT = 'mercury_schema_onboarding_date';
    public const REDIRECT = 'mercury_schema_activation_redirect';

    public const PRESETS = ['basic', 'smart', 'custom'];

    /** Types each preset switches on (Custom starts from Smart). */
    public const PRESET_TYPES = [
        'basic' => ['Organization', 'WebSite', 'WebPage', 'BreadcrumbList', 'Article'],
        'smart' => [
            'Organization', 'WebSite', 'WebPage', 'BreadcrumbList', 'Article', 'Person', 'BlogPosting',
            'FAQPage', 'HowTo', 'VideoObject', 'Product', 'Review', 'LocalBusiness',
        ],
    ];

    /**
     * Site-wide schemas the wizard creates when their type is enabled, so the
     * foundations work with zero configuration. Type => display rules.
     */
    public const AUTO_SITE_WIDE = [
        'Organization'   => ['locations' => ['front_page']],
        'WebSite'        => ['locations' => ['front_page']],
        'WebPage'        => ['locations' => ['singular']],
        'BreadcrumbList' => ['locations' => ['singular', 'archive']],
        'Article'        => ['locations' => ['singular'], 'post_types' => ['post']],
    ];

    public static function isComplete(): bool
    {
        return (bool) get_option(self::COMPLETE, false);
    }

    /**
     * Enabled type names, or null when setup has never saved a choice
     * (upgraded sites): then every type counts as enabled.
     *
     * @return string[]|null
     */
    public static function enabledTypes(): ?array
    {
        $types = get_option(self::ENABLED_TYPES, null);
        return is_array($types) ? array_values(array_filter($types, 'is_string')) : null;
    }

    /**
     * @param string[] $types
     */
    public static function saveEnabledTypes(array $types): void
    {
        update_option(self::ENABLED_TYPES, array_values(array_unique($types)), true);
    }

    public static function status(): array
    {
        return [
            'completed'     => self::isComplete(),
            'preset'        => (string) get_option(self::PRESET, ''),
            'enabled_types' => self::enabledTypes(),
            'completed_at'  => (string) get_option(self::COMPLETED_AT, ''),
        ];
    }

    public static function reset(): void
    {
        delete_option(self::COMPLETE);
        delete_option(self::PRESET);
        delete_option(self::COMPLETED_AT);
        // Enabled types are kept: resetting the wizard should not stop schemas printing.
    }
}
