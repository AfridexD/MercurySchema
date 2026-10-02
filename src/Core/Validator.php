<?php
/**
 * Validates schema field data against its type definition.
 *
 * Pure PHP: no WordPress calls.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Core;

use UnlimitedSchema\Helpers\DataMapper;

class Validator
{
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/';

    /** @var array<string, SchemaType> */
    private array $types;

    /**
     * @param array<string, SchemaType> $types Keyed by type name.
     */
    public function __construct(array $types)
    {
        $this->types = $types;
    }

    /**
     * Validate a schema.
     *
     * Values containing dynamic tokens ({{post_title}}) skip type checks; they
     * are checked again after resolution, at render time.
     *
     * @param bool $partial Skip required-field checks (used while saving drafts).
     * @return array{valid: bool, errors: array<int, array{field: string, message: string}>}
     */
    public function validate(Schema $schema, bool $partial = false): array
    {
        $type = $this->types[$schema->getType()] ?? null;
        if ($type === null) {
            return self::result([['field' => '', 'message' => 'Unknown schema type: ' . $schema->getType()]]);
        }

        $data = $schema->getData();
        $errors = [];

        if (!$partial) {
            foreach ($type->getRequiredFields() as $key) {
                if (!array_key_exists($key, $data) || SchemaType::isEmpty($data[$key])) {
                    $errors[] = ['field' => $key, 'message' => "Required field missing: $key"];
                }
            }
        }

        foreach ($data as $key => $value) {
            $field = $type->getField((string) $key);
            if ($field === null) {
                $errors[] = ['field' => (string) $key, 'message' => "Unknown field: $key"];
                continue;
            }
            if (SchemaType::isEmpty($value) || DataMapper::hasToken($value)) {
                continue;
            }
            $expected = $field['type'] ?? 'string';
            if (!$this->checkType($value, $expected, $field)) {
                $errors[] = ['field' => (string) $key, 'message' => "Invalid value for '$key'. Expected: $expected"];
            }
        }

        return self::result($errors);
    }

    private function checkType($value, string $type, array $field): bool
    {
        switch ($type) {
            case 'string':
            case 'text':
                return is_string($value) || is_int($value) || is_float($value);
            case 'url':
                return is_string($value)
                    && preg_match('#^https?://#i', $value) === 1
                    && filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'integer':
                return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1);
            case 'number':
                return (is_int($value) || is_float($value) || is_string($value)) && is_numeric($value);
            case 'date':
                return is_string($value) && preg_match(self::DATE_PATTERN, $value) === 1;
            case 'enum':
                return in_array($value, $field['options'] ?? [], true);
            case 'array':
                if (is_string($value)) {
                    return true; // Newline-separated list, split at render time.
                }
                return is_array($value) && count(array_filter($value, static fn($v) => !is_scalar($v))) === 0;
            default:
                return true;
        }
    }

    private static function result(array $errors): array
    {
        return ['valid' => empty($errors), 'errors' => $errors];
    }
}
