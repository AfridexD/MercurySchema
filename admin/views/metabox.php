<?php
/**
 * Metabox shell. editor-ui.js renders the UI into #unlimited-schema-app.
 *
 * @package UnlimitedSchema
 * @var \WP_Post $post
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="unlimited-schema-app" class="us-app" data-post-id="<?php echo esc_attr((string) $post->ID); ?>">
    <p class="us-muted"><?php esc_html_e('Loading…', 'unlimited-schema'); ?></p>
</div>
<noscript>
    <p><?php esc_html_e('UnlimitedSchema needs JavaScript to edit schema markup.', 'unlimited-schema'); ?></p>
</noscript>
