<?php
/**
 * Builds the parts of a schema that come from the site itself rather than
 * from fields: the breadcrumb trail and the main navigation.
 *
 * Each generator receives the JSON-LD built from fields and returns it
 * completed, or null when there is nothing meaningful to output here.
 *
 * @package MercurySchema
 */

namespace MercurySchema\Frontend;

class Generators
{
    private const MAX_ITEMS = 50;

    /**
     * @param array $data Resolved field data (config fields included).
     */
    public static function apply(string $generator, array $jsonLd, array $data, ?\WP_Post $post): ?array
    {
        switch ($generator) {
            case 'breadcrumbs':
                $items = self::breadcrumbItems($data, $post);
                if (count($items) < 2) {
                    return null; // A trail of just "Home" says nothing.
                }
                $jsonLd['itemListElement'] = self::listItems($items, 'ListItem', 'item');
                return $jsonLd;
            case 'navigation':
                $items = self::navigationItems((string) ($data['menuLocation'] ?? ''));
                if (!$items) {
                    return null;
                }
                return [
                    '@context'        => $jsonLd['@context'] ?? 'https://schema.org',
                    '@type'           => 'ItemList',
                    'itemListElement' => self::listItems($items, 'SiteNavigationElement', 'url'),
                ];
            default:
                return $jsonLd;
        }
    }

    /**
     * @param array<int, array{0: string, 1: string}> $items [name, url] pairs.
     */
    private static function listItems(array $items, string $type, string $urlKey): array
    {
        $out = [];
        foreach (array_slice($items, 0, self::MAX_ITEMS) as $i => [$name, $url]) {
            $out[] = ['@type' => $type, 'position' => $i + 1, 'name' => $name, $urlKey => $url];
        }
        return $out;
    }

    /**
     * Home, then the hierarchy above the current page, then the page itself.
     */
    private static function breadcrumbItems(array $data, ?\WP_Post $post): array
    {
        $home = trim((string) ($data['homeLabel'] ?? '')) ?: __('Home', 'mercury-schema');
        $items = [[$home, home_url('/')]];

        if ($post) {
            if ((int) get_option('page_on_front') === (int) $post->ID) {
                return $items;
            }
            if (is_post_type_hierarchical($post->post_type)) {
                foreach (array_reverse(get_post_ancestors($post)) as $ancestor) {
                    $items[] = [self::plain(get_the_title($ancestor)), (string) get_permalink($ancestor)];
                }
            } elseif ($post->post_type === 'post') {
                $categories = get_the_category($post->ID);
                if ($categories) {
                    $items = array_merge($items, self::termTrail($categories[0]));
                }
            } else {
                $object = get_post_type_object($post->post_type);
                $archive = get_post_type_archive_link($post->post_type);
                if ($object && $archive) {
                    $items[] = [self::plain($object->labels->name), $archive];
                }
            }
            $items[] = [self::plain(get_the_title($post)), (string) get_permalink($post)];
            return $items;
        }

        // Archive-type pages (only reached when rendering, never in previews).
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $items = array_merge($items, self::termTrail($term));
            }
        } elseif (is_post_type_archive()) {
            $items[] = [self::plain((string) post_type_archive_title('', false)), (string) get_post_type_archive_link((string) get_query_var('post_type'))];
        } elseif (is_author()) {
            $author = get_queried_object();
            if ($author instanceof \WP_User) {
                $items[] = [self::plain($author->display_name), (string) get_author_posts_url($author->ID)];
            }
        } elseif (is_home() && !is_front_page()) {
            $page = (int) get_option('page_for_posts');
            if ($page) {
                $items[] = [self::plain(get_the_title($page)), (string) get_permalink($page)];
            }
        }
        return $items;
    }

    private static function termTrail(\WP_Term $term): array
    {
        $trail = [];
        foreach (array_reverse(get_ancestors($term->term_id, $term->taxonomy, 'taxonomy')) as $ancestorId) {
            $ancestor = get_term($ancestorId, $term->taxonomy);
            if ($ancestor instanceof \WP_Term) {
                $trail[] = [self::plain($ancestor->name), (string) get_term_link($ancestor)];
            }
        }
        $trail[] = [self::plain($term->name), (string) get_term_link($term)];
        return $trail;
    }

    /**
     * Top-level links of the main menu: a classic menu if one is assigned,
     * otherwise the block theme's navigation.
     */
    private static function navigationItems(string $location): array
    {
        $locations = array_filter((array) get_nav_menu_locations());
        $menuId = $locations[$location] ?? (reset($locations) ?: 0);
        if (!$menuId) {
            $menus = wp_get_nav_menus();
            $menuId = $menus ? $menus[0]->term_id : 0;
        }
        if ($menuId) {
            $items = [];
            foreach ((array) wp_get_nav_menu_items($menuId) as $item) {
                if ((int) $item->menu_item_parent === 0 && !empty($item->url)) {
                    $items[] = [self::plain($item->title), (string) $item->url];
                }
            }
            if ($items) {
                return $items;
            }
        }
        return self::blockNavigationItems();
    }

    private static function blockNavigationItems(): array
    {
        $navigation = get_posts([
            'post_type'      => 'wp_navigation',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);
        $blocks = $navigation ? parse_blocks($navigation[0]->post_content) : [['blockName' => 'core/page-list']];

        $items = [];
        foreach ($blocks as $block) {
            $attrs = $block['attrs'] ?? [];
            switch ($block['blockName'] ?? '') {
                case 'core/navigation-link':
                case 'core/navigation-submenu':
                    if (!empty($attrs['label']) && !empty($attrs['url'])) {
                        $items[] = [self::plain((string) $attrs['label']), (string) $attrs['url']];
                    }
                    break;
                case 'core/home-link':
                    $items[] = [self::plain((string) ($attrs['label'] ?? __('Home', 'mercury-schema'))), home_url('/')];
                    break;
                case 'core/page-list':
                    foreach (get_pages(['parent' => 0, 'sort_column' => 'menu_order,post_title']) as $page) {
                        $items[] = [self::plain($page->post_title), (string) get_permalink($page)];
                    }
                    break;
            }
        }
        return $items;
    }

    private static function plain(string $text): string
    {
        return trim(html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
