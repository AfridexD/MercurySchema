<?php
/**
 * Admin wiring: settings page, metabox, and editor assets.
 *
 * The metabox and the site-wide screen are empty shells; admin/js/editor-ui.js
 * fills them by talking to the REST API, so the admin never touches schema
 * data directly.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Admin;

use UnlimitedSchema\API\Hooks;
use UnlimitedSchema\API\REST;

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
        if (!self::canManage()) {
            return;
        }

        if ($hook === 'settings_page_' . Settings::PAGE) {
            wp_enqueue_style('unlimited-schema-admin', UNLIMITED_SCHEMA_URL . 'admin/css/editor-ui.css', ['dashicons'], UNLIMITED_SCHEMA_VERSION);
            if (Settings::currentTab() === 'schemas') {
                $this->enqueueEditor(['scope' => 'global', 'postId' => 0, 'testUrl' => home_url('/')]);
            }
            return;
        }

        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $post = get_post();
        if (!$post || !in_array($post->post_type, self::postTypes(), true)) {
            return;
        }

        wp_enqueue_style('unlimited-schema-admin', UNLIMITED_SCHEMA_URL . 'admin/css/editor-ui.css', ['dashicons'], UNLIMITED_SCHEMA_VERSION);
        $this->enqueueEditor([
            'scope'       => 'post',
            'postId'      => (int) $post->ID,
            'testUrl'     => $post->post_status === 'publish' ? get_permalink($post) : '',
            'settingsUrl' => Settings::url(),
        ]);
    }

    private function enqueueEditor(array $scope): void
    {
        wp_enqueue_script('unlimited-schema-admin', UNLIMITED_SCHEMA_URL . 'admin/js/editor-ui.js', [], UNLIMITED_SCHEMA_VERSION, true);

        $postTypes = [];
        foreach (get_post_types(['public' => true], 'objects') as $name => $object) {
            if ($name !== 'attachment') {
                $postTypes[$name] = $object->labels->singular_name;
            }
        }

        wp_localize_script('unlimited-schema-admin', 'UnlimitedSchemaData', $scope + [
            'restUrl'   => esc_url_raw(rest_url(REST::NAMESPACE . '/')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'tokens'    => self::tokens(),
            'postTypes' => $postTypes,
            'roles'     => array_map('translate_user_role', wp_roles()->get_names()),
            'locations' => [
                'front_page' => __('Front page', 'unlimited-schema'),
                'singular'   => __('Single posts & pages', 'unlimited-schema'),
                'archive'    => __('Archives & blog index', 'unlimited-schema'),
            ],
            'i18n'      => self::strings(),
        ]);
    }

    /**
     * Token name => label for the token picker.
     */
    public static function tokens(): array
    {
        return (array) apply_filters(Hooks::TOKENS, [
            'post_title'       => __('Post title', 'unlimited-schema'),
            'post_excerpt'     => __('Post excerpt', 'unlimited-schema'),
            'post_url'         => __('Post URL', 'unlimited-schema'),
            'post_date'        => __('Publish date', 'unlimited-schema'),
            'post_modified'    => __('Last modified date', 'unlimited-schema'),
            'featured_image'   => __('Featured image URL', 'unlimited-schema'),
            'author_name'      => __('Author name', 'unlimited-schema'),
            'author_url'       => __('Author archive URL', 'unlimited-schema'),
            'site_name'        => __('Site title', 'unlimited-schema'),
            'site_description' => __('Site tagline', 'unlimited-schema'),
            'site_logo'        => __('Site logo URL', 'unlimited-schema'),
            'home_url'         => __('Home page URL', 'unlimited-schema'),
        ]);
    }

    private static function strings(): array
    {
        return [
            'title'             => __('Structured data for this page', 'unlimited-schema'),
            'intro'             => __('Helps search engines understand this content and show rich results.', 'unlimited-schema'),
            'siteWideTitle'     => __('Site-wide schemas', 'unlimited-schema'),
            'siteWideIntro'     => __('Added to every page that matches their display rules. A page’s own schema of the same type replaces these.', 'unlimited-schema'),
            'add'               => __('Add schema', 'unlimited-schema'),
            'searchTypes'       => __('Search schema types…', 'unlimited-schema'),
            'close'             => __('Close', 'unlimited-schema'),
            'emptyTitle'        => __('No schema yet', 'unlimited-schema'),
            'emptyText'         => __('Add a schema type and it fills itself in from this post. Popular choices:', 'unlimited-schema'),
            'emptyGlobal'       => __('Describe your organization once and it appears across the site. Popular choices:', 'unlimited-schema'),
            'siteWideOnPage'    => __('Site-wide on this page:', 'unlimited-schema'),
            'overridden'        => __('Replaced by this page’s own schema of the same type.', 'unlimited-schema'),
            'overriddenShort'   => __('replaced', 'unlimited-schema'),
            'manage'            => __('Manage', 'unlimited-schema'),
            /* translators: %s: schema type label */
            'replacesSiteWide'  => __('This replaces the site-wide %s schema on this page.', 'unlimited-schema'),
            /* translators: %s: schema type label */
            'added'             => __('%s schema added.', 'unlimited-schema'),
            'duplicated'        => __('Schema duplicated.', 'unlimited-schema'),
            'enabled'           => __('Enabled', 'unlimited-schema'),
            'disabled'          => __('Disabled', 'unlimited-schema'),
            'nowEnabled'        => __('Schema enabled.', 'unlimited-schema'),
            'nowDisabled'       => __('Schema disabled. It will not be printed.', 'unlimited-schema'),
            'actions'           => __('Schema actions', 'unlimited-schema'),
            'duplicate'         => __('Duplicate', 'unlimited-schema'),
            'copyJson'          => __('Copy JSON-LD', 'unlimited-schema'),
            'delete'            => __('Delete', 'unlimited-schema'),
            'confirmDelete'     => __('Click again to delete', 'unlimited-schema'),
            'deleted'           => __('Schema deleted.', 'unlimited-schema'),
            'tabFields'         => __('Fields', 'unlimited-schema'),
            'tabRules'          => __('Display rules', 'unlimited-schema'),
            'tabPreview'        => __('Preview', 'unlimited-schema'),
            'save'              => __('Save changes', 'unlimited-schema'),
            'saved'             => __('Saved. Ready for rich results.', 'unlimited-schema'),
            'savedIncomplete'   => __('Saved, but some required fields are empty. It won’t be printed until they’re filled.', 'unlimited-schema'),
            'unsaved'           => __('Unsaved', 'unlimited-schema'),
            'valid'             => __('Ready', 'unlimited-schema'),
            'needsAttention'    => __('Incomplete', 'unlimited-schema'),
            /* translators: %s: number of problems */
            'missingSummary'    => __('%s required field(s) still empty. This schema is not printed until they are filled.', 'unlimited-schema'),
            /* translators: %s: number of problems */
            'fixSummary'        => __('Couldn’t save: %s field(s) need fixing.', 'unlimited-schema'),
            /* translators: %s: field label */
            'required'          => __('%s is required.', 'unlimited-schema'),
            /* translators: %s: field label */
            'invalid'           => __('%s has an invalid value.', 'unlimited-schema'),
            'invalid_url'       => __('Enter a full URL starting with https://', 'unlimited-schema'),
            'invalid_date'      => __('Use a date like 2026-10-02 or 2026-10-02T18:30:00+00:00.', 'unlimited-schema'),
            'invalid_duration'  => __('Use an ISO 8601 duration like PT30M (30 minutes) or PT1H15M.', 'unlimited-schema'),
            'invalid_number'    => __('Enter a number, like 19.99.', 'unlimited-schema'),
            'invalid_integer'   => __('Enter a whole number.', 'unlimited-schema'),
            'invalid_enum'      => __('Choose one of the listed options.', 'unlimited-schema'),
            /* translators: %s: field key */
            'unknownField'      => __('“%s” is not a field of this schema type.', 'unlimited-schema'),
            /* translators: %s: number of questions */
            'nQuestions'        => __('%s question(s)', 'unlimited-schema'),
            'item'              => __('Item', 'unlimited-schema'),
            /* translators: %s: item label, e.g. "question" */
            'addItem'           => __('Add %s', 'unlimited-schema'),
            'moveUp'            => __('Move up', 'unlimited-schema'),
            'moveDown'          => __('Move down', 'unlimited-schema'),
            'remove'            => __('Remove', 'unlimited-schema'),
            'insertToken'       => __('Insert dynamic value', 'unlimited-schema'),
            'rulesIntro'        => __('Optionally limit when this schema is printed. Leave everything empty to always print it on this page.', 'unlimited-schema'),
            'rulesIntroGlobal'  => __('Choose where this schema appears. Empty groups mean no restriction.', 'unlimited-schema'),
            'where'             => __('Show on', 'unlimited-schema'),
            'postTypes'         => __('Post types', 'unlimited-schema'),
            'categories'        => __('Categories (slugs or IDs)', 'unlimited-schema'),
            'userRoles'         => __('Author roles', 'unlimited-schema'),
            'postIds'           => __('Only these post IDs', 'unlimited-schema'),
            'commaHelp'         => __('Comma-separated.', 'unlimited-schema'),
            'loading'           => __('Loading…', 'unlimited-schema'),
            'previewValid'      => __('Valid. This is the JSON-LD search engines will see.', 'unlimited-schema'),
            /* translators: %s: number of problems */
            'previewInvalid'    => __('%s problem(s). This schema won’t be printed until they’re fixed:', 'unlimited-schema'),
            'previewNote'       => __('Dynamic values are filled in from this post. The preview uses your unsaved changes.', 'unlimited-schema'),
            'previewNoteGlobal' => __('Post-based dynamic values are filled in per page on the live site.', 'unlimited-schema'),
            'copy'              => __('Copy', 'unlimited-schema'),
            'copied'            => __('Copied to clipboard.', 'unlimited-schema'),
            'testGoogle'        => __('Test in Google', 'unlimited-schema'),
            'error'             => __('Something went wrong. Please try again.', 'unlimited-schema'),
        ];
    }

    public function actionLinks(array $links): array
    {
        array_unshift($links, '<a href="' . esc_url(Settings::url()) . '">' . esc_html__('Settings', 'unlimited-schema') . '</a>');
        return $links;
    }
}
