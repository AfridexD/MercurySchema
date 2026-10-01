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

    public function testNormalize(): void
    {
        $this->assertSame([
            'post_types' => ['post', 'page'],
            'categories' => [],
            'user_roles' => [],
            'post_ids'   => [3],
        ], ConditionEvaluator::normalize([
            'post_types' => ' post, page,post',
            'categories' => 123, // Not a list: treated as empty.
            'post_ids'   => ['3', 'abc', 0],
            'unknown'    => ['x'],
        ]));
    }
}
