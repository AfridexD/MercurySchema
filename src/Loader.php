<?php
/**
 * Plugin bootstrap. Registers hooks only; every object is built lazily
 * when its hook actually fires.
 *
 * @package MercurySchema
 */

namespace MercurySchema;

use MercurySchema\Admin\AdminController;
use MercurySchema\Admin\Settings;
use MercurySchema\API\REST;
use MercurySchema\API\SchemaController;
use MercurySchema\Frontend\SchemaOutput;
use MercurySchema\Frontend\SchemaRegistry;
use MercurySchema\Helpers\GlobalStore;
use MercurySchema\Helpers\Migrator;
use MercurySchema\Helpers\SetupState;
use MercurySchema\API\SetupController;
use MercurySchema\Helpers\PostMetaStore;

class Loader
{
    private static ?Loader $instance = null;

    private ?SchemaRegistry $registry = null;
    private ?PostMetaStore $store = null;
    private ?GlobalStore $global = null;
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
        // Translations load automatically (WordPress 4.6+); no load_plugin_textdomain() needed.
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('plugins_loaded', [Integrations\WooCommerce::class, 'register']);

        if (is_admin()) {
            (new AdminController())->registerHooks();
        } else {
            $hook = Settings::get('output_location') === 'footer' ? 'wp_footer' : 'wp_head';
            add_action($hook, [$this, 'outputSchemaMarkup'], 20);
        }
    }

    public static function activate(): void
    {
        $migrated = Migrator::run(); // Before defaults, so migrated settings win.
        SchemaRegistry::seed();
        add_option(Settings::OPTION, Settings::defaults());

        if ($migrated['options'] || $migrated['meta_rows']) {
            // Upgrading from UnlimitedSchema: already configured, keep every type on, no wizard.
            add_option(SetupState::COMPLETE, true);
        } elseif (!SetupState::isComplete()) {
            set_transient(SetupState::REDIRECT, 1, 60);
        }
    }

    public function registerRestRoutes(): void
    {
        (new REST(new SchemaController($this->registry(), $this->store(), $this->globalStore(), $this->output())))->register();
        (new SetupController($this->registry(), $this->globalStore()))->register();
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

    public function globalStore(): GlobalStore
    {
        return $this->global ??= new GlobalStore();
    }

    public function output(): SchemaOutput
    {
        return $this->output ??= new SchemaOutput($this->registry(), $this->store(), $this->globalStore());
    }
}
