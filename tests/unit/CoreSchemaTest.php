<?php

use PHPUnit\Framework\TestCase;
use MercurySchema\Core\Schema;

class CoreSchemaTest extends TestCase
{
    public function testSchemaCreation(): void
    {
        $schema = new Schema('Article');
        $this->assertSame('Article', $schema->getType());
        $this->assertTrue($schema->isEnabled());
        $this->assertMatchesRegularExpression('/^article-[0-9a-f]{8}$/', $schema->getId());
    }

    public function testIdsAreUnique(): void
    {
        $this->assertNotSame((new Schema('Article'))->getId(), (new Schema('Article'))->getId());
    }

    public function testIdSlugifiesType(): void
    {
        $this->assertStringStartsWith('localbusiness-', (new Schema('LocalBusiness'))->getId());
    }

    public function testSchemaDataStorage(): void
    {
        $schema = (new Schema('Product'))->setData(['name' => 'Test Product', 'price' => '99.99']);
        $this->assertSame('Test Product', $schema->getData()['name']);
    }

    public function testSetEnabledCastsToBool(): void
    {
        $schema = (new Schema('Article'))->setEnabled(0);
        $this->assertFalse($schema->isEnabled());
    }

    public function testRoundTrip(): void
    {
        $schema = (new Schema('Article', 'article-1'))
            ->setData(['headline' => 'Test'])
            ->setConditions(['post_types' => ['post']])
            ->setEnabled(false);

        $copy = Schema::fromArray($schema->toArray());
        $this->assertSame($schema->toArray(), $copy->toArray());
    }

    public function testFromArrayDefaults(): void
    {
        $schema = Schema::fromArray(['type' => 'Person', 'data' => 'not-an-array']);
        $this->assertTrue($schema->isEnabled());
        $this->assertSame([], $schema->getData());
        $this->assertSame([], $schema->getConditions());
        $this->assertNotEmpty($schema->getId());
    }

    public function testFromArrayRequiresType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Schema::fromArray(['id' => 'x']);
    }
}
