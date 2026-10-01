<?php
/**
 * Plugin bootstrap. Registers hooks only; every object is built lazily
 * when its hook actually fires.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema;

use UnlimitedSchema\Admin\AdminController;
use UnlimitedSchema\Admin\Settings;
use UnlimitedSchema\API\REST;
use UnlimitedSchema\API\SchemaController;
use UnlimitedSchema\Frontend\SchemaOutput;
use UnlimitedSchema\Frontend\SchemaRegistry;
use UnlimitedSchema\Helpers\PostMetaStore;

class Loader
{
    private static ?Loader $instance = null;

    private ?SchemaRegistry $registry = null;
    private ?PostMetaStore $store = null;
    private ?SchemaOutput $output = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    public function run(): void
    {
        add_action('init', [$this, 'loadTextDomain']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        if (is_admin()) {
            (new AdminController())->registerHooks();
        } else {
            $hook = Settings::get('output_location') === 'footer' ? 'wp_footer' : 'wp_head';
            add_action($hook, [$this, 'outputSchemaMarkup'], 20);
        }
    }

    public static function activate(): void
    {
        SchemaRegistry::seed();
        add_option(Settings::OPTION, Settings::defaults());
    }

    public function loadTextDomain(): void
    {
        load_plugin_textdomain('unlimited-schema', false, dirname(plugin_basename(UNLIMITED_SCHEMA_FILE)) . '/languages');
    }

    public function registerRestRoutes(): void
    {
        (new REST(new SchemaController($this->registry(), $this->store())))->register();
    }

    public function outputSchemaMarkup(): void
    {
        $this->output()->render();
    }

    public function registry(): SchemaRegistry
    {
        return $this->registry ??= new SchemaRegistry();
    }

    public function store(): PostMetaStore
    {
        return $this->store ??= new PostMetaStore();
    }

    public function output(): SchemaOutput
    {
        return $this->output ??= new SchemaOutput($this->registry(), $this->store());
    }
}
