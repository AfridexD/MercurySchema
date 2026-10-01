<?php
/**
 * Callbacks for the /unlimited-schema/v1 REST routes.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\API;

use UnlimitedSchema\Core\Schema;
use UnlimitedSchema\Core\SchemaType;
use UnlimitedSchema\Frontend\SchemaRegistry;
use UnlimitedSchema\Helpers\PostMetaStore;
use UnlimitedSchema\Helpers\Sanitizer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class SchemaController
{
    private SchemaRegistry $registry;
    private PostMetaStore $store;

    public function __construct(SchemaRegistry $registry, PostMetaStore $store)
    {
        $this->registry = $registry;
        $this->store = $store;
    }

    public function checkPermission(WP_REST_Request $request)
    {
        $capability = (string) apply_filters(Hooks::REST_CAPABILITY, 'manage_options');
        if (!current_user_can($capability)) {
            return new WP_Error('rest_forbidden', __('You are not allowed to manage schema markup.', 'unlimited-schema'), ['status' => rest_authorization_required_code()]);
        }

        $postId = (int) $request->get_param('post_id');
        if ($postId && get_post($postId) && !current_user_can('edit_post', $postId)) {
            return new WP_Error('rest_forbidden', __('You are not allowed to edit this post.', 'unlimited-schema'), ['status' => rest_authorization_required_code()]);
        }
        return true;
    }

    /** GET /schemas */
    public function listTypes(): WP_REST_Response
    {
        $types = array_map(static fn(SchemaType $t) => $t->toArray(), array_values($this->registry->all()));
        return new WP_REST_Response(['types' => $types]);
    }

    /** GET /schema-types */
    public function getTypes(): WP_REST_Response
    {
        $types = [];
        foreach ($this->registry->all() as $name => $type) {
            $definition = $type->toArray();
            unset($definition['type']);
            $types[$name] = $definition;
        }
        return new WP_REST_Response($types);
    }

    /** GET /schemas/{post_id} */
    public function getPostSchemas(WP_REST_Request $request)
    {
        $postId = $this->postId($request);
        if (is_wp_error($postId)) {
            return $postId;
        }
        $doc = $this->store->get($postId);
        return new WP_REST_Response(['post_id' => $postId] + $doc);
    }

    /** POST /schemas/{post_id} */
    public function createSchema(WP_REST_Request $request)
    {
        $postId = $this->postId($request);
        if (is_wp_error($postId)) {
            return $postId;
        }

        $type = $this->type($request->get_param('type'));
        if (is_wp_error($type)) {
            return $type;
        }

        $data = $request->get_param('data');
        $data = is_array($data) && $data ? $data : $type->getDefaults();

        $schema = (new Schema($type->getName()))
            ->setData($data)
            ->setConditions((array) ($request->get_param('conditions') ?? []))
            ->setEnabled($request->get_param('enabled') ?? true);

        $saved = $this->persist($postId, $schema, null);
        if (is_wp_error($saved)) {
            return $saved;
        }
        return new WP_REST_Response(['success' => true, 'schema' => $saved], 201);
    }

    /** PUT /schemas/{post_id}/{schema_id} */
    public function updateSchema(WP_REST_Request $request)
    {
        $postId = $this->postId($request);
        if (is_wp_error($postId)) {
            return $postId;
        }

        $schemaId = (string) $request->get_param('schema_id');
        $existing = $this->find($postId, $schemaId);
        if ($existing === null) {
            return $this->notFound();
        }

        $schema = Schema::fromArray($existing);
        $typeName = $request->get_param('type') ?? $schema->getType();
        $type = $this->type($typeName);
        if (is_wp_error($type)) {
            return $type;
        }

        $typeChanged = $type->getName() !== $schema->getType();
        $schema = Schema::fromArray(['type' => $type->getName()] + $schema->toArray());
        if ($request->has_param('data')) {
            $schema->setData((array) $request->get_param('data'));
        } elseif ($typeChanged) {
            $schema->setData($type->getDefaults());
        }
        if ($request->has_param('conditions')) {
            $schema->setConditions((array) $request->get_param('conditions'));
        }
        if ($request->has_param('enabled')) {
            $schema->setEnabled(rest_sanitize_boolean($request->get_param('enabled')));
        }

        $saved = $this->persist($postId, $schema, $schemaId);
        if (is_wp_error($saved)) {
            return $saved;
        }
        return new WP_REST_Response(['success' => true, 'schema' => $saved]);
    }

    /** DELETE /schemas/{post_id}/{schema_id} */
    public function deleteSchema(WP_REST_Request $request)
    {
        $postId = $this->postId($request);
        if (is_wp_error($postId)) {
            return $postId;
        }

        $schemaId = (string) $request->get_param('schema_id');
        $doc = $this->store->get($postId);
        $remaining = array_filter($doc['schemas'], static fn($s) => ($s['id'] ?? '') !== $schemaId);
        if (count($remaining) === count($doc['schemas'])) {
            return $this->notFound();
        }

        if (!$this->store->save($postId, $remaining)) {
            return new WP_Error('unlimited_schema_save_failed', __('Could not save schema data.', 'unlimited-schema'), ['status' => 500]);
        }
        do_action(Hooks::SCHEMA_DELETED, $schemaId, $postId);
        return new WP_REST_Response(['success' => true, 'deleted' => $schemaId]);
    }

    /** POST /validate */
    public function validate(WP_REST_Request $request)
    {
        $type = $this->type($request->get_param('type'));
        if (is_wp_error($type)) {
            return $type;
        }
        $schema = (new Schema($type->getName()))->setData((array) ($request->get_param('data') ?? []));
        return new WP_REST_Response($this->registry->validator()->validate($schema));
    }

    /**
     * Validate (partially: required fields may still be empty while editing),
     * sanitize, and write the schema into the post's document.
     *
     * @return array|WP_Error The saved schema.
     */
    private function persist(int $postId, Schema $schema, ?string $replaceId)
    {
        $result = $this->registry->validator()->validate($schema, true);
        if (!$result['valid']) {
            return new WP_Error('unlimited_schema_invalid', __('Schema data is invalid.', 'unlimited-schema'), ['status' => 400, 'errors' => $result['errors']]);
        }

        $type = $this->registry->get($schema->getType());
        $schema->setData(Sanitizer::data($type, $schema->getData()))
            ->setConditions(Sanitizer::conditions($schema->getConditions()));
        $saved = $schema->toArray();

        $schemas = $this->store->get($postId)['schemas'];
        if ($replaceId === null) {
            $schemas[] = $saved;
        } else {
            foreach ($schemas as $i => $existing) {
                if (($existing['id'] ?? '') === $replaceId) {
                    $schemas[$i] = $saved;
                }
            }
        }

        if (!$this->store->save($postId, $schemas)) {
            return new WP_Error('unlimited_schema_save_failed', __('Could not save schema data.', 'unlimited-schema'), ['status' => 500]);
        }
        do_action(Hooks::SCHEMA_SAVED, $saved, $postId);
        return $saved;
    }

    /** @return int|WP_Error */
    private function postId(WP_REST_Request $request)
    {
        $postId = (int) $request->get_param('post_id');
        $post = $postId ? get_post($postId) : null;
        if (!$post || $post->post_type === 'revision') {
            return new WP_Error('unlimited_schema_post_not_found', __('Post not found.', 'unlimited-schema'), ['status' => 404]);
        }
        return $postId;
    }

    /** @return SchemaType|WP_Error */
    private function type($name)
    {
        $type = is_string($name) && $name !== '' ? $this->registry->get($name) : null;
        if ($type === null) {
            $message = is_string($name) && $name !== ''
                /* translators: %s: schema type name */
                ? sprintf(__('Unknown schema type: %s', 'unlimited-schema'), $name)
                : __('Schema type is required.', 'unlimited-schema');
            return new WP_Error('unlimited_schema_invalid_type', $message, ['status' => 400]);
        }
        return $type;
    }

    private function find(int $postId, string $schemaId): ?array
    {
        foreach ($this->store->get($postId)['schemas'] as $schema) {
            if (($schema['id'] ?? '') === $schemaId) {
                return $schema;
            }
        }
        return null;
    }

    private function notFound(): WP_Error
    {
        return new WP_Error('unlimited_schema_not_found', __('Schema not found.', 'unlimited-schema'), ['status' => 404]);
    }
}
