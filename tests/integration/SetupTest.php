<?php

use MercurySchema\Helpers\GlobalStore;
use MercurySchema\Helpers\PostMetaStore;
use MercurySchema\Helpers\SetupState;
use MercurySchema\Loader;

class SetupTest extends WP_UnitTestCase
{
    private const NS = '/mercury-schema/v1';

    public function set_up(): void
    {
        parent::set_up();
        global $wp_rest_server;
        $wp_rest_server = null;
        foreach ([SetupState::COMPLETE, SetupState::PRESET, SetupState::ENABLED_TYPES, SetupState::COMPLETED_AT, GlobalStore::OPTION] as $option) {
            delete_option($option);
        }
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
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

    private function output()
    {
        return Loader::getInstance()->output();
    }

    public function testStatusListsAllTypesEnabledBeforeSetup(): void
    {
        $data = $this->request('GET', '/setup')->get_data();
        $this->assertFalse($data['completed']);
        $this->assertNull($data['enabled_types']);
        $this->assertCount(22, $data['types']);
        $this->assertTrue(array_reduce($data['types'], fn($all, $t) => $all && $t['enabled'], true));
        $this->assertSame(SetupState::PRESET_TYPES['basic'], $data['presets']['basic']);
    }

    public function testBasicPresetCompletesWithFoundationSchemas(): void
    {
        $res = $this->request('POST', '/setup', ['preset' => 'basic', 'complete' => true])->get_data();

        $this->assertTrue($res['completed']);
        $this->assertSame('basic', $res['preset']);
        $this->assertSame(SetupState::PRESET_TYPES['basic'], $res['enabled_types']);
        $this->assertSame(['Organization', 'WebSite', 'WebPage', 'BreadcrumbList', 'Article'], $res['site_wide_added']);
        $this->assertNotEmpty(get_option(SetupState::COMPLETED_AT));

        // Finishing again must not duplicate the site-wide schemas.
        $again = $this->request('POST', '/setup', ['complete' => true])->get_data();
        $this->assertSame([], $again['site_wide_added']);
        $this->assertCount(5, (new GlobalStore())->get()['schemas']);
    }

    public function testFoundationSchemasRenderOnTheSite(): void
    {
        $this->request('POST', '/setup', ['preset' => 'basic', 'complete' => true]);
        $cat = self::factory()->category->create(['name' => 'Guides']);
        $post = self::factory()->post->create(['post_title' => 'Hello', 'post_category' => [$cat], 'post_author' => self::factory()->user->create(['display_name' => 'Ada'])]);

        $types = array_column($this->output()->buildJsonLd($post), '@type');
        $this->assertSame(['WebPage', 'BreadcrumbList', 'Article'], $types);

        $crumbs = $this->output()->buildJsonLd($post)[1]['itemListElement'];
        $this->assertSame(['Home', 'Guides', 'Hello'], array_column($crumbs, 'name'));
        $this->assertSame([1, 2, 3], array_column($crumbs, 'position'));

        $this->go_to(home_url('/'));
        $home = get_echo([$this->output(), 'render']);
        $this->assertStringContainsString('"@type":"Organization"', $home);
        $this->assertStringContainsString('"@type":"WebSite"', $home);
        $this->assertStringNotContainsString('BreadcrumbList', $home, 'no single-item trail on the front page');

        $this->go_to(get_category_link($cat));
        $archive = get_echo([$this->output(), 'render']);
        $this->assertStringContainsString('"name":"Guides"', $archive);
    }

    public function testDisabledTypesStopPrintingButKeepData(): void
    {
        $post = self::factory()->post->create(['post_title' => 'Hi']);
        (new PostMetaStore())->save($post, [['id' => 'p', 'type' => 'Person', 'enabled' => true, 'data' => ['name' => 'Jo'], 'conditions' => []]]);
        $this->assertCount(1, $this->output()->buildJsonLd($post));

        $this->request('POST', '/setup', ['enabled_types' => ['Article']]);
        $this->assertSame([], $this->output()->buildJsonLd($post));
        $this->assertCount(1, (new PostMetaStore())->get($post)['schemas'], 'data kept');

        $types = $this->request('GET', '/schema-types')->get_data();
        $this->assertFalse($types['Person']['enabled']);
        $this->assertTrue($types['Article']['enabled']);

        $preview = $this->request('POST', '/preview', ['type' => 'Person', 'post_id' => $post, 'data' => ['name' => 'Jo']])->get_data();
        $this->assertFalse($preview['valid']);
    }

    public function testTypesUpdatedActionFires(): void
    {
        $seen = null;
        add_action('mercury_schema_types_updated', $cb = function ($types) use (&$seen) { $seen = $types; });
        $this->request('POST', '/setup', ['enabled_types' => ['Article', 'FAQPage']]);
        remove_action('mercury_schema_types_updated', $cb);
        $this->assertSame(['Article', 'FAQPage'], $seen);
    }

    public function testRejectsUnknownTypesAndPresets(): void
    {
        $this->assertSame(400, $this->request('POST', '/setup', ['enabled_types' => ['Article', 'Spaceship']])->get_status());
        $this->assertSame(400, $this->request('POST', '/setup', ['preset' => 'mega'])->get_status());
        $this->assertNull(SetupState::enabledTypes());
    }

    public function testResetKeepsTypesAndSchemas(): void
    {
        $this->request('POST', '/setup', ['preset' => 'basic', 'complete' => true]);
        $res = $this->request('POST', '/setup/reset')->get_data();
        $this->assertFalse($res['completed']);
        $this->assertSame(SetupState::PRESET_TYPES['basic'], $res['enabled_types']);
        $this->assertCount(5, (new GlobalStore())->get()['schemas']);
    }

    public function testFreshActivationQueuesWizard(): void
    {
        delete_transient(SetupState::REDIRECT);
        Loader::activate();
        $this->assertSame(1, (int) get_transient(SetupState::REDIRECT));
        $this->assertFalse(SetupState::isComplete());
    }

    public function testActivationAfterMigrationSkipsWizard(): void
    {
        delete_transient(SetupState::REDIRECT);
        add_option('unlimited_schema_settings', ['post_types' => ['post'], 'output_location' => 'head', 'debug' => false]);
        Loader::activate();
        $this->assertTrue(SetupState::isComplete());
        $this->assertFalse(get_transient(SetupState::REDIRECT));
        $this->assertNull(SetupState::enabledTypes(), 'every type stays on for upgraded sites');
    }

    public function testEditorsCannotUseSetup(): void
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));
        $this->assertSame(403, $this->request('GET', '/setup')->get_status());
        $this->assertSame(403, $this->request('POST', '/setup', ['preset' => 'basic'])->get_status());
        $this->assertSame(403, $this->request('POST', '/setup/reset')->get_status());
    }

    public function testNavigationFromClassicMenu(): void
    {
        $menu = wp_create_nav_menu('Main');
        wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'About', 'menu-item-url' => 'https://example.org/about', 'menu-item-status' => 'publish']);
        wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'Blog &amp; News', 'menu-item-url' => 'https://example.org/blog', 'menu-item-status' => 'publish']);

        $post = self::factory()->post->create();
        (new PostMetaStore())->save($post, [['id' => 'n', 'type' => 'SiteNavigationElement', 'enabled' => true, 'data' => ['menuLocation' => 'primary'], 'conditions' => []]]);
        $doc = $this->output()->buildJsonLd($post)[0];

        $this->assertSame('ItemList', $doc['@type']);
        $this->assertSame(['About', 'Blog & News'], array_column($doc['itemListElement'], 'name'));
        $this->assertSame('SiteNavigationElement', $doc['itemListElement'][0]['@type']);
        $this->assertArrayNotHasKey('menuLocation', $doc);
    }

    public function testHierarchicalPageBreadcrumbs(): void
    {
        $parent = self::factory()->post->create(['post_type' => 'page', 'post_title' => 'Services']);
        $child = self::factory()->post->create(['post_type' => 'page', 'post_title' => 'Plumbing', 'post_parent' => $parent]);
        (new PostMetaStore())->save($child, [['id' => 'b', 'type' => 'BreadcrumbList', 'enabled' => true, 'data' => ['homeLabel' => 'Start'], 'conditions' => []]]);

        $crumbs = $this->output()->buildJsonLd($child)[0]['itemListElement'];
        $this->assertSame(['Start', 'Services', 'Plumbing'], array_column($crumbs, 'name'));
        $this->assertSame(get_permalink($parent), $crumbs[1]['item']);
    }
}
