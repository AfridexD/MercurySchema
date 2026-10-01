<?php
/**
 * Every public filter and action the plugin fires, in one place.
 *
 * @package UnlimitedSchema
 */

namespace UnlimitedSchema\API;

class Hooks
{
    /** filter( array $definitions ) — all type definitions, keyed by type name. */
    public const DEFINITIONS = 'unlimited_schema_definitions';

    /** filter( array $definition, string $type ) — one type definition. */
    public const TYPE_DEFINITION = 'unlimited_schema_type_definition';

    /** filter( bool $enabled ) — return false to stop all front-end output. */
    public const OUTPUT_ENABLED = 'unlimited_schema_output_enabled';

    /** filter( bool $should_render, Schema $schema, int $post_id ) */
    public const SHOULD_RENDER = 'unlimited_schema_should_render';

    /** filter( array $json_ld, int $post_id, Schema $schema ) — return [] to drop it. */
    public const JSON_LD_OUTPUT = 'unlimited_schema_json_ld_output';

    /** filter( array $values, WP_Post $post ) — token name => value for {{tokens}}. */
    public const TOKEN_VALUES = 'unlimited_schema_token_values';

    /** filter( array $context, WP_Post $post ) — data handed to ConditionEvaluator. */
    public const CONDITION_CONTEXT = 'unlimited_schema_condition_context';

    /** filter( string $capability ) — capability required for the REST API. */
    public const REST_CAPABILITY = 'unlimited_schema_rest_capability';

    /** filter( string[] $post_types ) — post types that get the metabox. */
    public const POST_TYPES = 'unlimited_schema_post_types';

    /** filter( bool $enabled ) — force debug logging on or off. */
    public const LOGGING_ENABLED = 'unlimited_schema_logging_enabled';

    /** action( array $schema, int $post_id ) — after create or update. */
    public const SCHEMA_SAVED = 'unlimited_schema_schema_saved';

    /** action( string $schema_id, int $post_id ) — after delete. */
    public const SCHEMA_DELETED = 'unlimited_schema_schema_deleted';
}
