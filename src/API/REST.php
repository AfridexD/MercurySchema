<?php
/**
 * Registers the unlimited-schema/v1 REST routes.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\API;

use WP_REST_Server;

class REST
{
    public const NAMESPACE = 'unlimited-schema/v1';

    private SchemaController $controller;

    public function __construct(SchemaController $controller)
    {
        $this->controller = $controller;
    }

    public function register(): void
    {
        $c = $this->controller;
        $permission = [$c, 'checkPermission'];
        $postId = ['type' => 'integer', 'required' => true, 'minimum' => 1];
        $schemaId = ['type' => 'string', 'required' => true, 'pattern' => '^[A-Za-z0-9_-]+$'];

        register_rest_route(self::NAMESPACE, '/schemas', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$c, 'listTypes'],
            'permission_callback' => $permission,
        ]);

        register_rest_route(self::NAMESPACE, '/schema-types', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$c, 'getTypes'],
            'permission_callback' => $permission,
        ]);

        register_rest_route(self::NAMESPACE, '/schemas/(?P<post_id>\d+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$c, 'getPostSchemas'],
                'permission_callback' => $permission,
                'args'                => ['post_id' => $postId],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$c, 'createSchema'],
                'permission_callback' => $permission,
                'args'                => [
                    'post_id'    => $postId,
                    'type'       => ['type' => 'string', 'required' => true],
                    'data'       => ['type' => 'object'],
                    'conditions' => ['type' => 'object'],
                    'enabled'    => ['type' => 'boolean'],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/schemas/(?P<post_id>\d+)/(?P<schema_id>[A-Za-z0-9_-]+)', [
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$c, 'updateSchema'],
                'permission_callback' => $permission,
                'args'                => [
                    'post_id'    => $postId,
                    'schema_id'  => $schemaId,
                    'type'       => ['type' => 'string'],
                    'data'       => ['type' => 'object'],
                    'conditions' => ['type' => 'object'],
                    'enabled'    => ['type' => 'boolean'],
                ],
            ],
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$c, 'deleteSchema'],
                'permission_callback' => $permission,
                'args'                => ['post_id' => $postId, 'schema_id' => $schemaId],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/validate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$c, 'validate'],
            'permission_callback' => $permission,
            'args'                => [
                'type' => ['type' => 'string', 'required' => true],
                'data' => ['type' => 'object'],
            ],
        ]);
    }
}
