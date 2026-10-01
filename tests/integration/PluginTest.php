<?php

use UnlimitedSchema\Helpers\PostMetaStore;
use UnlimitedSchema\Loader;

class PluginTest extends WP_UnitTestCase
{
    private int $postId;

    public function set_up(): void
    {
        parent::set_up();
        $this->postId = self::factory()->post->create([
            'post_title'   => 'Hello </script> World',
            'post_excerpt' => 'A short summary.',
            'post_author'  => self::factory()->user->create(['role' => 'author', 'display_name' => 'Ada Lovelace']),
        ]);
    }

    private function setSchemas(array $schemas): void
    {
        (new PostMetaStore())->save($this->postId, $schemas);
    }

    private function article(array $overrides = []): array
    {
        return array_merge([
            'id'         => 'article-1',
            'type'       => 'Article',
            'enabled'    => true,
            'data'       => ['headline' => '{{post_title}}', 'author' => '{{author_name}}', 'description' => '{{post_excerpt}}'],
            'conditions' => [],
        ], $overrides);
    }

    public function testPluginLoaded(): void
    {
        $this->assertTrue(defined('UNLIMITED_SCHEMA_VERSION'));
        $this->assertInstanceOf(Loader::class, Loader::getInstance());
    }

    public function testRendersResolvedTokens(): void
    {
        $this->setSchemas([$this->article()]);
        $docs = Loader::getInstance()->output()->buildJsonLd($this->postId);

        $this->assertCount(1, $docs);
        $this->assertSame('Article', $docs[0]['@type']);
        $this->assertSame('Hello World', preg_replace('/\s+/', ' ', $docs[0]['headline']));
        $this->assertSame(['@type' => 'Person', 'name' => 'Ada Lovelace'], $docs[0]['author']);
        $this->assertSame('A short summary.', $docs[0]['description']);
    }

    public function testMarkupCannotBreakOutOfScriptTag(): void
    {
        $this->setSchemas([$this->article(['data' => ['headline' => 'x</script><b>', 'author' => 'Ada']])]);
        $html = Loader::getInstance()->output()->getMarkup($this->postId);

        $this->assertStringStartsWith('<script type="application/ld+json">', $html);
        $this->assertSame(1, substr_count($html, '</script>'));
        $this->assertStringContainsString('</script>', $html);
    }

    public function testSkipsDisabledInvalidAndNonMatching(): void
    {
        $this->setSchemas([
            $this->article(['id' => 'off', 'enabled' => false]),
            $this->article(['id' => 'invalid', 'data' => ['headline' => 'No author']]),
            $this->article(['id' => 'pages-only', 'conditions' => ['post_types' => ['page']]]),
            $this->article(['id' => 'unknown-type', 'type' => 'Spaceship']),
        ]);
        $this->assertSame([], Loader::getInstance()->output()->buildJsonLd($this->postId));
    }

    public function testCategoryAndAuthorRoleConditions(): void
    {
        $news = self::factory()->category->create(['slug' => 'news']);
        wp_set_post_categories($this->postId, [$news]);

        $this->setSchemas([
            $this->article(['id' => 'news-slug', 'conditions' => ['categories' => ['news']]]),
            $this->article(['id' => 'news-id', 'conditions' => ['categories' => [(string) $news]]]),
            $this->article(['id' => 'sport', 'conditions' => ['categories' => ['sport']]]),
            $this->article(['id' => 'author-role', 'conditions' => ['user_roles' => ['author']]]),
            $this->article(['id' => 'admin-role', 'conditions' => ['user_roles' => ['administrator']]]),
        ]);

        $this->assertCount(3, Loader::getInstance()->output()->buildJsonLd($this->postId));
    }

    public function testFiltersCanVetoAndModify(): void
    {
        $this->setSchemas([$this->article()]);

        $modify = static fn($ld) => $ld + ['inLanguage' => 'en'];
        add_filter('unlimited_schema_json_ld_output', $modify);
        $docs = Loader::getInstance()->output()->buildJsonLd($this->postId);
        remove_filter('unlimited_schema_json_ld_output', $modify);
        $this->assertSame('en', $docs[0]['inLanguage']);

        add_filter('unlimited_schema_should_render', '__return_false');
        $this->assertSame([], Loader::getInstance()->output()->buildJsonLd($this->postId));
        remove_filter('unlimited_schema_should_render', '__return_false');
    }

    public function testWpHeadOutputOnSingularOnly(): void
    {
        $this->setSchemas([$this->article()]);

        $this->go_to(get_permalink($this->postId));
        $this->assertStringContainsString('application/ld+json', get_echo([Loader::getInstance(), 'outputSchemaMarkup']));

        $this->go_to(home_url('/'));
        $this->assertSame('', get_echo([Loader::getInstance(), 'outputSchemaMarkup']));
    }
}
