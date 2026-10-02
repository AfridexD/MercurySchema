<?php
/**
 * Admin wiring: settings page, metabox, and editor assets.
 *
 * The metabox and the site-wide screen are empty shells; admin/js/editor-ui.js
 * fills them by talking to the REST API, so the admin never touches schema
 * data directly.
 *
 * @package MercurySchema
 */

namespace MercurySchema\Admin;

use MercurySchema\API\Hooks;
use MercurySchema\API\REST;
use MercurySchema\API\SchemaController;

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
        (new Pages($this->settings))->registerHooks();
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('plugin_action_links_' . plugin_basename(MERCURY_SCHEMA_FILE), [$this, 'actionLinks']);
    }

    public static function postTypes(): array
    {
        return (array) apply_filters(Hooks::POST_TYPES, (array) Settings::get('post_types'));
    }

    public static function canManage(): bool
    {
        return current_user_can(SchemaController::postCapability());
    }

    public static function canManageGlobal(): bool
    {
        return current_user_can(SchemaController::globalCapability());
    }

    public function addMetaBox(): void
    {
        if (!self::canManage()) {
            return;
        }
        foreach (self::postTypes() as $postType) {
            add_meta_box(
                'mercury-schema-box',
                __('Schema Markup', 'mercury-schema'),
                [$this, 'renderMetaBox'],
                $postType,
                'normal',
                'default'
            );
        }
    }

    public function renderMetaBox(\WP_Post $post): void
    {
        require MERCURY_SCHEMA_PATH . 'admin/views/metabox.php';
    }

    public function enqueueAssets(string $hook): void
    {
        $page = Pages::current();
        if ($page !== '') {
            if (!self::canManageGlobal()) {
                return;
            }
            wp_enqueue_style('mercury-schema-admin', MERCURY_SCHEMA_URL . 'admin/css/editor-ui.css', ['dashicons'], MERCURY_SCHEMA_VERSION);
            if ($page === Pages::SITE_WIDE) {
                self::enqueueEditor(['scope' => 'global', 'postId' => 0, 'testUrl' => home_url('/')]);
            } elseif ($page === Pages::TYPES || $page === Pages::SETUP) {
                $this->enqueueSetup($page === Pages::SETUP ? 'wizard' : 'types');
            }
            return;
        }

        if (!in_array($hook, ['post.php', 'post-new.php'], true) || !self::canManage()) {
            return;
        }
        $post = get_post();
        if (!$post || !in_array($post->post_type, self::postTypes(), true)) {
            return;
        }

        wp_enqueue_style('mercury-schema-admin', MERCURY_SCHEMA_URL . 'admin/css/editor-ui.css', ['dashicons'], MERCURY_SCHEMA_VERSION);
        self::enqueueEditor([
            'scope'       => 'post',
            'postId'      => (int) $post->ID,
            'testUrl'     => $post->post_status === 'publish' ? get_permalink($post) : '',
            'settingsUrl' => self::canManageGlobal() ? Pages::url(Pages::SITE_WIDE) : '',
            'typesUrl'    => self::canManageGlobal() ? Pages::url(Pages::TYPES) : '',
        ]);
    }

    private function enqueueSetup(string $mode): void
    {
        wp_enqueue_style('mercury-schema-setup', MERCURY_SCHEMA_URL . 'admin/css/setup.css', ['mercury-schema-admin'], MERCURY_SCHEMA_VERSION);
        wp_enqueue_script('mercury-schema-setup', MERCURY_SCHEMA_URL . 'admin/js/setup.js', [], MERCURY_SCHEMA_VERSION, true);

        $firstPost = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids']);
        wp_localize_script('mercury-schema-setup', 'MercurySchemaSetup', [
            'mode'     => $mode,
            'restUrl'  => esc_url_raw(rest_url(REST::NAMESPACE . '/')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'version'  => MERCURY_SCHEMA_VERSION,
            'logo'     => Brand::mark('#fff'),
            'urls'     => [
                'types'    => Pages::url(Pages::TYPES),
                'siteWide' => Pages::url(Pages::SITE_WIDE),
                'setup'    => Pages::url(Pages::SETUP),
                'post'     => $firstPost ? get_edit_post_link($firstPost[0], 'raw') : admin_url('post-new.php'),
                'test'     => 'https://search.google.com/test/rich-results?url=' . rawurlencode(home_url('/')),
            ],
            'i18n'     => self::setupStrings(),
        ]);
    }

    /** Load the schema editor (also used inside the Elementor editor). */
    public static function enqueueEditor(array $scope): void
    {
        wp_enqueue_script('mercury-schema-admin', MERCURY_SCHEMA_URL . 'admin/js/editor-ui.js', [], MERCURY_SCHEMA_VERSION, true);

        $postTypes = [];
        foreach (get_post_types(['public' => true], 'objects') as $name => $object) {
            if ($name !== 'attachment') {
                $postTypes[$name] = $object->labels->singular_name;
            }
        }

        wp_localize_script('mercury-schema-admin', 'MercurySchemaData', $scope + [
            'restUrl'   => esc_url_raw(rest_url(REST::NAMESPACE . '/')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'tokens'    => self::tokens(),
            'postTypes' => $postTypes,
            'roles'     => array_map('translate_user_role', wp_roles()->get_names()),
            'locations' => [
                'front_page' => __('Front page', 'mercury-schema'),
                'singular'   => __('Single posts & pages', 'mercury-schema'),
                'archive'    => __('Archives & blog index', 'mercury-schema'),
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
            'post_title'       => __('Post title', 'mercury-schema'),
            'post_excerpt'     => __('Post excerpt', 'mercury-schema'),
            'post_content'     => __('Post content (plain text)', 'mercury-schema'),
            'post_url'         => __('Post URL', 'mercury-schema'),
            'post_date'        => __('Publish date', 'mercury-schema'),
            'post_modified'    => __('Last modified date', 'mercury-schema'),
            'featured_image'   => __('Featured image URL', 'mercury-schema'),
            'author_name'      => __('Author name', 'mercury-schema'),
            'author_url'       => __('Author archive URL', 'mercury-schema'),
            'site_name'        => __('Site title', 'mercury-schema'),
            'site_description' => __('Site tagline', 'mercury-schema'),
            'site_logo'        => __('Site logo URL', 'mercury-schema'),
            'home_url'         => __('Home page URL', 'mercury-schema'),
            'site_language'    => __('Site language (e.g. en-US)', 'mercury-schema'),
        ]);
    }

    private static function strings(): array
    {
        return [
            'title'             => __('Structured data for this page', 'mercury-schema'),
            'intro'             => __('Helps search engines understand this content and show rich results.', 'mercury-schema'),
            'siteWideTitle'     => __('Site-wide schemas', 'mercury-schema'),
            'siteWideIntro'     => __('Added to every page that matches their display rules. A page’s own schema of the same type replaces these.', 'mercury-schema'),
            'add'               => __('Add schema', 'mercury-schema'),
            'searchTypes'       => __('Search schema types…', 'mercury-schema'),
            'close'             => __('Close', 'mercury-schema'),
            'emptyTitle'        => __('No schema yet', 'mercury-schema'),
            'emptyText'         => __('Add a schema type and it fills itself in from this post. Popular choices:', 'mercury-schema'),
            'emptyGlobal'       => __('Describe your organization once and it appears across the site. Popular choices:', 'mercury-schema'),
            'siteWideOnPage'    => __('Site-wide on this page:', 'mercury-schema'),
            'overridden'        => __('Replaced by this page’s own schema of the same type.', 'mercury-schema'),
            'overriddenShort'   => __('replaced', 'mercury-schema'),
            'manage'            => __('Manage', 'mercury-schema'),
            /* translators: %s: schema type label */
            'replacesSiteWide'  => __('This replaces the site-wide %s schema on this page.', 'mercury-schema'),
            /* translators: %s: schema type label */
            'added'             => __('%s schema added.', 'mercury-schema'),
            'duplicated'        => __('Schema duplicated.', 'mercury-schema'),
            'enabled'           => __('Enabled', 'mercury-schema'),
            'disabled'          => __('Disabled', 'mercury-schema'),
            'nowEnabled'        => __('Schema enabled.', 'mercury-schema'),
            'nowDisabled'       => __('Schema disabled. It will not be printed.', 'mercury-schema'),
            'actions'           => __('Schema actions', 'mercury-schema'),
            'duplicate'         => __('Duplicate', 'mercury-schema'),
            'copyJson'          => __('Copy JSON-LD', 'mercury-schema'),
            'delete'            => __('Delete', 'mercury-schema'),
            'confirmDelete'     => __('Click again to delete', 'mercury-schema'),
            'deleted'           => __('Schema deleted.', 'mercury-schema'),
            'tabFields'         => __('Fields', 'mercury-schema'),
            'tabRules'          => __('Display rules', 'mercury-schema'),
            'tabPreview'        => __('Preview', 'mercury-schema'),
            'save'              => __('Save changes', 'mercury-schema'),
            'saved'             => __('Saved. Ready for rich results.', 'mercury-schema'),
            'savedIncomplete'   => __('Saved, but some required fields are empty. It won’t be printed until they’re filled.', 'mercury-schema'),
            'unsaved'           => __('Unsaved', 'mercury-schema'),
            'valid'             => __('Ready', 'mercury-schema'),
            'needsAttention'    => __('Incomplete', 'mercury-schema'),
            /* translators: %s: number of problems */
            'missingSummary'    => __('%s required field(s) still empty. This schema is not printed until they are filled.', 'mercury-schema'),
            /* translators: %s: number of problems */
            'fixSummary'        => __('Couldn’t save: %s field(s) need fixing.', 'mercury-schema'),
            /* translators: %s: field label */
            'required'          => __('%s is required.', 'mercury-schema'),
            /* translators: %s: field label */
            'invalid'           => __('%s has an invalid value.', 'mercury-schema'),
            'invalid_url'       => __('Enter a full URL starting with https://', 'mercury-schema'),
            'invalid_date'      => __('Use a date like 2026-10-02 or 2026-10-02T18:30:00+00:00.', 'mercury-schema'),
            'invalid_duration'  => __('Use an ISO 8601 duration like PT30M (30 minutes) or PT1H15M.', 'mercury-schema'),
            'invalid_number'    => __('Enter a number, like 19.99.', 'mercury-schema'),
            'invalid_integer'   => __('Enter a whole number.', 'mercury-schema'),
            'invalid_enum'      => __('Choose one of the listed options.', 'mercury-schema'),
            'added_badge'       => __('Added', 'mercury-schema'),
            /* translators: %s: number of hidden schema types */
            'hiddenTypes'       => __('%s more types are switched off.', 'mercury-schema'),
            'manageTypes'       => __('Manage schema types', 'mercury-schema'),
            'typeOffPill'       => __('Type off', 'mercury-schema'),
            'typeOff'           => __('This schema type is switched off, so it isn’t printed. Its data is kept.', 'mercury-schema'),
            'nothingGenerated'  => __('Nothing to build on this page: breadcrumbs need a page below the home page, and navigation needs a site menu.', 'mercury-schema'),
            /* translators: %s: field label */
            'tooLong'           => __('%s is too long. Shorten it or split it up.', 'mercury-schema'),
            /* translators: %s: field key */
            'unknownField'      => __('“%s” is not a field of this schema type.', 'mercury-schema'),
            /* translators: %s: number of questions */
            'nQuestions'        => __('%s question(s)', 'mercury-schema'),
            'item'              => __('Item', 'mercury-schema'),
            /* translators: %s: item label, e.g. "question" */
            'addItem'           => __('Add %s', 'mercury-schema'),
            'moveUp'            => __('Move up', 'mercury-schema'),
            'moveDown'          => __('Move down', 'mercury-schema'),
            'remove'            => __('Remove', 'mercury-schema'),
            'insertToken'       => __('Insert dynamic value', 'mercury-schema'),
            'rulesIntro'        => __('Optionally limit when this schema is printed. Leave everything empty to always print it on this page.', 'mercury-schema'),
            'rulesIntroGlobal'  => __('Choose where this schema appears. Empty groups mean no restriction.', 'mercury-schema'),
            'where'             => __('Show on', 'mercury-schema'),
            'postTypes'         => __('Post types', 'mercury-schema'),
            'categories'        => __('Categories (slugs or IDs)', 'mercury-schema'),
            'userRoles'         => __('Author roles', 'mercury-schema'),
            'postIds'           => __('Only these post IDs', 'mercury-schema'),
            'commaHelp'         => __('Comma-separated.', 'mercury-schema'),
            'loading'           => __('Loading…', 'mercury-schema'),
            'previewValid'      => __('Valid. This is the JSON-LD search engines will see.', 'mercury-schema'),
            /* translators: %s: number of problems */
            'previewInvalid'    => __('%s problem(s). This schema won’t be printed until they’re fixed:', 'mercury-schema'),
            'previewNote'       => __('Dynamic values are filled in from this post. The preview uses your unsaved changes.', 'mercury-schema'),
            'previewNoteGlobal' => __('Post-based dynamic values are filled in per page on the live site.', 'mercury-schema'),
            'copy'              => __('Copy', 'mercury-schema'),
            'copied'            => __('Copied to clipboard.', 'mercury-schema'),
            'testGoogle'        => __('Test in Google', 'mercury-schema'),
            'error'             => __('Something went wrong. Please try again.', 'mercury-schema'),
        ];
    }

    public function actionLinks(array $links): array
    {
        array_unshift($links, '<a href="' . esc_url(Pages::url()) . '">' . esc_html__('Schema Types', 'mercury-schema') . '</a>');
        return $links;
    }

    private static function setupStrings(): array
    {
        return [
            'tabs'          => [__('Getting Started', 'mercury-schema'), __('Configuration', 'mercury-schema'), __('Schemas', 'mercury-schema'), __('Done', 'mercury-schema')],
            'skipped'       => __('Skipped', 'mercury-schema'),
            'welcomeTitle'  => __('Get started with Mercury Schema', 'mercury-schema'),
            'welcomeText'   => __('This short setup adds the right structured data to your site, so search engines and AI answers understand it. It takes about a minute.', 'mercury-schema'),
            'welcomePoints' => [
                __('Site-wide basics set up for you: organization, website, pages and breadcrumbs', 'mercury-schema'),
                __('Works with the block editor, classic editor, Elementor and WooCommerce', 'mercury-schema'),
                __('Tiny footprint: no front-end scripts, one database row per post', 'mercury-schema'),
            ],
            'start'         => __('Start setup', 'mercury-schema'),
            'skip'          => __('Skip and use the Basic setup', 'mercury-schema'),
            'presetTitle'   => __('Choose your schema setup', 'mercury-schema'),
            'presetText'    => __('Pick a starting point. You can switch any schema type on or off later under Schema Types.', 'mercury-schema'),
            'presets'       => [
                'basic'  => [__('Basic', 'mercury-schema'), __('The core schemas every site needs. Lightweight and zero configuration.', 'mercury-schema')],
                'smart'  => [__('Smart', 'mercury-schema'), __('Our pick for most sites. Covers content, products and local search to qualify for more rich results.', 'mercury-schema')],
                'custom' => [__('Custom', 'mercury-schema'), __('Hand-pick every schema type Mercury outputs. For sites with specific structured data needs.', 'mercury-schema')],
            ],
            'recommended'   => __('Recommended', 'mercury-schema'),
            /* translators: %s: number of schema types */
            'nSchemas'      => __('%s schemas', 'mercury-schema'),
            'youChoose'     => __('You choose', 'mercury-schema'),
            'typesTitle'    => __('Turn on the schemas you need', 'mercury-schema'),
            'typesText'     => __('Switched-off types are hidden from the editor and not printed. Their saved data is kept.', 'mercury-schema'),
            'dashTitle'     => __('Schema Types', 'mercury-schema'),
            'dashText'      => __('Choose which schema types your site uses. Changes save instantly.', 'mercury-schema'),
            'enableAll'     => __('Enable all', 'mercury-schema'),
            /* translators: 1: enabled count, 2: total count */
            'countOn'       => __('%1$s / %2$s on', 'mercury-schema'),
            'groups'        => [
                'foundations' => __('Site foundations', 'mercury-schema'),
                'content'     => __('Content', 'mercury-schema'),
                'commerce'    => __('Commerce', 'mercury-schema'),
                'local'       => __('Local & events', 'mercury-schema'),
                'other'       => __('Other', 'mercury-schema'),
            ],
            'doneTitle'     => __('You’re all set', 'mercury-schema'),
            'doneText'      => __('Mercury Schema is live on your site. Here’s what to do next.', 'mercury-schema'),
            /* translators: 1: preset name, 2: number of schema types */
            'summary'       => __('%1$s · %2$s schemas active', 'mercury-schema'),
            'addedSiteWide' => __('Added site-wide for you:', 'mercury-schema'),
            'guides'        => [
                ['tag' => __('Content', 'mercury-schema'), 'title' => __('Add schema to a post', 'mercury-schema'), 'desc' => __('Open a post and use the Schema Markup box to add an FAQ, recipe, product and more.', 'mercury-schema'), 'cta' => __('Open a post', 'mercury-schema'), 'url' => 'post'],
                ['tag' => __('Site-wide', 'mercury-schema'), 'title' => __('Review site-wide schemas', 'mercury-schema'), 'desc' => __('Fill in your logo, social profiles and contact details for the Organization schema.', 'mercury-schema'), 'cta' => __('Review schemas', 'mercury-schema'), 'url' => 'siteWide'],
                ['tag' => __('Testing', 'mercury-schema'), 'title' => __('Test with Google', 'mercury-schema'), 'desc' => __('Check your home page in Google’s Rich Results Test once the site is public.', 'mercury-schema'), 'cta' => __('Open the test', 'mercury-schema'), 'url' => 'test', 'external' => true],
            ],
            'previous'      => __('← Previous', 'mercury-schema'),
            'next'          => __('Next →', 'mercury-schema'),
            'finish'        => __('Finish setup', 'mercury-schema'),
            'goTypes'       => __('Go to Schema Types', 'mercury-schema'),
            'saving'        => __('Saving…', 'mercury-schema'),
            /* translators: %s: schema type label */
            'enabledToast'  => __('%s enabled', 'mercury-schema'),
            /* translators: %s: schema type label */
            'disabledToast' => __('%s switched off', 'mercury-schema'),
            'allOn'         => __('All schema types enabled', 'mercury-schema'),
            'allOff'        => __('All schema types switched off', 'mercury-schema'),
            'rerun'         => __('Run setup wizard again', 'mercury-schema'),
            'rerunConfirm'  => __('Run the setup wizard again? Your schemas and enabled types are kept until you finish it.', 'mercury-schema'),
            'error'         => __('Something went wrong. Please try again.', 'mercury-schema'),
        ];
    }
}
