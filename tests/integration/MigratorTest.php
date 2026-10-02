<?php

use MercurySchema\Admin\Settings;
use MercurySchema\Helpers\GlobalStore;
use MercurySchema\Helpers\Migrator;
use MercurySchema\Helpers\PostMetaStore;

class MigratorTest extends WP_UnitTestCase
{
    public function testMovesLegacyOptionsAndMeta(): void
    {
        delete_option(Settings::OPTION);
        delete_option(GlobalStore::OPTION);
        add_option('unlimited_schema_settings', ['post_types' => ['post'], 'output_location' => 'footer', 'debug' => false]);
        add_option('unlimited_schema_global', ['version' => '1.0', 'schemas' => [['id' => 'org', 'type' => 'Organization', 'enabled' => true, 'data' => ['name' => 'Acme'], 'conditions' => []]]]);
        add_option('unlimited_schema_definitions', ['types' => []]);

        $a = self::factory()->post->create();
        $b = self::factory()->post->create();
        $json = wp_json_encode(['version' => '1.0', 'schemas' => [['id' => 'p', 'type' => 'Person', 'enabled' => true, 'data' => ['name' => 'Jo "Q" \\ x'], 'conditions' => []]]]);
        add_post_meta($a, '_unlimited_schema_data', wp_slash($json));
        add_post_meta($b, '_unlimited_schema_data', wp_slash($json));
        // $b already has new-format data: it must be kept, not overwritten.
        add_post_meta($b, PostMetaStore::META_KEY, wp_slash(wp_json_encode(['version' => '1.0', 'schemas' => []])));

        $report = Migrator::run();

        $this->assertSame(2, $report['options']);
        $this->assertSame(1, $report['meta_rows']);
        $this->assertSame('footer', Settings::get('output_location'));
        $this->assertSame('Acme', (new GlobalStore())->get()['schemas'][0]['data']['name']);
        $this->assertFalse(get_option('unlimited_schema_settings'));
        $this->assertFalse(get_option('unlimited_schema_global'));
        $this->assertFalse(get_option('unlimited_schema_definitions'));
        $this->assertSame('Jo "Q" \\ x', (new PostMetaStore())->get($a)['schemas'][0]['data']['name']);
        $this->assertSame([], (new PostMetaStore())->get($b)['schemas']);

        $again = Migrator::run();
        $this->assertSame(['options' => 0, 'meta_rows' => 0, 'deactivated_legacy' => false], $again, 'safe to run twice');
    }
}
