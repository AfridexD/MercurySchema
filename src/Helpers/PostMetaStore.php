<?php
/**
 * Reads and writes the single _mercury_schema_data meta entry.
 *
 * Every schema for a post lives in one JSON document, so a post costs one
 * meta lookup (and WordPress primes post meta in a single query anyway).
 *
 * @package MercurySchema
 */

namespace MercurySchema\Helpers;

class PostMetaStore
{
    public const META_KEY = '_mercury_schema_data';
    public const DOC_VERSION = '1.0';

    /**
     * @return array{version: string, schemas: array<int, array>}
     */
    public function get(int $postId): array
    {
        $raw = get_post_meta($postId, self::META_KEY, true);
        $doc = is_string($raw) && $raw !== '' ? json_decode($raw, true) : (is_array($raw) ? $raw : null);

        if (!is_array($doc) || !isset($doc['schemas']) || !is_array($doc['schemas'])) {
            return ['version' => self::DOC_VERSION, 'schemas' => []];
        }

        $doc['schemas'] = array_values(array_filter($doc['schemas'], static fn($s) => is_array($s) && !empty($s['type'])));
        $doc['version'] = (string) ($doc['version'] ?? self::DOC_VERSION);
        return $doc;
    }

    /**
     * Persist the document. An empty schema list removes the meta row.
     */
    public function save(int $postId, array $schemas): bool
    {
        $schemas = array_values($schemas);
        if (!$schemas) {
            delete_post_meta($postId, self::META_KEY);
            return true;
        }

        $json = wp_json_encode(
            ['version' => self::DOC_VERSION, 'schemas' => $schemas],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        if ($json === false) {
            return false;
        }

        // update_post_meta() unslashes its input; slash first so JSON escapes survive.
        $result = update_post_meta($postId, self::META_KEY, wp_slash($json));
        // false also means "value unchanged", which is a success for us.
        return $result !== false || get_post_meta($postId, self::META_KEY, true) === $json;
    }
}
