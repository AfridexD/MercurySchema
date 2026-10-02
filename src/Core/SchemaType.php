<?php
/**
 * One schema type definition (e.g. Article) and the logic to turn
 * flat field data into nested JSON-LD.
 *
 * Field config keys: type, required, label, description, default, options,
 * path, maxLength (a soft UI hint).
 * "path" is a dot path into the JSON-LD (e.g. "offers.price"); intermediate
 * objects get their @type from the definition's "nested" map.
 * An "objects" field is a repeatable group: its value is a list of items, each
 * built from "itemFields" into an object of type "itemType" (with its own
 * "itemNested" map), e.g. FAQ questions.
 *
 * Pure PHP: no WordPress calls.
 *
 * @package MercurySchema
 */

namespace MercurySchema\Core;

class SchemaType
{
    public const FIELD_TYPES = ['string', 'text', 'url', 'integer', 'number', 'date', 'duration', 'enum', 'array', 'objects'];

    private string $name;
    private array $definition;

    public function __construct(string $name, array $definition)
    {
        $this->name = $name;
        $this->definition = $definition + ['label' => $name, 'fields' => [], 'nested' => []];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return (string) $this->definition['label'];
    }

    public function getFields(): array
    {
        return is_array($this->definition['fields']) ? $this->definition['fields'] : [];
    }

    public function hasField(string $key): bool
    {
        return isset($this->getFields()[$key]);
    }

    public function getField(string $key): ?array
    {
        return $this->getFields()[$key] ?? null;
    }

    public function getRequiredFields(): array
    {
        return array_keys(array_filter($this->getFields(), static fn($f) => !empty($f['required'])));
    }

    /**
     * Default values for every field that declares one.
     */
    public function getDefaults(): array
    {
        $defaults = [];
        foreach ($this->getFields() as $key => $field) {
            if (isset($field['default']) && $field['default'] !== '') {
                $defaults[$key] = $field['default'];
            }
        }
        return $defaults;
    }

    public function toArray(): array
    {
        return [
            'type'        => $this->name,
            'label'       => $this->getLabel(),
            'description' => (string) ($this->definition['description'] ?? ''),
            'icon'        => (string) ($this->definition['icon'] ?? ''),
            'fields'      => $this->getFields(),
            'nested'      => $this->definition['nested'],
        ];
    }

    /**
     * Build a JSON-LD array from resolved (token-free) field data.
     * Unknown fields and empty values are dropped.
     */
    public function toJsonLd(array $data): array
    {
        $out = [
            '@context' => 'https://schema.org',
            '@type'    => $this->name,
        ];
        return $this->fill($out, $this->getFields(), (array) $this->definition['nested'], $data);
    }

    public static function isEmpty($value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, static fn($v) => !self::isEmpty($v))) === 0;
        }
        return $value === null || $value === '' || $value === false;
    }

    /**
     * Write each field's value into $node at its path.
     */
    private function fill(array $node, array $fields, array $nested, array $data): array
    {
        foreach ($fields as $key => $field) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $this->castValue($data[$key], $field);
            if (self::isEmpty($value)) {
                continue;
            }
            $path = isset($field['path']) && $field['path'] !== '' ? (string) $field['path'] : (string) $key;
            self::setPath($node, explode('.', $path), $value, '', $nested);
        }
        return $node;
    }

    private function castValue($value, array $field)
    {
        switch ($field['type'] ?? 'string') {
            case 'integer':
                return is_numeric($value) ? (int) $value : $value;
            case 'number':
                return is_numeric($value) ? $value + 0 : $value;
            case 'array':
                $items = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value);
                $items = array_map(static fn($v) => is_scalar($v) ? trim((string) $v) : '', $items);
                return array_values(array_filter($items, static fn($v) => $v !== ''));
            case 'objects':
                // Repeatable group: each item becomes a typed object, e.g. a Question.
                $items = [];
                foreach (is_array($value) ? $value : [] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $object = $this->fill(
                        ['@type' => (string) ($field['itemType'] ?? 'Thing')],
                        (array) ($field['itemFields'] ?? []),
                        (array) ($field['itemNested'] ?? []),
                        $item
                    );
                    if (count($object) > 1) {
                        $items[] = $object;
                    }
                }
                return $items;
            default:
                return is_scalar($value) ? trim((string) $value) : $value;
        }
    }

    private static function setPath(array &$node, array $segments, $value, string $prefix, array $nested): void
    {
        $key = array_shift($segments);
        if (!$segments) {
            $node[$key] = $value;
            return;
        }

        $fullPath = $prefix === '' ? $key : $prefix . '.' . $key;
        if (!isset($node[$key]) || !is_array($node[$key])) {
            $node[$key] = ['@type' => $nested[$fullPath] ?? 'Thing'];
        }
        self::setPath($node[$key], $segments, $value, $fullPath, $nested);
    }
}
