<?php
/**
 * Decides whether a schema's conditions match the current post.
 *
 * An empty condition list means "no restriction". All non-empty lists must match.
 * Context is a plain array built by the caller, so this class has no WordPress calls.
 *
 * Context keys:
 *   post_id     int
 *   post_type   string
 *   categories  string[]  term IDs and slugs of the post's categories
 *   user_roles  string[]  roles of the post's author
 *   locations   string[]  where the page is: front_page, singular, archive
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Core;

class ConditionEvaluator
{
    public const KEYS = ['post_types', 'categories', 'user_roles', 'post_ids', 'locations'];
    public const LOCATIONS = ['front_page', 'singular', 'archive'];

    public function evaluate(array $conditions, array $context): bool
    {
        $conditions = self::normalize($conditions);

        if ($conditions['locations'] && !self::intersects($conditions['locations'], $context['locations'] ?? [])) {
            return false;
        }
        if ($conditions['post_types'] && !in_array((string) ($context['post_type'] ?? ''), $conditions['post_types'], true)) {
            return false;
        }
        if ($conditions['post_ids'] && !in_array((int) ($context['post_id'] ?? 0), $conditions['post_ids'], true)) {
            return false;
        }
        if ($conditions['categories'] && !self::intersects($conditions['categories'], $context['categories'] ?? [])) {
            return false;
        }
        if ($conditions['user_roles'] && !self::intersects($conditions['user_roles'], $context['user_roles'] ?? [])) {
            return false;
        }
        return true;
    }

    /**
     * Coerce raw conditions into the canonical shape: every key present,
     * string lists for slugs, int list for post IDs.
     */
    public static function normalize(array $conditions): array
    {
        $out = [];
        foreach (self::KEYS as $key) {
            $list = $conditions[$key] ?? [];
            if (is_string($list)) {
                $list = explode(',', $list);
            }
            if (!is_array($list)) {
                $list = [];
            }
            $list = array_filter(array_map(static fn($v) => is_scalar($v) ? trim((string) $v) : '', $list), 'strlen');
            if ($key === 'post_ids') {
                $list = array_filter(array_map('intval', $list));
            } elseif ($key === 'locations') {
                $list = array_intersect($list, self::LOCATIONS);
            }
            $out[$key] = array_values(array_unique($list));
        }
        return $out;
    }

    private static function intersects(array $wanted, array $actual): bool
    {
        $actual = array_map('strval', $actual);
        return count(array_intersect($wanted, $actual)) > 0;
    }
}
