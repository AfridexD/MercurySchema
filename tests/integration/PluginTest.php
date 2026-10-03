<?php

use MercurySchema\Helpers\PostMetaStore;
use MercurySchema\Loader;

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
        $this->assertTrue(defined('MERCURY_SCHEMA_VERSION'));
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

    /**
     * Regression: a password-protected post must not leak its excerpt or
     * content through JSON-LD to visitors who haven't entered the password.
     */
    public function testPasswordProtectedPostDoesNotLeakContent(): void
    {
        wp_update_post(['ID' => $this->postId, 'post_password' => 'secret', 'post_excerpt' => 'SECRET excerpt', 'post_content' => 'SECRET body']);
        $this->setSchemas([$this->article(['data' => ['headline' => '{{post_title}}', 'author' => 'Ada', 'description' => '{{post_excerpt}}']])]);
        (new \MercurySchema\Helpers\GlobalStore())->save([
            ['id' => 'site-page', 'type' => 'WebPage', 'enabled' => true, 'conditions' => [], 'data' => ['name' => '{{post_title}}', 'description' => '{{post_content}}']],
        ]);

        $locked = wp_json_encode(Loader::getInstance()->output()->buildJsonLd($this->postId));
        $this->assertStringNotContainsString('SECRET', $locked);
        $this->assertStringContainsString('WebPage', $locked, 'site-wide schemas still print, with public fields only');
        $this->assertStringNotContainsString('"Article"', $locked, "the post's own schemas are held back");

        add_filter('post_password_required', '__return_false'); // Visitor has entered the password.
        $unlocked = wp_json_encode(Loader::getInstance()->output()->buildJsonLd($this->postId));
        remove_filter('post_password_required', '__return_false');
        $this->assertStringContainsString('SECRET excerpt', $unlocked);
        $this->assertStringContainsString('"Article"', $unlocked);

        (new \MercurySchema\Helpers\GlobalStore())->save([]);
    }

    public function testFiltersCanVetoAndModify(): void
    {
        $this->setSchemas([$this->article()]);

        $modify = static fn($ld) => $ld + ['inLanguage' => 'en'];
        add_filter('mercury_schema_json_ld_output', $modify);
        $docs = Loader::getInstance()->output()->buildJsonLd($this->postId);
        remove_filter('mercury_schema_json_ld_output', $modify);
        $this->assertSame('en', $docs[0]['inLanguage']);

        add_filter('mercury_schema_should_render', '__return_false');
        $this->assertSame([], Loader::getInstance()->output()->buildJsonLd($this->postId));
        remove_filter('mercury_schema_should_render', '__return_false');
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
