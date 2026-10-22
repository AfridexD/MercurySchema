<?php

use MercurySchema\Admin\OtherSchemaSources;
use MercurySchema\API\Hooks;

class OtherSourcesTest extends WP_UnitTestCase
{
    public function set_up(): void
    {
        parent::set_up();
        update_option('active_plugins', ['mercury-schema/mercury-schema.php']);
    }

    public function testNoWarningWhenNoOtherSchemaPluginIsActive(): void
    {
        update_option('active_plugins', ['mercury-schema/mercury-schema.php', 'akismet/akismet.php', 'elementor/elementor.php']);
        $this->assertSame([], OtherSchemaSources::active());
        $this->assertNull(OtherSchemaSources::warning());
    }

    public function testDetectsSeoPluginsByFolderAndMergesProEditions(): void
    {
        update_option('active_plugins', [
            'wordpress-seo/wp-seo.php',
            'wordpress-seo-premium/wp-seo-premium.php',
            'seo-by-rank-math/rank-math.php',
            'seo-by-rank-math-pro/renamed-main-file.php',
        ]);
        $this->assertSame(['Yoast SEO', 'Rank Math SEO'], OtherSchemaSources::active());
    }

    public function testWarningNamesEveryPluginInOneSentence(): void
    {
        update_option('active_plugins', ['wordpress-seo/wp-seo.php']);
        $this->assertSame('Yoast SEO also adds schema to your pages.', OtherSchemaSources::warning()['title']);

        update_option('active_plugins', ['wordpress-seo/wp-seo.php', 'all-in-one-seo-pack/all_in_one_seo_pack.php', 'slim-seo/slim-seo.php']);
        $warning = OtherSchemaSources::warning();
        $this->assertSame('Yoast SEO, All in One SEO and Slim SEO also add schema to your pages.', $warning['title']);
        $this->assertStringContainsString('#other-schema', $warning['url']);
    }

    public function testFilterCanAddOrRemoveSources(): void
    {
        update_option('active_plugins', ['wordpress-seo/wp-seo.php']);
        add_filter(Hooks::OTHER_SOURCES, static function (array $names): array {
            return array_merge(array_diff($names, ['Yoast SEO']), ['My Theme']);
        });
        $this->assertSame(['My Theme'], OtherSchemaSources::active());
    }

    public function testNoticeIsEscaped(): void
    {
        add_filter(Hooks::OTHER_SOURCES, static function (): array {
            return ['<script>alert(1)</script>'];
        });
        ob_start();
        OtherSchemaSources::renderNotice();
        $html = ob_get_clean();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
