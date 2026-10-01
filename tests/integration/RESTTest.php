<?php

use UnlimitedSchema\Helpers\PostMetaStore;

class RESTTest extends WP_UnitTestCase
{
    private const NS = '/unlimited-schema/v1';

    private int $postId;
    private int $adminId;

    public function set_up(): void
    {
        parent::set_up();
        global $wp_rest_server;
        $wp_rest_server = null;

        $this->adminId = self::factory()->user->create(['role' => 'administrator']);
        $this->postId = self::factory()->post->create(['post_title' => 'Test Post']);
        wp_set_current_user($this->adminId);
    }

    private function request(string $method, string $route, ?array $body = null): WP_REST_Response
    {
        $request = new WP_REST_Request($method, self::NS . $route);
        if ($body !== null) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body(wp_json_encode($body));
        }
        return rest_do_request($request);
    }

    private function create(array $body): array
    {
        $response = $this->request('POST', '/schemas/' . $this->postId, $body);
        $this->assertSame(201, $response->get_status(), wp_json_encode($response->get_data()));
        return $response->get_data()['schema'];
    }

    public function testListTypes(): void
    {
        $response = $this->request('GET', '/schemas');
        $this->assertSame(200, $response->get_status());
        $this->assertContains('Article', array_column($response->get_data()['types'], 'type'));

        $types = $this->request('GET', '/schema-types')->get_data();
        $this->assertArrayHasKey('LocalBusiness', $types);
        $this->assertArrayHasKey('fields', $types['LocalBusiness']);
    }

    public function testCreateSchemaViaREST(): void
    {
        $schema = $this->create(['type' => 'Article', 'data' => ['headline' => 'Test']]);
        $this->assertSame('Article', $schema['type']);
        $this->assertSame('Test', $schema['data']['headline']);
        $this->assertTrue($schema['enabled']);
    }

    public function testCreateWithoutDataAppliesDefaults(): void
    {
        $schema = $this->create(['type' => 'Article']);
        $this->assertSame('{{post_title}}', $schema['data']['headline']);
    }

    public function testGetSchemasViaREST(): void
    {
        update_post_meta($this->postId, PostMetaStore::META_KEY, wp_slash(wp_json_encode([
            'version' => '1.0',
            'schemas' => [['id' => 'test-1', 'type' => 'Article', 'enabled' => true, 'data' => [], 'conditions' => []]],
        ])));

        $response = $this->request('GET', '/schemas/' . $this->postId);
        $this->assertSame(200, $response->get_status());
        $this->assertCount(1, $response->get_data()['schemas']);
    }

    public function testUpdateAndDelete(): void
    {
        $schema = $this->create(['type' => 'Person', 'data' => ['name' => 'Jo']]);
        $route = '/schemas/' . $this->postId . '/' . $schema['id'];

        $updated = $this->request('PUT', $route, ['enabled' => false, 'data' => ['name' => 'Joanna']]);
        $this->assertSame(200, $updated->get_status());
        $this->assertFalse($updated->get_data()['schema']['enabled']);
        $this->assertSame('Joanna', $updated->get_data()['schema']['data']['name']);

        $deleted = $this->request('DELETE', $route);
        $this->assertSame(200, $deleted->get_status());
        $this->assertTrue($deleted->get_data()['success']);
        $this->assertSame('', get_post_meta($this->postId, PostMetaStore::META_KEY, true));

        $this->assertSame(404, $this->request('DELETE', $route)->get_status());
    }

    public function testQuotesAndBackslashesSurviveStorage(): void
    {
        $name = 'The "Best" C:\\Path \\u00e9 café';
        $schema = $this->create(['type' => 'Person', 'data' => ['name' => $name]]);
        $stored = $this->request('GET', '/schemas/' . $this->postId)->get_data()['schemas'][0];
        $this->assertSame($schema['data']['name'], $stored['data']['name']);
        $this->assertStringContainsString('"Best"', $stored['data']['name']);
    }

    public function testRejectsInvalidData(): void
    {
        $response = $this->request('POST', '/schemas/' . $this->postId, ['type' => 'Person', 'data' => ['url' => 'nope']]);
        $this->assertSame(400, $response->get_status());
        $this->assertSame('url', $response->get_data()['data']['errors'][0]['field']);
    }

    public function testUnknownTypeAndMissingPost(): void
    {
        $this->assertSame(400, $this->request('POST', '/schemas/' . $this->postId, ['type' => 'Spaceship'])->get_status());
        $this->assertSame(404, $this->request('GET', '/schemas/999999')->get_status());
    }

    public function testValidateEndpoint(): void
    {
        $response = $this->request('POST', '/validate', ['type' => 'Article', 'data' => ['headline' => 'Hi']]);
        $this->assertSame(200, $response->get_status());
        $this->assertFalse($response->get_data()['valid']);
        $this->assertSame('author', $response->get_data()['errors'][0]['field']);
    }

    public function testRequiresManageOptions(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->assertSame(403, $this->request('GET', '/schemas/' . $this->postId)->get_status());

        wp_set_current_user(0);
        $this->assertSame(401, $this->request('GET', '/schemas')->get_status());
    }
}
