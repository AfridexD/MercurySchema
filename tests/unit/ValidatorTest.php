<?php

use PHPUnit\Framework\TestCase;
use UnlimitedSchema\Core\Schema;
use UnlimitedSchema\Core\SchemaType;
use UnlimitedSchema\Core\Validator;

class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $json = json_decode(file_get_contents(dirname(__DIR__, 2) . '/assets/schema-definitions.json'), true);
        $types = [];
        foreach ($json['types'] as $name => $definition) {
            $types[$name] = new SchemaType($name, $definition);
        }
        $this->validator = new Validator($types);
    }

    private function check(string $type, array $data, bool $partial = false): array
    {
        return $this->validator->validate((new Schema($type))->setData($data), $partial);
    }

    private function errorFields(array $result): array
    {
        return array_column($result['errors'], 'field');
    }

    public function testValidArticle(): void
    {
        $result = $this->check('Article', [
            'headline'      => 'Hello',
            'author'        => 'Jane',
            'image'         => 'https://example.com/a.jpg',
            'datePublished' => '2026-10-02T09:30:00+00:00',
        ]);
        $this->assertTrue($result['valid'], json_encode($result['errors']));
    }

    public function testUnknownType(): void
    {
        $result = $this->check('Spaceship', []);
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Unknown schema type', $result['errors'][0]['message']);
    }

    public function testMissingRequiredFields(): void
    {
        $result = $this->check('Article', ['headline' => '']);
        $this->assertFalse($result['valid']);
        $this->assertEqualsCanonicalizing(['headline', 'author'], $this->errorFields($result));
    }

    public function testPartialSkipsRequired(): void
    {
        $this->assertTrue($this->check('Article', [], true)['valid']);
    }

    public function testPartialStillChecksTypes(): void
    {
        $result = $this->check('Article', ['image' => 'not a url'], true);
        $this->assertSame(['image'], $this->errorFields($result));
    }

    public function testUnknownField(): void
    {
        $result = $this->check('Person', ['name' => 'Jo', 'shoeSize' => '9']);
        $this->assertSame(['shoeSize'], $this->errorFields($result));
    }

    /**
     * @dataProvider invalidValues
     */
    public function testInvalidValues(string $type, string $field, $value): void
    {
        $result = $this->check($type, [$field => $value], true);
        $this->assertSame([$field], $this->errorFields($result));
    }

    public static function invalidValues(): array
    {
        return [
            'ftp url'       => ['Person', 'url', 'ftp://example.com'],
            'javascript url'=> ['Person', 'url', 'javascript:alert(1)'],
            'bad date'      => ['Event', 'startDate', 'next tuesday'],
            'bad number'    => ['Product', 'price', 'cheap'],
            'bad enum'      => ['Product', 'availability', 'InStock'],
            'nested array'  => ['Organization', 'sameAs', [['x']]],
            'array string'  => ['Person', 'name', ['a']],
        ];
    }

    /**
     * @dataProvider validValues
     */
    public function testValidValues(string $type, string $field, $value): void
    {
        $this->assertTrue($this->check($type, [$field => $value], true)['valid']);
    }

    public static function validValues(): array
    {
        return [
            'date only'     => ['Event', 'startDate', '2026-11-20'],
            'datetime Z'    => ['Event', 'startDate', '2026-11-20T19:00:00Z'],
            'numeric price' => ['Product', 'price', '19.99'],
            'int price'     => ['Product', 'price', 20],
            'enum'          => ['Product', 'availability', 'https://schema.org/InStock'],
            'list'          => ['Organization', 'sameAs', ['https://x.com/a']],
            'list string'   => ['Organization', 'sameAs', "https://x.com/a\nhttps://y.com/b"],
        ];
    }

    public function testDurations(): void
    {
        $this->assertTrue($this->check('Recipe', ['prepTime' => 'PT1H30M'], true)['valid']);
        $this->assertTrue($this->check('VideoObject', ['duration' => 'PT4M30.5S'], true)['valid']);
        foreach (['30 minutes', 'P', 'PT', '1H'] as $bad) {
            $this->assertSame(['prepTime'], $this->errorFields($this->check('Recipe', ['prepTime' => $bad], true)), $bad);
        }
    }

    public function testFaqRequiresQuestions(): void
    {
        $this->assertSame(['questions'], $this->errorFields($this->check('FAQPage', [])));
        $this->assertSame(['questions'], $this->errorFields($this->check('FAQPage', ['questions' => [['question' => '', 'answer' => '']]])));
    }

    public function testFaqItemErrorsUseDottedPaths(): void
    {
        $result = $this->check('FAQPage', ['questions' => [
            ['question' => 'Q1', 'answer' => 'A1'],
            ['question' => 'Q2'],
            ['question' => 'Q3', 'answer' => 'A3', 'bogus' => 'x'],
        ]]);
        $this->assertEqualsCanonicalizing(['questions.1.answer', 'questions.2.bogus'], $this->errorFields($result));
    }

    public function testFaqPartialAllowsIncompleteItems(): void
    {
        $this->assertTrue($this->check('FAQPage', ['questions' => [['question' => 'Draft']]], true)['valid']);
    }

    public function testObjectsMustBeAList(): void
    {
        $this->assertSame(['questions'], $this->errorFields($this->check('FAQPage', ['questions' => 'nope'], true)));
        $this->assertSame(['questions'], $this->errorFields($this->check('FAQPage', ['questions' => ['a' => ['question' => 'x']]], true)));
        $this->assertSame(['questions.0'], $this->errorFields($this->check('FAQPage', ['questions' => ['x']], true)));
    }

    public function testTokensInsideItemsSkipTypeChecks(): void
    {
        $result = $this->check('FAQPage', ['questions' => [['question' => '{{post_title}}', 'answer' => '{{post_excerpt}}']]]);
        $this->assertTrue($result['valid'], json_encode($result['errors']));
    }

    public function testSizeLimits(): void
    {
        $this->assertTrue($this->check('Person', ['name' => str_repeat('a', Validator::MAX_STRING)], true)['valid']);
        $this->assertSame(['name'], $this->errorFields($this->check('Person', ['name' => str_repeat('a', Validator::MAX_STRING + 1)], true)));
        $this->assertTrue($this->check('Article', ['description' => str_repeat('é', Validator::MAX_TEXT)], true)['valid'], 'counts characters, not bytes');
        $this->assertSame(['description'], $this->errorFields($this->check('Article', ['description' => str_repeat('a', Validator::MAX_TEXT + 1)], true)));
        $this->assertSame(['sameAs'], $this->errorFields($this->check('Organization', ['sameAs' => array_fill(0, Validator::MAX_ITEMS + 1, 'https://x.com')], true)));
        $this->assertSame(['sameAs'], $this->errorFields($this->check('Organization', ['sameAs' => [str_repeat('a', Validator::MAX_STRING + 1)]], true)));
        $this->assertSame(['questions'], $this->errorFields($this->check('FAQPage', ['questions' => array_fill(0, Validator::MAX_ITEMS + 1, ['question' => 'q', 'answer' => 'a'])], true)));
        $this->assertSame(['questions.0.answer'], $this->errorFields($this->check('FAQPage', ['questions' => [['question' => 'q', 'answer' => str_repeat('a', Validator::MAX_TEXT + 1)]]], true)));
        $this->assertSame(['name'], $this->errorFields($this->check('Person', ['name' => str_repeat('{{post_title}}', 200)], true)), 'tokens do not bypass limits');
    }

    public function testTokensSkipTypeChecksButCountAsPresent(): void
    {
        $result = $this->check('Article', [
            'headline' => '{{post_title}}',
            'author'   => '{{author_name}}',
            'image'    => '{{featured_image}}',
        ]);
        $this->assertTrue($result['valid'], json_encode($result['errors']));
    }
}
