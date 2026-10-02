<?php
/**
 * Metabox shell. editor-ui.js renders the UI into #mercury-schema-app.
 *
 * @package MercurySchema
 * @var \WP_Post $post
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="mercury-schema-app" class="ms-app" data-post-id="<?php echo esc_attr((string) $post->ID); ?>">
    <p class="ms-muted"><?php esc_html_e('Loading…', 'mercury-schema'); ?></p>
</div>
<noscript>
    <p><?php esc_html_e('Mercury Schema needs JavaScript to edit schema markup.', 'mercury-schema'); ?></p>
</noscript>
