<?php
/**
 * Renders JSON-LD script tags: site-wide schemas on every page, plus the
 * current post's own schemas on singular pages.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Frontend;

use UnlimitedSchema\API\Hooks;
use UnlimitedSchema\Core\ConditionEvaluator;
use UnlimitedSchema\Core\Schema;
use UnlimitedSchema\Helpers\DataMapper;
use UnlimitedSchema\Helpers\GlobalStore;
use UnlimitedSchema\Helpers\Logger;
use UnlimitedSchema\Helpers\PostMetaStore;

class SchemaOutput
{
    private SchemaRegistry $registry;
    private PostMetaStore $store;
    private GlobalStore $global;
    private ConditionEvaluator $evaluator;

    public function __construct(SchemaRegistry $registry, PostMetaStore $store, GlobalStore $global)
    {
        $this->registry = $registry;
        $this->store = $store;
        $this->global = $global;
        $this->evaluator = new ConditionEvaluator();
    }

    /**
     * Hook callback for wp_head / wp_footer.
     */
    public function render(): void
    {
        if (is_admin() || is_feed() || !apply_filters(Hooks::OUTPUT_ENABLED, true)) {
            return;
        }

        $object = is_singular() ? get_queried_object() : null;
        $post = $object instanceof \WP_Post ? $object : null;

        $locations = [];
        if (is_front_page()) {
            $locations[] = 'front_page';
        }
        if ($post) {
            $locations[] = 'singular';
        } elseif (is_archive() || is_home() || is_search()) {
            $locations[] = 'archive';
        }

        echo $this->markup($this->documents($post, $locations)); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON encoded with JSON_HEX_TAG.
    }

    /**
     * Markup for a singular post (site-wide schemas included).
     */
    public function getMarkup(int $postId): string
    {
        return $this->markup($this->buildJsonLd($postId));
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
        $locations = ['singular'];
        if ((int) get_option('page_on_front') === $postId) {
            $locations[] = 'front_page';
        }
        return $this->documents($post, $locations);
    }

    /**
     * Resolve, validate and build one schema without saving it, for the editor preview.
     *
     * @return array{json_ld: array, valid: bool, errors: array}
     */
    public function preview(Schema $schema, ?\WP_Post $post): array
    {
        $type = $this->registry->get($schema->getType());
        if ($type === null) {
            return ['json_ld' => [], 'valid' => false, 'errors' => [['field' => '', 'message' => 'Unknown schema type: ' . $schema->getType()]]];
        }
        $data = DataMapper::resolve($schema->getData(), $this->tokenValues($post));
        $result = $this->registry->validator()->validate((clone $schema)->setData($data));
        return ['json_ld' => $type->toJsonLd($data)] + $result;
    }

    /**
     * Whether a site-wide schema's display rules match this post's page.
     */
    public function appliesTo(array $raw, \WP_Post $post): bool
    {
        $schema = Schema::fromArray($raw);
        $locations = ['singular'];
        if ((int) get_option('page_on_front') === (int) $post->ID) {
            $locations[] = 'front_page';
        }
        $context = $this->conditionContext($post, $locations, $schema->getConditions(), []);
        return $schema->isEnabled() && $this->evaluator->evaluate($schema->getConditions(), $context);
    }

    private function markup(array $documents): string
    {
        $html = '';
        foreach ($documents as $jsonLd) {
            $json = wp_json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
            if ($json !== false) {
                $html .= '<script type="application/ld+json">' . $json . "</script>\n";
            }
        }
        return $html;
    }

    private function documents(?\WP_Post $post, array $locations): array
    {
        $own = $post ? $this->store->get((int) $post->ID)['schemas'] : []; // The single meta read for this post.
        $own = array_values(array_filter($own, static fn($s) => !isset($s['enabled']) || $s['enabled']));
        $site = $this->global->get()['schemas']; // Autoloaded option: no query.

        // A post's own schema replaces a site-wide schema of the same type.
        $ownTypes = array_column($own, 'type');
        $site = array_filter($site, static fn($s) => !in_array($s['type'], $ownTypes, true));

        $schemas = array_merge(array_values($site), $own);
        if (!$schemas) {
            return [];
        }

        $postId = $post ? (int) $post->ID : 0;
        $context = [];
        $tokens = null;
        $validator = null;
        $output = [];

        foreach ($schemas as $raw) {
            $schema = Schema::fromArray($raw);
            if (!$schema->isEnabled()) {
                continue; // Disabled schemas never load definitions or post data.
            }
            $type = $this->registry->get($schema->getType());
            if ($type === null) {
                continue;
            }

            $context = $this->conditionContext($post, $locations, $schema->getConditions(), $context);
            $shouldRender = $this->evaluator->evaluate($schema->getConditions(), $context);
            if (!apply_filters(Hooks::SHOULD_RENDER, $shouldRender, $schema, $postId)) {
                continue;
            }

            $data = $schema->getData();
            if (DataMapper::hasToken($data)) {
                $tokens ??= $this->tokenValues($post);
                $data = DataMapper::resolve($data, $tokens);
            }

            $validator ??= $this->registry->validator();
            $result = $validator->validate((clone $schema)->setData($data));
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

    /**
     * Build the condition context, loading terms and the author only when a
     * condition actually needs them. $context carries what earlier schemas loaded.
     */
    private function conditionContext(?\WP_Post $post, array $locations, array $conditions, array $context): array
    {
        $conditions = ConditionEvaluator::normalize($conditions);
        $context += [
            'post_id'   => $post ? (int) $post->ID : 0,
            'post_type' => $post ? $post->post_type : '',
            'locations' => $locations,
        ];

        if ($conditions['categories'] && !isset($context['categories'])) {
            $context['categories'] = [];
            $terms = $post ? get_the_terms($post, 'category') : [];
            foreach (is_array($terms) ? $terms : [] as $term) {
                $context['categories'][] = (string) $term->term_id;
                $context['categories'][] = $term->slug;
            }
        }

        if ($conditions['user_roles'] && !isset($context['user_roles'])) {
            $author = $post ? get_userdata((int) $post->post_author) : false;
            $context['user_roles'] = $author ? (array) $author->roles : [];
        }

        return apply_filters(Hooks::CONDITION_CONTEXT, $context, $post);
    }

    /**
     * Values for {{tokens}}. Post tokens are empty when there is no post
     * (front page of posts, archives).
     */
    private function tokenValues(?\WP_Post $post): array
    {
        $logoId = (int) get_theme_mod('custom_logo');
        $logo = $logoId ? wp_get_attachment_image_url($logoId, 'full') : '';

        $values = [
            'post_title'       => '',
            'post_excerpt'     => '',
            'post_url'         => '',
            'post_date'        => '',
            'post_modified'    => '',
            'featured_image'   => '',
            'author_name'      => '',
            'author_url'       => '',
            'site_name'        => self::plain((string) get_bloginfo('name')),
            'site_description' => self::plain((string) get_bloginfo('description')),
            'site_logo'        => $logo ? (string) $logo : (string) get_site_icon_url(),
            'home_url'         => home_url('/'),
        ];

        if ($post) {
            $excerpt = has_excerpt($post)
                ? $post->post_excerpt
                : wp_trim_words(strip_shortcodes($post->post_content), 55, '…');
            $authorId = (int) $post->post_author;

            $values = array_merge($values, [
                'post_title'     => self::plain($post->post_title),
                'post_excerpt'   => self::plain($excerpt),
                'post_url'       => (string) get_permalink($post),
                'post_date'      => (string) get_post_time('c', true, $post),
                'post_modified'  => (string) get_post_modified_time('c', true, $post),
                'featured_image' => (string) get_the_post_thumbnail_url($post, 'full'),
                'author_name'    => $authorId ? self::plain((string) get_the_author_meta('display_name', $authorId)) : '',
                'author_url'     => $authorId ? (string) get_author_posts_url($authorId) : '',
            ]);
        }

        return (array) apply_filters(Hooks::TOKEN_VALUES, $values, $post);
    }

    private static function plain(string $text): string
    {
        return trim(html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
