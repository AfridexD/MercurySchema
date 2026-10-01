<?php

use PHPUnit\Framework\TestCase;
use UnlimitedSchema\Core\SchemaType;

class SchemaTypeTest extends TestCase
{
    private static array $definitions;

    public static function setUpBeforeClass(): void
    {
        $json = json_decode(file_get_contents(dirname(__DIR__, 2) . '/assets/schema-definitions.json'), true);
        self::$definitions = $json['types'];
    }

    private function type(string $name): SchemaType
    {
        return new SchemaType($name, self::$definitions[$name]);
    }

    public function testBundledDefinitionsAreWellFormed(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Article', 'Product', 'Event', 'Organization', 'Person', 'LocalBusiness', 'Review'],
            array_keys(self::$definitions)
        );
        foreach (self::$definitions as $name => $definition) {
            $this->assertNotEmpty($definition['fields'], "$name has no fields");
            foreach ($definition['fields'] as $key => $field) {
                $this->assertContains($field['type'], SchemaType::FIELD_TYPES, "$name.$key has an unknown type");
                if ($field['type'] === 'enum') {
                    $this->assertNotEmpty($field['options'], "$name.$key enum has no options");
                }
            }
        }
    }

    public function testRequiredFieldsAndDefaults(): void
    {
        $article = $this->type('Article');
        $this->assertSame(['headline', 'author'], $article->getRequiredFields());
        $this->assertSame('{{post_title}}', $article->getDefaults()['headline']);
    }

    public function testArticleNestsAuthorAsPerson(): void
    {
        $ld = $this->type('Article')->toJsonLd([
            'headline'  => 'Hello',
            'author'    => 'Jane Doe',
            'authorUrl' => 'https://example.com/jane',
        ]);

        $this->assertSame('https://schema.org', $ld['@context']);
        $this->assertSame('Article', $ld['@type']);
        $this->assertSame('Hello', $ld['headline']);
        $this->assertSame(['@type' => 'Person', 'name' => 'Jane Doe', 'url' => 'https://example.com/jane'], $ld['author']);
    }

    public function testProductBuildsOfferAndCastsPrice(): void
    {
        $ld = $this->type('Product')->toJsonLd([
            'name'          => 'Widget',
            'price'         => '19.99',
            'priceCurrency' => 'USD',
            'availability'  => 'https://schema.org/InStock',
        ]);

        $this->assertSame('Offer', $ld['offers']['@type']);
        $this->assertSame(19.99, $ld['offers']['price']);
        $this->assertSame('USD', $ld['offers']['priceCurrency']);
    }

    public function testReviewItemTypeOverridesNestedDefault(): void
    {
        $ld = $this->type('Review')->toJsonLd([
            'itemReviewedType' => 'Book',
            'itemReviewed'     => 'Dune',
            'reviewRating'     => '5',
            'author'           => 'Sam',
        ]);

        $this->assertSame(['@type' => 'Book', 'name' => 'Dune'], $ld['itemReviewed']);
        $this->assertSame(['@type' => 'Rating', 'ratingValue' => 5], $ld['reviewRating']);
    }

    public function testEmptyAndUnknownValuesAreDropped(): void
    {
        $ld = $this->type('Organization')->toJsonLd([
            'name'    => 'Acme',
            'logo'    => '',
            'sameAs'  => ['', ' '],
            'bogus'   => 'x',
        ]);

        $this->assertSame(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'Acme'], $ld);
    }

    public function testArrayFieldAcceptsNewlineString(): void
    {
        $ld = $this->type('Organization')->toJsonLd([
            'name'   => 'Acme',
            'sameAs' => "https://x.com/acme\r\n\nhttps://github.com/acme ",
        ]);

        $this->assertSame(['https://x.com/acme', 'https://github.com/acme'], $ld['sameAs']);
    }

    public function testNestedObjectWithNoValuesIsOmitted(): void
    {
        $ld = $this->type('LocalBusiness')->toJsonLd(['name' => 'Cafe', 'streetAddress' => '']);
        $this->assertArrayNotHasKey('address', $ld);
    }
}
