<?php
/**
 * Plugin settings: stored in one option, edited on Settings → UnlimitedSchema.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\Admin;

class Settings
{
    public const OPTION = 'unlimited_schema_settings';
    public const PAGE = 'unlimited-schema';

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
            __('UnlimitedSchema', 'unlimited-schema'),
            __('UnlimitedSchema', 'unlimited-schema'),
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

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = self::all();
        $postTypes = get_post_types(['public' => true], 'objects');
        unset($postTypes['attachment']);
        $name = self::OPTION;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('UnlimitedSchema', 'unlimited-schema'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields(self::PAGE); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Post types', 'unlimited-schema'); ?></th>
                        <td>
                            <fieldset>
                                <?php foreach ($postTypes as $type) : ?>
                                    <label>
                                        <input type="checkbox" name="<?php echo esc_attr($name); ?>[post_types][]" value="<?php echo esc_attr($type->name); ?>" <?php checked(in_array($type->name, $settings['post_types'], true)); ?>>
                                        <?php echo esc_html($type->labels->name); ?>
                                    </label><br>
                                <?php endforeach; ?>
                                <p class="description"><?php esc_html_e('Show the Schema Markup box on these post types.', 'unlimited-schema'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Output location', 'unlimited-schema'); ?></th>
                        <td>
                            <label><input type="radio" name="<?php echo esc_attr($name); ?>[output_location]" value="head" <?php checked($settings['output_location'], 'head'); ?>> <?php esc_html_e('Page head (recommended)', 'unlimited-schema'); ?></label><br>
                            <label><input type="radio" name="<?php echo esc_attr($name); ?>[output_location]" value="footer" <?php checked($settings['output_location'], 'footer'); ?>> <?php esc_html_e('Page footer', 'unlimited-schema'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Debug logging', 'unlimited-schema'); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[debug]" value="1" <?php checked($settings['debug']); ?>> <?php esc_html_e('Log skipped or invalid schemas to the PHP error log', 'unlimited-schema'); ?></label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
