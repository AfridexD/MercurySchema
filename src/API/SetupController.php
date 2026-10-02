<?php
/**
 * Setup wizard and Schema Types screen: /mercury-schema/v1/setup routes.
 * Administrators only (the global capability).
 *
 * @package MercurySchema
 */

namespace MercurySchema\API;

use MercurySchema\Core\Schema;
use MercurySchema\Frontend\SchemaRegistry;
use MercurySchema\Helpers\GlobalStore;
use MercurySchema\Helpers\Sanitizer;
use MercurySchema\Helpers\SetupState;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class SetupController
{
    private SchemaRegistry $registry;
    private GlobalStore $global;

    public function __construct(SchemaRegistry $registry, GlobalStore $global)
    {
        $this->registry = $registry;
        $this->global = $global;
    }

    public function register(): void
    {
        $permission = [$this, 'checkPermission'];

        register_rest_route(REST::NAMESPACE, '/setup', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'status'],
                'permission_callback' => $permission,
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'save'],
                'permission_callback' => $permission,
                'args'                => [
                    'preset'        => ['type' => 'string', 'enum' => SetupState::PRESETS],
                    'enabled_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'complete'      => ['type' => 'boolean'],
                ],
            ],
        ]);

        register_rest_route(REST::NAMESPACE, '/setup/reset', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'reset'],
            'permission_callback' => $permission,
        ]);
    }

    public function checkPermission()
    {
        if (!current_user_can(SchemaController::globalCapability())) {
            return new WP_Error('rest_forbidden', __('You are not allowed to configure Mercury Schema.', 'mercury-schema'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /** GET /setup */
    public function status(): WP_REST_Response
    {
        $types = [];
        foreach ($this->registry->all() as $name => $type) {
            $arr = $type->toArray();
            $types[] = [
                'type'        => $name,
                'label'       => $arr['label'],
                'description' => $arr['description'],
                'icon'        => $arr['icon'],
                'group'       => $arr['group'] ?: 'other',
                'enabled'     => $this->registry->isEnabled($name),
            ];
        }
        return new WP_REST_Response(SetupState::status() + [
            'types'   => $types,
            'presets' => SetupState::PRESET_TYPES,
        ]);
    }

    /**
     * POST /setup — save the preset and/or enabled types; with complete=true,
     * finish the wizard and add the site-wide foundation schemas.
     */
    public function save(WP_REST_Request $request)
    {
        $preset = $request->get_param('preset');
        $types = $request->get_param('enabled_types');

        if ($types === null && is_string($preset) && isset(SetupState::PRESET_TYPES[$preset])) {
            $types = SetupState::PRESET_TYPES[$preset];
        }
        if (is_array($types)) {
            $unknown = array_diff($types, array_keys($this->registry->all()));
            if ($unknown) {
                /* translators: %s: comma-separated type names */
                return new WP_Error('mercury_schema_invalid_type', sprintf(__('Unknown schema types: %s', 'mercury-schema'), implode(', ', $unknown)), ['status' => 400]);
            }
            $before = SetupState::enabledTypes();
            SetupState::saveEnabledTypes($types);
            if ($before !== SetupState::enabledTypes()) {
                do_action(Hooks::TYPES_UPDATED, SetupState::enabledTypes(), $before);
            }
        }
        if (is_string($preset)) {
            update_option(SetupState::PRESET, $preset, false);
        }

        $created = [];
        if ($request->get_param('complete')) {
            if (SetupState::enabledTypes() === null) {
                SetupState::saveEnabledTypes(SetupState::PRESET_TYPES['basic']);
            }
            $created = $this->addFoundationSchemas();
            update_option(SetupState::COMPLETE, true, true);
            update_option(SetupState::COMPLETED_AT, gmdate('c'), false);
        }

        return new WP_REST_Response(['success' => true, 'site_wide_added' => $created] + SetupState::status());
    }

    /** POST /setup/reset — show the wizard again. Schemas and enabled types are kept. */
    public function reset(): WP_REST_Response
    {
        SetupState::reset();
        return new WP_REST_Response(['success' => true] + SetupState::status());
    }

    /**
     * Create site-wide schemas for enabled foundation types that don't have one yet.
     *
     * @return string[] Types added.
     */
    private function addFoundationSchemas(): array
    {
        $schemas = $this->global->get()['schemas'];
        $existing = array_column($schemas, 'type');
        $added = [];

        foreach (SetupState::AUTO_SITE_WIDE as $name => $conditions) {
            $type = $this->registry->get($name);
            if (!$type || in_array($name, $existing, true) || !$this->registry->isEnabled($name)) {
                continue;
            }
            $schema = (new Schema($name))
                ->setData(Sanitizer::data($type, $type->getDefaults()))
                ->setConditions(Sanitizer::conditions($conditions));
            $schemas[] = $schema->toArray();
            $added[] = $name;
        }

        if ($added) {
            $this->global->save($schemas);
        }
        return $added;
    }
}
