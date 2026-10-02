<?php
/**
 * Callbacks for the /unlimited-schema/v1 REST routes.
 *
 * Two scopes share the same callbacks: a post (routes with {post_id}) and
 * site-wide (/global routes, no post_id). Internally site-wide is post ID 0.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\API;

use UnlimitedSchema\Core\Schema;
use UnlimitedSchema\Core\SchemaType;
use UnlimitedSchema\Frontend\SchemaOutput;
use UnlimitedSchema\Frontend\SchemaRegistry;
use UnlimitedSchema\Helpers\GlobalStore;
use UnlimitedSchema\Helpers\PostMetaStore;
use UnlimitedSchema\Helpers\Sanitizer;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class SchemaController
{
    private SchemaRegistry $registry;
    private PostMetaStore $store;
    private GlobalStore $global;
    private SchemaOutput $output;

    public function __construct(SchemaRegistry $registry, PostMetaStore $store, GlobalStore $global, SchemaOutput $output)
    {
        $this->registry = $registry;
        $this->store = $store;
        $this->global = $global;
        $this->output = $output;
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

    /** GET /schemas/{post_id} and GET /global */
    public function getPostSchemas(WP_REST_Request $request)
    {
        $postId = $this->postId($request);
        if (is_wp_error($postId)) {
            return $postId;
        }

        $doc = $this->load($postId);
        $validator = $this->registry->validator();
        $status = [];
        foreach ($doc['schemas'] as $raw) {
            // Tokens count as filled in here; they are re-checked at render time.
            $status[$raw['id'] ?? ''] = $validator->validate(Schema::fromArray($raw));
        }

        $response = ['post_id' => $postId] + $doc + ['status' => (object) $status];
        if ($postId) {
            // Site-wide schemas whose display rules match this post.
            $post = get_post($postId);
            $response['site_wide'] = [];
            foreach ($this->global->get()['schemas'] as $s) {
                if ($this->output->appliesTo($s, $post)) {
                    $response['site_wide'][] = ['id' => $s['id'] ?? '', 'type' => $s['type'], 'enabled' => true];
                }
            }
        }
        return new WP_REST_Response($response);
    }

    /** POST /schemas/{post_id} and POST /global */
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
        return new WP_REST_Response(['success' => true, 'schema' => $saved, 'status' => $this->status($saved)], 201);
    }

    /** PUT /schemas/{post_id}/{schema_id} and PUT /global/{schema_id} */
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
        $type = $this->type($request->get_param('type') ?? $schema->getType());
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
        return new WP_REST_Response(['success' => true, 'schema' => $saved, 'status' => $this->status($saved)]);
    }

    /** DELETE /schemas/{post_id}/{schema_id} and DELETE /global/{schema_id} */
    public function deleteSchema(WP_REST_Request $request)
    {
        $postId = $this->postId($request);
        if (is_wp_error($postId)) {
            return $postId;
        }

        $schemaId = (string) $request->get_param('schema_id');
        $schemas = $this->load($postId)['schemas'];
        $remaining = array_filter($schemas, static fn($s) => ($s['id'] ?? '') !== $schemaId);
        if (count($remaining) === count($schemas)) {
            return $this->notFound();
        }

        if (!$this->write($postId, $remaining)) {
            return $this->saveFailed();
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

    /** POST /preview — the JSON-LD a schema would produce, tokens resolved for the post. */
    public function preview(WP_REST_Request $request)
    {
        $type = $this->type($request->get_param('type'));
        if (is_wp_error($type)) {
            return $type;
        }
        $postId = (int) $request->get_param('post_id');
        $post = $postId ? get_post($postId) : null;

        $schema = (new Schema($type->getName()))->setData((array) ($request->get_param('data') ?? []));
        return new WP_REST_Response($this->output->preview($schema, $post instanceof \WP_Post ? $post : null));
    }

    /**
     * Validate (partially: required fields may still be empty while editing),
     * sanitize, and write the schema into its document.
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

        $schemas = $this->load($postId)['schemas'];
        if ($replaceId === null) {
            $schemas[] = $saved;
        } else {
            foreach ($schemas as $i => $existing) {
                if (($existing['id'] ?? '') === $replaceId) {
                    $schemas[$i] = $saved;
                }
            }
        }

        if (!$this->write($postId, $schemas)) {
            return $this->saveFailed();
        }
        do_action(Hooks::SCHEMA_SAVED, $saved, $postId);
        return $saved;
    }

    private function status(array $schema): array
    {
        return $this->registry->validator()->validate(Schema::fromArray($schema));
    }

    private function load(int $postId): array
    {
        return $postId === 0 ? $this->global->get() : $this->store->get($postId);
    }

    private function write(int $postId, array $schemas): bool
    {
        return $postId === 0 ? $this->global->save($schemas) : $this->store->save($postId, $schemas);
    }

    /**
     * Post ID for the request, or 0 for the site-wide scope (routes without {post_id}).
     *
     * @return int|WP_Error
     */
    private function postId(WP_REST_Request $request)
    {
        if (!isset($request->get_url_params()['post_id'])) {
            return 0;
        }
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
        foreach ($this->load($postId)['schemas'] as $schema) {
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

    private function saveFailed(): WP_Error
    {
        return new WP_Error('unlimited_schema_save_failed', __('Could not save schema data.', 'unlimited-schema'), ['status' => 500]);
    }
}
