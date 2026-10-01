<?php
/**
 * Admin wiring: settings page, metabox, and editor assets.
 *
 * The metabox is an empty shell; admin/js/editor-ui.js fills it by talking
 * to the REST API, so the admin never touches schema data directly.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Admin;

use UnlimitedSchema\API\Hooks;
use UnlimitedSchema\API\REST;
use UnlimitedSchema\Helpers\DataMapper;

class AdminController
{
    private Settings $settings;

    public function __construct()
    {
        $this->settings = new Settings();
    }

    public function registerHooks(): void
    {
        add_action('admin_init', [$this->settings, 'register']);
        add_action('admin_menu', [$this->settings, 'addPage']);
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('plugin_action_links_' . plugin_basename(UNLIMITED_SCHEMA_FILE), [$this, 'actionLinks']);
    }

    public static function postTypes(): array
    {
        return (array) apply_filters(Hooks::POST_TYPES, (array) Settings::get('post_types'));
    }

    public static function canManage(): bool
    {
        return current_user_can((string) apply_filters(Hooks::REST_CAPABILITY, 'manage_options'));
    }

    public function addMetaBox(): void
    {
        if (!self::canManage()) {
            return;
        }
        foreach (self::postTypes() as $postType) {
            add_meta_box(
                'unlimited-schema-box',
                __('Schema Markup', 'unlimited-schema'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'default'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        require UNLIMITED_SCHEMA_PATH . 'admin/views/metabox.php';
    }

    public function enqueueAssets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true) || !self::canManage()) {
            return;
        }
        $post = get_post();
        if (!$post || !in_array($post->post_type, self::postTypes(), true)) {
            return;
        }

        wp_enqueue_style('unlimited-schema-admin', UNLIMITED_SCHEMA_URL . 'admin/css/editor-ui.css', [], UNLIMITED_SCHEMA_VERSION);
        wp_enqueue_script('unlimited-schema-admin', UNLIMITED_SCHEMA_URL . 'admin/js/editor-ui.js', [], UNLIMITED_SCHEMA_VERSION, true);

        wp_localize_script('unlimited-schema-admin', 'UnlimitedSchemaData', [
            'restUrl' => esc_url_raw(rest_url(REST::NAMESPACE . '/')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'postId'  => (int) $post->ID,
            'tokens'  => array_map(static fn($t) => '{{' . $t . '}}', DataMapper::TOKENS),
            'i18n'    => [
                'selectType'    => __('— Select schema type —', 'unlimited-schema'),
                'add'           => __('Add schema', 'unlimited-schema'),
                'save'          => __('Save', 'unlimited-schema'),
                'validate'      => __('Validate', 'unlimited-schema'),
                'delete'        => __('Delete', 'unlimited-schema'),
                'enabled'       => __('Enabled', 'unlimited-schema'),
                'disabled'      => __('Disabled', 'unlimited-schema'),
                'conditions'    => __('Display conditions', 'unlimited-schema'),
                'conditionHelp' => __('Comma-separated. Leave empty for no restriction.', 'unlimited-schema'),
                'postTypes'     => __('Post types', 'unlimited-schema'),
                'categories'    => __('Categories (slugs or IDs)', 'unlimited-schema'),
                'userRoles'     => __('Author roles', 'unlimited-schema'),
                'postIds'       => __('Post IDs', 'unlimited-schema'),
                'confirmDelete' => __('Delete this schema?', 'unlimited-schema'),
                'saved'         => __('Schema saved.', 'unlimited-schema'),
                'deleted'       => __('Schema deleted.', 'unlimited-schema'),
                'added'         => __('Schema added.', 'unlimited-schema'),
                'valid'         => __('Valid. Ready for rich results.', 'unlimited-schema'),
                'invalid'       => __('Fix the highlighted fields.', 'unlimited-schema'),
                'empty'         => __('No schema on this post yet.', 'unlimited-schema'),
                'loading'       => __('Loading…', 'unlimited-schema'),
                'error'         => __('Something went wrong.', 'unlimited-schema'),
                'unsaved'       => __('Unsaved changes', 'unlimited-schema'),
                'required'      => __('Required', 'unlimited-schema'),
                'tokensHelp'    => __('Dynamic values you can type into any field:', 'unlimited-schema'),
            ],
        ]);
    }

    public function actionLinks(array $links): array
    {
        $url = admin_url('options-general.php?page=' . Settings::PAGE);
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'unlimited-schema') . '</a>');
        return $links;
    }
}
