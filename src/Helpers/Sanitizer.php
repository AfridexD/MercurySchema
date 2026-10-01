<?php
/**
 * Sanitizes schema input before it is stored.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Helpers;

use UnlimitedSchema\Core\ConditionEvaluator;
use UnlimitedSchema\Core\SchemaType;

class Sanitizer
{
    /**
     * Sanitize field data for a type. Unknown fields are dropped.
     */
    public static function data(SchemaType $type, array $data): array
    {
        $clean = [];
        foreach ($data as $key => $value) {
            $field = $type->getField((string) $key);
            if ($field === null) {
                continue;
            }
            $clean[(string) $key] = self::value($value, $field['type'] ?? 'string');
        }
        return $clean;
    }

    public static function conditions(array $conditions): array
    {
        $clean = ConditionEvaluator::normalize($conditions);
        $clean['post_types'] = array_values(array_filter(array_map('sanitize_key', $clean['post_types'])));
        $clean['user_roles'] = array_values(array_filter(array_map('sanitize_key', $clean['user_roles'])));
        $clean['categories'] = array_values(array_filter(array_map('sanitize_title', $clean['categories'])));
        return $clean;
    }

    public static function value($value, string $type)
    {
        if ($type === 'array') {
            $items = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value);
            $items = array_map(static fn($v) => is_scalar($v) ? self::value((string) $v, 'string') : '', $items);
            return array_values(array_filter($items, static fn($v) => $v !== ''));
        }

        if (!is_scalar($value)) {
            return '';
        }
        $value = (string) $value;

        switch ($type) {
            case 'text':
                return sanitize_textarea_field($value);
            case 'url':
                // Keep tokens intact; esc_url_raw would mangle the braces.
                return DataMapper::hasToken($value) ? sanitize_text_field($value) : esc_url_raw($value, ['http', 'https']);
            default:
                return sanitize_text_field($value);
        }
    }
}
