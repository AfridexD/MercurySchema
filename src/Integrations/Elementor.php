<?php
/**
 * Elementor: open the schema editor from Elementor's own editor, where the
 * WordPress Schema Markup box is not shown. Loads only inside the Elementor
 * editor and reuses the regular editor (admin/js/editor-ui.js).
 *
 * @package MercurySchema
 */

namespace MercurySchema\Integrations;

use MercurySchema\Admin\AdminController;
use MercurySchema\Admin\Brand;

class Elementor
{
    public static function register(): void
    {
        if (!did_action('elementor/loaded')) {
            return;
        }
        add_action('elementor/editor/after_enqueue_scripts', [self::class, 'enqueue']);
        add_action('elementor/editor/after_enqueue_styles', [self::class, 'enqueueStyles']);
    }

    private static function post(): ?\WP_Post
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which post Elementor is editing.
        $post = get_post(isset($_GET['post']) ? (int) $_GET['post'] : 0);
        if (!$post || !AdminController::canManage() || !current_user_can('edit_post', $post->ID)
            || !in_array($post->post_type, AdminController::postTypes(), true)) {
            return null;
        }
        return $post;
    }

    public static function enqueueStyles(): void
    {
        if (!self::post()) {
            return;
        }
        // Elementor's editor doesn't load the admin button and form styles the editor uses.
        wp_enqueue_style('mercury-schema-admin', MERCURY_SCHEMA_URL . 'admin/css/editor-ui.css', ['dashicons', 'buttons', 'forms'], MERCURY_SCHEMA_VERSION);
    }

    public static function enqueue(): void
    {
        $post = self::post();
        if (!$post) {
            return;
        }
        AdminController::enqueueEditor([
            'scope'       => 'post',
            'postId'      => (int) $post->ID,
            'testUrl'     => $post->post_status === 'publish' ? get_permalink($post) : '',
            'settingsUrl' => AdminController::canManageGlobal() ? \MercurySchema\Admin\Pages::url(\MercurySchema\Admin\Pages::SITE_WIDE) : '',
            'typesUrl'    => AdminController::canManageGlobal() ? \MercurySchema\Admin\Pages::url() : '',
        ]);
        wp_enqueue_script('mercury-schema-elementor', MERCURY_SCHEMA_URL . 'admin/js/elementor.js', ['mercury-schema-admin'], MERCURY_SCHEMA_VERSION, true);
        wp_localize_script('mercury-schema-elementor', 'MercurySchemaElementor', [
            'logo' => Brand::mark('#fff'),
            'i18n' => [
                'button'   => __('Schema Markup', 'mercury-schema'),
                'title'    => __('Schema Markup', 'mercury-schema'),
                /* translators: %s: post title */
                'subtitle' => sprintf(__('Mercury Schema · %s', 'mercury-schema'), wp_strip_all_tags(get_the_title($post)) ?: __('(no title)', 'mercury-schema')),
                'close'    => __('Close', 'mercury-schema'),
            ],
        ]);
    }
}
