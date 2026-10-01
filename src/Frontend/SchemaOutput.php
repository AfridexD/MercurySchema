<?php
/**
 * Renders JSON-LD script tags for the current singular post.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Frontend;

use UnlimitedSchema\API\Hooks;
use UnlimitedSchema\Core\ConditionEvaluator;
use UnlimitedSchema\Core\Schema;
use UnlimitedSchema\Helpers\DataMapper;
use UnlimitedSchema\Helpers\Logger;
use UnlimitedSchema\Helpers\PostMetaStore;

class SchemaOutput
{
    private SchemaRegistry $registry;
    private PostMetaStore $store;
    private ConditionEvaluator $evaluator;

    public function __construct(SchemaRegistry $registry, PostMetaStore $store)
    {
        $this->registry = $registry;
        $this->store = $store;
        $this->evaluator = new ConditionEvaluator();
    }

    /**
     * Hook callback for wp_head / wp_footer.
     */
    public function render(): void
    {
        if (!is_singular() || !apply_filters(Hooks::OUTPUT_ENABLED, true)) {
            return;
        }
        echo $this->getMarkup((int) get_queried_object_id()); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON encoded with JSON_HEX_TAG.
    }

    public function getMarkup(int $postId): string
    {
        $html = '';
        foreach ($this->buildJsonLd($postId) as $jsonLd) {
            $json = wp_json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
            if ($json !== false) {
                $html .= '<script type="application/ld+json">' . $json . "</script>\n";
            }
        }
        return $html;
    }

    /**
     * @return array<int, array> One JSON-LD document per renderable schema.
     */
    public function buildJsonLd(int $postId): array
    {
        $post = $postId ? get_post($postId) : null;
        if (!$post) {
            return [];
        }

        $doc = $this->store->get($postId); // The single meta read for this post.
        if (!$doc['schemas']) {
            return [];
        }

        $context = null;
        $tokens = null;
        $validator = $this->registry->validator();
        $output = [];

        foreach ($doc['schemas'] as $raw) {
            $schema = Schema::fromArray($raw);
            $type = $this->registry->get($schema->getType());
            if (!$schema->isEnabled() || $type === null) {
                continue;
            }

            $context ??= $this->conditionContext($post);
            $shouldRender = $this->evaluator->evaluate($schema->getConditions(), $context);
            if (!apply_filters(Hooks::SHOULD_RENDER, $shouldRender, $schema, $postId)) {
                continue;
            }

            $data = $schema->getData();
            if (DataMapper::hasToken($data)) {
                $tokens ??= $this->tokenValues($post);
                $data = DataMapper::resolve($data, $tokens);
            }
            $resolved = (clone $schema)->setData($data);

            $result = $validator->validate($resolved);
            if (!$result['valid']) {
                Logger::log('Skipped invalid schema.', ['post_id' => $postId, 'schema' => $schema->getId(), 'errors' => $result['errors']]);
                continue;
            }

            $jsonLd = apply_filters(Hooks::JSON_LD_OUTPUT, $type->toJsonLd($data), $postId, $schema);
            if (is_array($jsonLd) && $jsonLd) {
                $output[] = $jsonLd;
            }
        }

        return $output;
    }

    private function conditionContext(\WP_Post $post): array
    {
        $categories = [];
        $terms = get_the_terms($post, 'category');
        if (is_array($terms)) {
            foreach ($terms as $term) {
                $categories[] = (string) $term->term_id;
                $categories[] = $term->slug;
            }
        }

        $author = get_userdata((int) $post->post_author);

        return apply_filters(Hooks::CONDITION_CONTEXT, [
            'post_id'    => (int) $post->ID,
            'post_type'  => $post->post_type,
            'categories' => $categories,
            'user_roles' => $author ? (array) $author->roles : [],
        ], $post);
    }

    private function tokenValues(\WP_Post $post): array
    {
        $excerpt = has_excerpt($post)
            ? $post->post_excerpt
            : wp_trim_words(strip_shortcodes($post->post_content), 55, '…');

        $logoId = (int) get_theme_mod('custom_logo');
        $logo = $logoId ? wp_get_attachment_image_url($logoId, 'full') : '';
        $authorId = (int) $post->post_author;

        $values = [
            'post_title'       => self::plain($post->post_title),
            'post_excerpt'     => self::plain($excerpt),
            'post_url'         => (string) get_permalink($post),
            'post_date'        => (string) get_post_time('c', true, $post),
            'post_modified'    => (string) get_post_modified_time('c', true, $post),
            'featured_image'   => (string) get_the_post_thumbnail_url($post, 'full'),
            'author_name'      => self::plain((string) get_the_author_meta('display_name', $authorId)),
            'author_url'       => $authorId ? (string) get_author_posts_url($authorId) : '',
            'site_name'        => self::plain((string) get_bloginfo('name')),
            'site_description' => self::plain((string) get_bloginfo('description')),
            'site_logo'        => $logo ? (string) $logo : (string) get_site_icon_url(),
            'home_url'         => home_url('/'),
        ];

        return (array) apply_filters(Hooks::TOKEN_VALUES, $values, $post);
    }

    private static function plain(string $text): string
    {
        return trim(html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
