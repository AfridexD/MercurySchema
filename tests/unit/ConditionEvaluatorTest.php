<?php

use PHPUnit\Framework\TestCase;
use UnlimitedSchema\Core\ConditionEvaluator;

class ConditionEvaluatorTest extends TestCase
{
    private ConditionEvaluator $evaluator;
    private array $context = [
        'post_id'    => 42,
        'post_type'  => 'post',
        'categories' => ['7', 'news'],
        'user_roles' => ['editor'],
    ];

    protected function setUp(): void
    {
        $this->evaluator = new ConditionEvaluator();
    }

    public function testEmptyConditionsAlwaysMatch(): void
    {
        $this->assertTrue($this->evaluator->evaluate([], $this->context));
        $this->assertTrue($this->evaluator->evaluate(['post_types' => [], 'categories' => []], $this->context));
    }

    public function testPostType(): void
    {
        $this->assertTrue($this->evaluator->evaluate(['post_types' => ['post', 'page']], $this->context));
        $this->assertFalse($this->evaluator->evaluate(['post_types' => ['product']], $this->context));
    }

    public function testCategoriesMatchSlugOrId(): void
    {
        $this->assertTrue($this->evaluator->evaluate(['categories' => ['news']], $this->context));
        $this->assertTrue($this->evaluator->evaluate(['categories' => [7]], $this->context));
        $this->assertFalse($this->evaluator->evaluate(['categories' => ['sport']], $this->context));
    }

    public function testUserRoles(): void
    {
        $this->assertTrue($this->evaluator->evaluate(['user_roles' => ['editor']], $this->context));
        $this->assertFalse($this->evaluator->evaluate(['user_roles' => ['administrator']], $this->context));
    }

    public function testPostIds(): void
    {
        $this->assertTrue($this->evaluator->evaluate(['post_ids' => ['42']], $this->context));
        $this->assertFalse($this->evaluator->evaluate(['post_ids' => [1, 2]], $this->context));
    }

    public function testAllConditionsMustMatch(): void
    {
        $this->assertFalse($this->evaluator->evaluate(['post_types' => ['post'], 'user_roles' => ['author']], $this->context));
    }

    public function testLocations(): void
    {
        $front = ['post_id' => 0, 'post_type' => '', 'locations' => ['front_page']];
        $this->assertTrue($this->evaluator->evaluate(['locations' => ['front_page']], $front));
        $this->assertFalse($this->evaluator->evaluate(['locations' => ['singular']], $front));
        $this->assertTrue($this->evaluator->evaluate(['locations' => ['singular', 'archive']], ['locations' => ['archive']]));
        $this->assertFalse($this->evaluator->evaluate(['locations' => ['archive']], $this->context), 'no locations in context');
    }

    public function testUnknownLocationsAreDropped(): void
    {
        $this->assertSame(['front_page'], ConditionEvaluator::normalize(['locations' => ['front_page', 'moon']])['locations']);
        // An all-unknown list normalises to "no restriction".
        $this->assertTrue($this->evaluator->evaluate(['locations' => ['moon']], $this->context));
    }

    public function testNormalize(): void
    {
        $this->assertSame([
            'post_types' => ['post', 'page'],
            'categories' => [],
            'user_roles' => [],
            'post_ids'   => [3],
            'locations'  => [],
        ], ConditionEvaluator::normalize([
            'post_types' => ' post, page,post',
            'categories' => 123, // Not a list: treated as empty.
            'post_ids'   => ['3', 'abc', 0],
            'unknown'    => ['x'],
        ]));
    }
}
