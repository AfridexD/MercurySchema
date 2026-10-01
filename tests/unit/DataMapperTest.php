<?php

use PHPUnit\Framework\TestCase;
use UnlimitedSchema\Helpers\DataMapper;

class DataMapperTest extends TestCase
{
    public function testHasToken(): void
    {
        $this->assertTrue(DataMapper::hasToken('{{post_title}}'));
        $this->assertTrue(DataMapper::hasToken('By {{ author_name }}'));
        $this->assertTrue(DataMapper::hasToken(['a', ['{{site_name}}']]));
        $this->assertFalse(DataMapper::hasToken('plain'));
        $this->assertFalse(DataMapper::hasToken('{{Not Valid}}'));
        $this->assertFalse(DataMapper::hasToken(5));
    }

    public function testResolve(): void
    {
        $data = DataMapper::resolve(
            [
                'headline' => '{{post_title}} | {{site_name}}',
                'author'   => '{{author_name}}',
                'image'    => '{{unknown_token}}',
                'sameAs'   => ['{{home_url}}', 'https://x.com'],
                'price'    => 10,
            ],
            ['post_title' => 'Hello', 'site_name' => 'Acme', 'author_name' => 'Jo', 'home_url' => 'https://acme.test/']
        );

        $this->assertSame('Hello | Acme', $data['headline']);
        $this->assertSame('Jo', $data['author']);
        $this->assertSame('', $data['image']);
        $this->assertSame(['https://acme.test/', 'https://x.com'], $data['sameAs']);
        $this->assertSame(10, $data['price']);
    }
}
