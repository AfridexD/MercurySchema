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
    /** Size limits: generous for real content, small enough to keep pages and rows lean. */
    public const MAX_TEXT = 10000;   // "text" fields, e.g. FAQ answers
    public const MAX_STRING = 2000;  // every other scalar field
    public const MAX_ITEMS = 100;    // entries in an "array" or "objects" field

    /** ISO 8601 duration, e.g. PT1H30M or P1D. */
    private const DURATION_PATTERN = '/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+(\.\d+)?S)?)?$/';
    private const DATE_PATTERN ='/^\d{4}-\d{2}-\d{2}([T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/';

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

        return self::result($this->checkFields($type->getFields(), $schema->getData(), $partial, ''));
    }

    /**
     * Check $data against $fields. Recurses into "objects" groups; their
     * errors use dotted paths such as "questions.0.answer".
     */
    private function checkFields(array $fields, array $data, bool $partial, string $prefix): array
    {
        $errors = [];

        if (!$partial) {
            foreach ($fields as $key => $field) {
                if (!empty($field['required']) && (!array_key_exists($key, $data) || SchemaType::isEmpty($data[$key]))) {
                    $errors[] = ['field' => $prefix . $key, 'message' => "Required field missing: $key"];
                }
            }
        }

        foreach ($data as $key => $value) {
            $key = (string) $key;
            $field = $fields[$key] ?? null;
            if ($field === null) {
                $errors[] = ['field' => $prefix . $key, 'message' => "Unknown field: $key"];
                continue;
            }
            if (SchemaType::isEmpty($value)) {
                continue;
            }

            $expected = $field['type'] ?? 'string';
            $tooLong = self::tooLong($value, $expected);
            if ($tooLong !== null) {
                $errors[] = ['field' => $prefix . $key, 'message' => "Too long: '$key' $tooLong"];
                continue;
            }
            if ($expected === 'objects') {
                if (!is_array($value) || array_values($value) !== $value) {
                    $errors[] = ['field' => $prefix . $key, 'message' => "Invalid value for '$key'. Expected: a list of items"];
                    continue;
                }
                foreach ($value as $i => $item) {
                    if (!is_array($item)) {
                        $errors[] = ['field' => "$prefix$key.$i", 'message' => "Invalid item in '$key'"];
                    } elseif (!SchemaType::isEmpty($item)) {
                        $errors = array_merge($errors, $this->checkFields((array) ($field['itemFields'] ?? []), $item, $partial, "$prefix$key.$i."));
                    }
                }
                continue;
            }

            if (DataMapper::hasToken($value)) {
                continue;
            }
            if (!$this->checkType($value, $expected, $field)) {
                $errors[] = ['field' => $prefix . $key, 'message' => "Invalid value for '$key'. Expected: $expected"];
            }
        }

        return $errors;
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
            case 'duration':
                return is_string($value) && preg_match(self::DURATION_PATTERN, $value) === 1;
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

    /**
     * A description of the limit exceeded, or null when within limits.
     */
    private static function tooLong($value, string $type): ?string
    {
        if (is_array($value)) {
            if (count($value) > self::MAX_ITEMS) {
                return 'has more than ' . self::MAX_ITEMS . ' items';
            }
            if ($type === 'array') {
                foreach ($value as $item) {
                    if (is_string($item) && mb_strlen($item) > self::MAX_STRING) {
                        return 'has an item longer than ' . self::MAX_STRING . ' characters';
                    }
                }
            }
            return null; // Items of "objects" fields are checked field by field.
        }
        $max = $type === 'text' || $type === 'array' ? self::MAX_TEXT : self::MAX_STRING;
        return is_string($value) && mb_strlen($value) > $max ? "is longer than $max characters" : null;
    }

    private static function result(array $errors): array
    {
        return ['valid' => empty($errors), 'errors' => $errors];
    }
}
