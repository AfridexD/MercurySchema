<?php
/**
 * Stored plugin settings (one option) and the form that edits them on
 * Mercury Schema → Settings. Pages are registered in Pages.
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

    public function renderForm(): void
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
