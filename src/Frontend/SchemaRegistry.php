<?php
/**
 * Lazy-loading registry of schema type definitions.
 *
 * Definitions live in the database (a non-autoloaded option) and are seeded
 * from assets/schema-definitions.json on activation or when the plugin
 * version changes. Custom types stored in the option survive reseeding.
 * Nothing is read until a type is actually needed.
 *
 * @package MercurySchema
 */

namespace MercurySchema\Frontend;

use MercurySchema\API\Hooks;
use MercurySchema\Core\SchemaType;
use MercurySchema\Core\Validator;
use MercurySchema\Helpers\Logger;
use MercurySchema\Helpers\SetupState;

class SchemaRegistry
{
    public const OPTION = 'mercury_schema_definitions';

    /** @var array<string, SchemaType>|null */
    private ?array $types = null;

    /**
     * @return array<string, SchemaType>
     */
    public function all(): array
    {
        if ($this->types === null) {
            $this->types = [];
            $definitions = apply_filters(Hooks::DEFINITIONS, $this->loadDefinitions());
            foreach ((array) $definitions as $name => $definition) {
                $definition = apply_filters(Hooks::TYPE_DEFINITION, $definition, $name);
                if (is_string($name) && is_array($definition)) {
                    $this->types[$name] = new SchemaType($name, $definition);
                }
            }
        }
        return $this->types;
    }

    public function get(string $name): ?SchemaType
    {
        return $this->all()[$name] ?? null;
    }

    public function validator(): Validator
    {
        return new Validator($this->all());
    }

    /**
     * Whether a type is switched on in Schema Types. Before the setup wizard
     * has saved a choice, every type is on.
     */
    public function isEnabled(string $name): bool
    {
        $enabled = SetupState::enabledTypes();
        return $enabled === null || in_array($name, $enabled, true);
    }

    /**
     * Copy the bundled definitions into the database, keeping any custom
     * types that are not part of the bundled file.
     */
    public static function seed(): array
    {
        $file = MERCURY_SCHEMA_PATH . 'assets/schema-definitions.json';
        $bundled = is_readable($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($bundled) || !is_array($bundled['types'] ?? null)) {
            Logger::log('Could not read bundled schema definitions.', ['file' => $file]);
            return [];
        }

        $stored = get_option(self::OPTION);
        $existing = is_array($stored) && is_array($stored['types'] ?? null) ? $stored['types'] : [];
        $types = $bundled['types'] + $existing; // Bundled order first; custom types kept.

        update_option(self::OPTION, [
            'plugin_version' => MERCURY_SCHEMA_VERSION,
            'types'          => $types,
        ], false);

        return $types;
    }

    private function loadDefinitions(): array
    {
        $stored = get_option(self::OPTION);
        if (!is_array($stored) || ($stored['plugin_version'] ?? '') !== MERCURY_SCHEMA_VERSION) {
            return self::seed();
        }
        return is_array($stored['types'] ?? null) ? $stored['types'] : [];
    }
}
