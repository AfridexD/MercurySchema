<?php
/**
 * Plugin Name:       UnlimitedSchema
 * Plugin URI:        https://unlimitedschema.com
 * Description:       Lightweight, conflict-free JSON-LD schema markup for WordPress.
 * Version:           1.0.0
 * Author:            AfridexD
 * Author URI:        https://github.com/AfridexD
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       unlimited-schema
 * Domain Path:       /languages
 * Requires PHP:      8.0
 * Requires at least: 6.0
 *
 * @package UnlimitedSchema
 */

if (!defined('ABSPATH')) {
    exit;
}

define('UNLIMITED_SCHEMA_VERSION', '1.0.0');
define('UNLIMITED_SCHEMA_FILE', __FILE__);
define('UNLIMITED_SCHEMA_PATH', plugin_dir_path(__FILE__));
define('UNLIMITED_SCHEMA_URL', plugin_dir_url(__FILE__));

// PSR-4 autoloader: UnlimitedSchema\Foo\Bar => src/Foo/Bar.php. No Composer needed at runtime.
spl_autoload_register(static function ($class) {
    $prefix = 'UnlimitedSchema\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = UNLIMITED_SCHEMA_PATH . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, [\UnlimitedSchema\Loader::class, 'activate']);

\UnlimitedSchema\Loader::getInstance()->run();
