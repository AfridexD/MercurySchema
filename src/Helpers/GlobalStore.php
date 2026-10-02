<?php
/**
 * Site-wide schemas, stored in one autoloaded option so reading them on
 * every page costs no extra query. Same document shape as PostMetaStore.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Helpers;

class GlobalStore
{
    public const OPTION = 'unlimited_schema_global';

    /**
     * @return array{version: string, schemas: array<int, array>}
     */
    public function get(): array
    {
        $doc = get_option(self::OPTION);
        if (!is_array($doc) || !is_array($doc['schemas'] ?? null)) {
            return ['version' => PostMetaStore::DOC_VERSION, 'schemas' => []];
        }
        $doc['schemas'] = array_values(array_filter($doc['schemas'], static fn($s) => is_array($s) && !empty($s['type'])));
        $doc['version'] = (string) ($doc['version'] ?? PostMetaStore::DOC_VERSION);
        return $doc;
    }

    public function save(array $schemas): bool
    {
        $doc = ['version' => PostMetaStore::DOC_VERSION, 'schemas' => array_values($schemas)];
        // update_option() returns false when nothing changed, which is fine.
        return update_option(self::OPTION, $doc, true) || get_option(self::OPTION) === $doc;
    }
}
