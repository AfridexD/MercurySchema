<?php
/**
 * Plugin settings page (Settings → Mercury Schema): a "Site-wide schemas"
 * tab hosting the JS editor, and a "Settings" tab for the stored options.
 *
 * @package MercurySchema
 */

namespace MercurySchema\Admin;

class Settings
{
    public const OPTION = 'mercury_schema_settings';
    public const PAGE = 'mercury-schema';

    public static function defaults(): array
    {
        return [
            'post_types'      => ['post', 'page'],
            'output_location' => 'head',
            'debug'           => false,
        ];
    }

    public static function all(): array
    {
        $stored = get_option(self::OPTION, []);
        return array_merge(self::defaults(), is_array($stored) ? $stored : []);
    }

    public static function get(string $key)
    {
        return self::all()[$key] ?? null;
    }

    public function register(): void
    {
        register_setting(self::PAGE, self::OPTION, [
            'type'              => 'object',
            'sanitize_callback' => [$this, 'sanitize'],
            'default'           => self::defaults(),
        ]);
    }

    public function addPage(): void
    {
        add_options_page(
            __('Mercury Schema', 'mercury-schema'),
            __('Mercury Schema', 'mercury-schema'),
            'manage_options',
            self::PAGE,
            [$this, 'renderPage']
        );
    }

    public function sanitize($input): array
    {
        $input = is_array($input) ? $input : [];
        $public = get_post_types(['public' => true]);
        $postTypes = array_map('sanitize_key', (array) ($input['post_types'] ?? []));

        return [
            'post_types'      => array_values(array_intersect($postTypes, $public)),
            'output_location' => ($input['output_location'] ?? '') === 'footer' ? 'footer' : 'head',
            'debug'           => !empty($input['debug']),
        ];
    }

    public static function url(string $tab = ''): string
    {
        $url = admin_url('options-general.php?page=' . self::PAGE);
        return $tab ? add_query_arg('tab', $tab, $url) : $url;
    }

    public static function currentTab(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch.
        return isset($_GET['tab']) && $_GET['tab'] === 'settings' ? 'settings' : 'schemas';
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $tab = self::currentTab();
        $tabs = [
            'schemas'  => __('Site-wide schemas', 'mercury-schema'),
            'settings' => __('Settings', 'mercury-schema'),
        ];
        ?>
        <div class="wrap ms-page">
            <header class="ms-page__head">
                <span class="ms-page__logo dashicons dashicons-editor-code" aria-hidden="true"></span>
                <div>
                    <h1><?php esc_html_e('Mercury Schema', 'mercury-schema'); ?> <span class="ms-page__ver"><?php echo esc_html(MERCURY_SCHEMA_VERSION); ?></span></h1>
                    <p><?php esc_html_e('Lightweight JSON-LD structured data for rich results.', 'mercury-schema'); ?></p>
                </div>
            </header>
            <nav class="nav-tab-wrapper ms-page__tabs" aria-label="<?php esc_attr_e('Mercury Schema sections', 'mercury-schema'); ?>">
                <?php foreach ($tabs as $key => $label) : ?>
                    <a href="<?php echo esc_url(self::url($key === 'schemas' ? '' : $key)); ?>" class="nav-tab<?php echo $tab === $key ? ' nav-tab-active' : ''; ?>"<?php echo $tab === $key ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($tab === 'schemas') : ?>
                <div id="mercury-schema-app" class="ms-app ms-app--page">
                    <p class="ms-muted"><?php esc_html_e('Loading…', 'mercury-schema'); ?></p>
                </div>
                <noscript><p><?php esc_html_e('Mercury Schema needs JavaScript to edit schema markup.', 'mercury-schema'); ?></p></noscript>
            <?php else : ?>
                <?php $this->renderSettingsForm(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function renderSettingsForm(): void
    {
        $settings = self::all();
        $postTypes = get_post_types(['public' => true], 'objects');
        unset($postTypes['attachment']);
        $name = self::OPTION;
        ?>
            <form method="post" action="options.php" class="ms-settings">
                <?php settings_fields(self::PAGE); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Post types', 'mercury-schema'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($postTypes as $type) : ?>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr($name); ?>[post_types][]" value="<?php echo esc_attr($type->name); ?>" <?php checked(in_array($type->name, $settings['post_types'], true)); ?>>
                                        <?php echo esc_html($type->labels->name); ?>
                                    </label><br>
                                <?php endforeach; ?>
                                <p class="description"><?php esc_html_e('Show the Schema Markup box on these post types.', 'mercury-schema'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Output location', 'mercury-schema'); ?></th>
                        <td>
                            <label><input type="radio" name="<?php echo esc_attr($name); ?>[output_location]" value="head" <?php checked($settings['output_location'], 'head'); ?>> <?php esc_html_e('Page head (recommended)', 'mercury-schema'); ?></label><br>
                            <label><input type="radio" name="<?php echo esc_attr($name); ?>[output_location]" value="footer" <?php checked($settings['output_location'], 'footer'); ?>> <?php esc_html_e('Page footer', 'mercury-schema'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Debug logging', 'mercury-schema'); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[debug]" value="1" <?php checked($settings['debug']); ?>> <?php esc_html_e('Log skipped or invalid schemas to the PHP error log', 'mercury-schema'); ?></label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        <?php
    }
}
