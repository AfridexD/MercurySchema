<?php
/**
 * One schema type definition (e.g. Article) and the logic to turn
 * flat field data into nested JSON-LD.
 *
 * Field config keys: type, required, label, description, default, options, path.
 * "path" is a dot path into the JSON-LD (e.g. "offers.price"); intermediate
 * objects get their @type from the definition's "nested" map.
 *
 * Pure PHP: no WordPress calls.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Core;

class SchemaType
{
    public const FIELD_TYPES = ['string', 'text', 'url', 'integer', 'number', 'date', 'enum', 'array'];

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
            'type'   => $this->name,
            'label'  => $this->getLabel(),
            'fields' => $this->getFields(),
            'nested' => $this->definition['nested'],
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

        foreach ($this->getFields() as $key => $field) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $this->castValue($data[$key], $field['type'] ?? 'string');
            if (self::isEmpty($value)) {
                continue;
            }
            $path = isset($field['path']) && $field['path'] !== '' ? (string) $field['path'] : $key;
            $this->setPath($out, explode('.', $path), $value, '');
        }

        return $out;
    }

    public static function isEmpty($value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, static fn($v) => !self::isEmpty($v))) === 0;
        }
        return $value === null || $value === '' || $value === false;
    }

    private function castValue($value, string $type)
    {
        switch ($type) {
            case 'integer':
                return is_numeric($value) ? (int) $value : $value;
            case 'number':
                return is_numeric($value) ? $value + 0 : $value;
            case 'array':
                $items = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value);
                $items = array_map(static fn($v) => is_scalar($v) ? trim((string) $v) : '', $items);
                return array_values(array_filter($items, static fn($v) => $v !== ''));
            default:
                return is_scalar($value) ? trim((string) $value) : $value;
        }
    }

    private function setPath(array &$node, array $segments, $value, string $prefix): void
    {
        $key = array_shift($segments);
        if (!$segments) {
            $node[$key] = $value;
            return;
        }

        $fullPath = $prefix === '' ? $key : $prefix . '.' . $key;
        if (!isset($node[$key]) || !is_array($node[$key])) {
            $nested = $this->definition['nested'][$fullPath] ?? 'Thing';
            $node[$key] = ['@type' => $nested];
        }
        $this->setPath($node[$key], $segments, $value, $fullPath);
    }
}
