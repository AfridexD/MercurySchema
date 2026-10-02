<?php

use PHPUnit\Framework\TestCase;
use MercurySchema\Core\SchemaType;

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
            ['Organization', 'WebSite', 'WebPage', 'BreadcrumbList', 'Person', 'SiteNavigationElement',
             'Article', 'BlogPosting', 'NewsArticle', 'FAQPage', 'HowTo', 'VideoObject', 'ImageObject', 'Recipe',
             'Product', 'Review', 'Service', 'SoftwareApplication',
             'LocalBusiness', 'Event', 'Course', 'JobPosting'],
            array_keys(self::$definitions)
        );
        foreach (self::$definitions as $name => $definition) {
            $this->assertNotEmpty($definition['fields'], "$name has no fields");
            $this->assertContains($definition['group'] ?? '', ['foundations', 'content', 'commerce', 'local'], "$name has no wizard group");
            $this->assertNotEmpty($definition['icon'] ?? '', "$name has no icon");
            $this->assertNotEmpty($definition['description'] ?? '', "$name has no description");
            $this->assertFieldsWellFormed($name, $definition['fields']);
        }
    }

    private function assertFieldsWellFormed(string $where, array $fields): void
    {
        foreach ($fields as $key => $field) {
            $this->assertContains($field['type'], SchemaType::FIELD_TYPES, "$where.$key has an unknown type");
            if ($field['type'] === 'enum') {
                $this->assertNotEmpty($field['options'], "$where.$key enum has no options");
            }
            if ($field['type'] === 'objects') {
                $this->assertNotEmpty($field['itemType'] ?? '', "$where.$key has no itemType");
                $this->assertNotEmpty($field['itemFields'] ?? [], "$where.$key has no itemFields");
                $this->assertFieldsWellFormed("$where.$key", $field['itemFields']);
            }
        }
    }

    public function testFaqBuildsQuestionsWithAnswers(): void
    {
        $ld = $this->type('FAQPage')->toJsonLd([
            'questions' => [
                ['question' => 'Is it fast?', 'answer' => 'Yes.'],
                ['question' => '', 'answer' => ''],
                ['question' => 'Is it free?', 'answer' => 'Also yes.'],
            ],
        ]);

        $this->assertSame([
            ['@type' => 'Question', 'name' => 'Is it fast?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Yes.']],
            ['@type' => 'Question', 'name' => 'Is it free?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Also yes.']],
        ], $ld['mainEntity']);
    }

    public function testRecipeStepsIngredientsAndNutrition(): void
    {
        $ld = $this->type('Recipe')->toJsonLd([
            'name'             => 'Pancakes',
            'image'            => 'https://example.com/p.jpg',
            'prepTime'         => 'PT10M',
            'recipeIngredient' => "2 eggs\n1 cup flour",
            'calories'         => '300 calories',
            'steps'            => [['text' => 'Mix.'], ['text' => 'Fry.', 'name' => 'Cook']],
        ]);

        $this->assertSame(['2 eggs', '1 cup flour'], $ld['recipeIngredient']);
        $this->assertSame(['@type' => 'NutritionInformation', 'calories' => '300 calories'], $ld['nutrition']);
        $this->assertSame([
            ['@type' => 'HowToStep', 'text' => 'Mix.'],
            ['@type' => 'HowToStep', 'text' => 'Fry.', 'name' => 'Cook'],
        ], $ld['recipeInstructions']);
    }

    public function testObjectsFieldWithNoUsableItemsIsOmitted(): void
    {
        $ld = $this->type('FAQPage')->toJsonLd(['questions' => [['question' => '', 'answer' => ' '], 'junk']]);
        $this->assertArrayNotHasKey('mainEntity', $ld);
    }

    public function testFreeAppKeepsZeroPrice(): void
    {
        $ld = $this->type('SoftwareApplication')->toJsonLd(['name' => 'App', 'price' => '0', 'priceCurrency' => 'USD']);
        $this->assertSame(['@type' => 'Offer', 'price' => 0, 'priceCurrency' => 'USD'], $ld['offers']);
    }

    public function testConfigFieldsAreNeverOutput(): void
    {
        $type = $this->type('BreadcrumbList');
        $this->assertSame('breadcrumbs', $type->getGenerator());
        $this->assertSame(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList'], $type->toJsonLd(['homeLabel' => 'Start']));
        $this->assertSame('navigation', $this->type('SiteNavigationElement')->getGenerator());
        $this->assertSame('', $this->type('Article')->getGenerator());
    }

    public function testJobPostingNestsLocationAndSalary(): void
    {
        $ld = $this->type('JobPosting')->toJsonLd([
            'title'              => 'Engineer',
            'hiringOrganization' => 'Acme',
            'addressLocality'    => 'Berlin',
            'addressCountry'     => 'DE',
            'salary'             => '60000',
            'salaryCurrency'     => 'EUR',
            'salaryUnit'         => 'YEAR',
        ]);
        $this->assertSame(['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Berlin', 'addressCountry' => 'DE']], $ld['jobLocation']);
        $this->assertSame(['@type' => 'MonetaryAmount', 'value' => ['@type' => 'QuantitativeValue', 'value' => 60000, 'unitText' => 'YEAR'], 'currency' => 'EUR'], $ld['baseSalary']);
        $this->assertSame(['@type' => 'Organization', 'name' => 'Acme'], $ld['hiringOrganization']);
    }

    public function testHowToStepsAndBlogPostingInheritsArticleFields(): void
    {
        $ld = $this->type('HowTo')->toJsonLd(['name' => 'Tie a knot', 'supply' => "Rope\nPatience", 'steps' => [['text' => 'Loop.'], ['text' => 'Pull.', 'image' => 'https://x.com/a.jpg']]]);
        $this->assertSame(['Rope', 'Patience'], $ld['supply']);
        $this->assertSame([['@type' => 'HowToStep', 'text' => 'Loop.'], ['@type' => 'HowToStep', 'text' => 'Pull.', 'image' => 'https://x.com/a.jpg']], $ld['step']);

        $this->assertSame(array_keys(self::$definitions['Article']['fields']), array_keys(self::$definitions['BlogPosting']['fields']));
        $this->assertSame(['@type' => 'Person', 'name' => 'Jo'], $this->type('NewsArticle')->toJsonLd(['author' => 'Jo'])['author']);
    }

    public function testToArrayExposesPickerMetadata(): void
    {
        $arr = $this->type('FAQPage')->toArray();
        $this->assertSame('editor-help', $arr['icon']);
        $this->assertNotSame('', $arr['description']);
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
