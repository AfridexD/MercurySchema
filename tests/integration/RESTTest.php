<?php

use MercurySchema\Helpers\PostMetaStore;

class RESTTest extends WP_UnitTestCase
{
    private const NS = '/mercury-schema/v1';

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

    public function testEditorsCanEditPostSchemas(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->assertSame(200, $this->request('GET', '/schemas/' . $this->postId)->get_status());
        $this->assertSame(200, $this->request('GET', '/schema-types')->get_status());
        $created = $this->request('POST', '/schemas/' . $this->postId, ['type' => 'Person', 'data' => ['name' => 'Jo']]);
        $this->assertSame(201, $created->get_status());
        $route = '/schemas/' . $this->postId . '/' . $created->get_data()['schema']['id'];
        $this->assertSame(200, $this->request('PUT', $route, ['enabled' => false])->get_status());
        $this->assertSame(200, $this->request('POST', '/preview', ['type' => 'Person', 'post_id' => $this->postId, 'data' => []])->get_status());
        $this->assertSame(200, $this->request('DELETE', $route)->get_status());
    }

    public function testEditorsCannotEditSiteWideSchemas(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->assertSame(403, $this->request('GET', '/global')->get_status());
        $this->assertSame(403, $this->request('POST', '/global', ['type' => 'Organization'])->get_status());
    }

    /**
     * Regression: WordPress matches routes case-insensitively, so scope must
     * never be inferred from the URL text.
     */
    public function testEditorsCannotReachSiteWideRoutesViaCaseVariants(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        foreach (['/mercury-schema/v1/GLOBAL', '/mercury-schema/v1/Global', '/MERCURY-SCHEMA/V1/global'] as $route) {
            $this->assertSame(403, rest_do_request(new WP_REST_Request('GET', $route))->get_status(), "GET $route");
            $post = new WP_REST_Request('POST', $route);
            $post->set_param('type', 'Organization');
            $this->assertSame(403, rest_do_request($post)->get_status(), "POST $route");
            $this->assertSame(403, rest_do_request(new WP_REST_Request('DELETE', $route . '/organization-1'))->get_status(), "DELETE $route");
        }
        $this->assertSame([], (new \MercurySchema\Helpers\GlobalStore())->get()['schemas']);
    }

    public function testSchemaCountAndSizeLimits(): void
    {
        $tooBig = $this->request('POST', '/schemas/' . $this->postId, ['type' => 'Person', 'data' => ['name' => str_repeat('A', 2 * 1024 * 1024)]]);
        $this->assertSame(400, $tooBig->get_status());

        $schemas = array_map(static fn($i) => ['id' => "p-$i", 'type' => 'Person', 'enabled' => true, 'data' => [], 'conditions' => []], range(1, 50));
        (new PostMetaStore())->save($this->postId, $schemas);
        $res = $this->request('POST', '/schemas/' . $this->postId, ['type' => 'Person']);
        $this->assertSame(400, $res->get_status());
        $this->assertSame('mercury_schema_limit', $res->get_data()['code']);
    }

    public function testAuthorsAndContributorsCannotEditSchemas(): void
    {
        $author = self::factory()->user->create(['role' => 'author']);
        $own = self::factory()->post->create(['post_author' => $author]);
        foreach (['author' => $author, 'contributor' => self::factory()->user->create(['role' => 'contributor'])] as $role => $user) {
            wp_set_current_user($user);
            $this->assertSame(403, $this->request('GET', '/schemas/' . $own)->get_status(), "$role GET own post");
            $this->assertSame(403, $this->request('POST', '/schemas/' . $own, ['type' => 'Person'])->get_status(), "$role POST");
            $this->assertSame(403, $this->request('GET', '/schema-types')->get_status(), "$role types");
        }

        wp_set_current_user(0);
        $this->assertSame(401, $this->request('GET', '/schemas')->get_status());
    }

    public function testCapabilityFiltersStillWork(): void
    {
        $author = self::factory()->user->create(['role' => 'author']);
        wp_set_current_user($author);
        $own = self::factory()->post->create(['post_author' => $author]);
        $other = self::factory()->post->create(['post_author' => $this->adminId]);

        add_filter('mercury_schema_rest_capability', $cap = static fn() => 'edit_posts');
        $this->assertSame(200, $this->request('GET', '/schemas/' . $own)->get_status());
        $this->assertSame(403, $this->request('GET', '/schemas/' . $other)->get_status(), 'still needs edit_post on that post');
        remove_filter('mercury_schema_rest_capability', $cap);
    }
}
