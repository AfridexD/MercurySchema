<?php

use UnlimitedSchema\Helpers\GlobalStore;
use UnlimitedSchema\Helpers\PostMetaStore;
use UnlimitedSchema\Loader;

class SiteWideTest extends WP_UnitTestCase
{
    private const NS = '/unlimited-schema/v1';

    private int $postId;

    public function set_up(): void
    {
        parent::set_up();
        global $wp_rest_server;
        $wp_rest_server = null;
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        $this->postId = self::factory()->post->create([
            'post_title'  => 'Hello',
            'post_author' => self::factory()->user->create(['role' => 'author', 'display_name' => 'Ada']),
        ]);
        update_option('blogname', 'Acme Inc');
        delete_option(GlobalStore::OPTION);
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

    private function types(array $docs): array
    {
        return array_column($docs, '@type');
    }

    private function output()
    {
        return Loader::getInstance()->output();
    }

    public function testGlobalCrud(): void
    {
        $created = $this->request('POST', '/global', ['type' => 'Organization']);
        $this->assertSame(201, $created->get_status());
        $schema = $created->get_data()['schema'];
        $this->assertSame('{{site_name}}', $schema['data']['name']);
        $this->assertTrue($created->get_data()['status']['valid']);

        $list = $this->request('GET', '/global')->get_data();
        $this->assertSame(0, $list['post_id']);
        $this->assertCount(1, $list['schemas']);
        $this->assertArrayNotHasKey('site_wide', $list);

        $updated = $this->request('PUT', '/global/' . $schema['id'], ['data' => ['name' => 'Acme Ltd']]);
        $this->assertSame('Acme Ltd', $updated->get_data()['schema']['data']['name']);

        $this->assertSame(200, $this->request('DELETE', '/global/' . $schema['id'])->get_status());
        $this->assertSame([], (new GlobalStore())->get()['schemas']);
    }

    public function testGlobalStoreIsAutoloaded(): void
    {
        (new GlobalStore())->save([['id' => 'org-1', 'type' => 'Organization', 'enabled' => true, 'data' => [], 'conditions' => []]]);
        $this->assertArrayHasKey(GlobalStore::OPTION, wp_load_alloptions());
    }

    public function testSiteWideRendersOnPostsAndPostOverridesSameType(): void
    {
        (new GlobalStore())->save([
            ['id' => 'org', 'type' => 'Organization', 'enabled' => true, 'data' => ['name' => '{{site_name}}'], 'conditions' => []],
            ['id' => 'art', 'type' => 'Article', 'enabled' => true, 'data' => ['headline' => 'Global {{post_title}}', 'author' => '{{author_name}}'], 'conditions' => []],
        ]);

        $docs = $this->output()->buildJsonLd($this->postId);
        $this->assertSame(['Organization', 'Article'], $this->types($docs));
        $this->assertSame('Acme Inc', $docs[0]['name']);
        $this->assertSame('Global Hello', $docs[1]['headline']);

        (new PostMetaStore())->save($this->postId, [
            ['id' => 'own', 'type' => 'Article', 'enabled' => true, 'data' => ['headline' => 'Own', 'author' => 'Ada'], 'conditions' => []],
        ]);
        $docs = $this->output()->buildJsonLd($this->postId);
        $this->assertSame(['Organization', 'Article'], $this->types($docs));
        $this->assertSame('Own', $docs[1]['headline'], 'post schema replaces the site-wide one');

        // A disabled post schema does not replace the site-wide one.
        (new PostMetaStore())->save($this->postId, [
            ['id' => 'own', 'type' => 'Article', 'enabled' => false, 'data' => ['headline' => 'Own', 'author' => 'Ada'], 'conditions' => []],
        ]);
        $this->assertSame('Global Hello', $this->output()->buildJsonLd($this->postId)[1]['headline']);
    }

    public function testLocationsOnFrontPageAndArchives(): void
    {
        (new GlobalStore())->save([
            ['id' => 'org', 'type' => 'Organization', 'enabled' => true, 'data' => ['name' => '{{site_name}}'], 'conditions' => ['locations' => ['front_page']]],
            ['id' => 'art', 'type' => 'Article', 'enabled' => true, 'data' => ['headline' => '{{post_title}}', 'author' => '{{author_name}}'], 'conditions' => ['locations' => ['singular']]],
        ]);

        $this->go_to(home_url('/'));
        $home = get_echo([$this->output(), 'render']);
        $this->assertStringContainsString('"@type":"Organization"', $home);
        $this->assertStringNotContainsString('"Article"', $home);

        $this->go_to(get_permalink($this->postId));
        $single = get_echo([$this->output(), 'render']);
        $this->assertStringContainsString('"@type":"Article"', $single);
        $this->assertStringNotContainsString('Organization', $single);

        $this->go_to(get_author_posts_url((int) get_post($this->postId)->post_author));
        $this->assertSame('', get_echo([$this->output(), 'render']));
    }

    public function testStaticFrontPageCountsAsFrontPageAndSingular(): void
    {
        $page = self::factory()->post->create(['post_type' => 'page', 'post_title' => 'Home']);
        update_option('show_on_front', 'page');
        update_option('page_on_front', $page);
        (new GlobalStore())->save([
            ['id' => 'org', 'type' => 'Organization', 'enabled' => true, 'data' => ['name' => 'Acme'], 'conditions' => ['locations' => ['front_page']]],
        ]);

        $this->go_to(home_url('/'));
        $this->assertStringContainsString('Organization', get_echo([$this->output(), 'render']));
        $this->assertCount(1, $this->output()->buildJsonLd($page));
    }

    public function testPostListingReportsSiteWideAndStatus(): void
    {
        (new GlobalStore())->save([['id' => 'org', 'type' => 'Organization', 'enabled' => true, 'data' => [], 'conditions' => []]]);
        (new PostMetaStore())->save($this->postId, [
            ['id' => 'ok', 'type' => 'Person', 'enabled' => true, 'data' => ['name' => '{{author_name}}'], 'conditions' => []],
            ['id' => 'bad', 'type' => 'Person', 'enabled' => true, 'data' => [], 'conditions' => []],
        ]);

        $data = $this->request('GET', '/schemas/' . $this->postId)->get_data();
        $this->assertSame([['id' => 'org', 'type' => 'Organization', 'enabled' => true]], $data['site_wide']);
        $status = (array) $data['status'];
        $this->assertTrue($status['ok']['valid']);
        $this->assertFalse($status['bad']['valid']);
        $this->assertSame('name', $status['bad']['errors'][0]['field']);
    }

    public function testPreviewResolvesTokensForThePost(): void
    {
        $res = $this->request('POST', '/preview', [
            'type'    => 'Article',
            'post_id' => $this->postId,
            'data'    => ['headline' => '{{post_title}}', 'author' => '{{author_name}}', 'publisherName' => '{{site_name}}'],
        ])->get_data();

        $this->assertTrue($res['valid'], wp_json_encode($res['errors']));
        $this->assertSame('Hello', $res['json_ld']['headline']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Ada'], $res['json_ld']['author']);
        $this->assertSame('Acme Inc', $res['json_ld']['publisher']['name']);
    }

    public function testPreviewWithoutPostReportsMissingPostValues(): void
    {
        $res = $this->request('POST', '/preview', ['type' => 'Article', 'data' => ['headline' => '{{post_title}}', 'author' => 'Jo']])->get_data();
        $this->assertFalse($res['valid']);
        $this->assertSame('headline', $res['errors'][0]['field']);
    }

    public function testFaqSavedThroughRestIsSanitizedAndRendered(): void
    {
        $res = $this->request('POST', '/schemas/' . $this->postId, ['type' => 'FAQPage', 'data' => ['questions' => [
            ['question' => 'Is it <b>fast</b>?', 'answer' => "Very.\nReally <script>alert(1)</script>"],
            ['question' => '', 'answer' => ''],
        ]]]);
        $this->assertSame(201, $res->get_status(), wp_json_encode($res->get_data()));
        $saved = $res->get_data()['schema']['data']['questions'];
        $this->assertCount(1, $saved, 'blank rows are dropped');
        $this->assertSame('Is it fast?', $saved[0]['question']);
        $this->assertStringNotContainsString('<script>', $saved[0]['answer']);

        $docs = $this->output()->buildJsonLd($this->postId);
        $this->assertSame('FAQPage', $docs[0]['@type']);
        $this->assertSame('Question', $docs[0]['mainEntity'][0]['@type']);
    }

    public function testInvalidFaqItemIsRejectedWithItemPath(): void
    {
        $res = $this->request('POST', '/schemas/' . $this->postId, ['type' => 'FAQPage', 'data' => ['questions' => [['question' => 'Q', 'extra' => 'x']]]]);
        $this->assertSame(400, $res->get_status());
        $this->assertSame('questions.0.extra', $res->get_data()['data']['errors'][0]['field']);
    }

    public function testGlobalRoutesRequireManageOptions(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->assertSame(403, $this->request('GET', '/global')->get_status());
        $this->assertSame(403, $this->request('POST', '/global', ['type' => 'Organization'])->get_status());
    }
}
