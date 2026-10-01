<?php
/**
 * Resolves dynamic tokens such as {{post_title}} inside schema field values.
 *
 * Pure PHP: the token values are supplied by the caller (see
 * Frontend\SchemaOutput::tokenValues), so this class needs no WordPress.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Helpers;

class DataMapper
{
    public const TOKEN_PATTERN = '/\{\{\s*([a-z0-9_]+)\s*\}\}/';

    /** Built-in tokens; extend with the unlimited_schema_token_values filter. */
    public const TOKENS = [
        'post_title',
        'post_excerpt',
        'post_url',
        'post_date',
        'post_modified',
        'featured_image',
        'author_name',
        'author_url',
        'site_name',
        'site_description',
        'site_logo',
        'home_url',
    ];

    public static function hasToken($value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::hasToken($item)) {
                    return true;
                }
            }
            return false;
        }
        return is_string($value) && preg_match(self::TOKEN_PATTERN, $value) === 1;
    }

    /**
     * Replace tokens in every string value. Unknown tokens become empty strings.
     *
     * @param array<string, string> $values Token name => value.
     */
    public static function resolve(array $data, array $values): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::resolve($value, $values);
            } elseif (is_string($value) && strpos($value, '{{') !== false) {
                $data[$key] = trim(preg_replace_callback(
                    self::TOKEN_PATTERN,
                    static fn($m) => isset($values[$m[1]]) ? (string) $values[$m[1]] : '',
                    $value
                ));
            }
        }
        return $data;
    }
}
