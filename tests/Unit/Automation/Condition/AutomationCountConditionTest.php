<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Condition;

use Frosh\AbandonedCart\Automation\Condition\AutomationCountCondition;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AutomationCountCondition::class)]
class AutomationCountConditionTest extends TestCase
{
    private AutomationCountCondition $condition;

    protected function setUp(): void
    {
        $this->condition = new AutomationCountCondition();
    }

    public function testGetType(): void
    {
        static::assertSame('automation_count', $this->condition->getType());
    }

    #[DataProvider('operatorDataProvider')]
    public function testEvaluateWithDifferentOperators(
        string $operator,
        int $automationCount,
        int $configValue,
        bool $expected
    ): void {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getAutomationCount')->willReturn($automationCount);

        $config = [
            'operator' => $operator,
            'value' => $configValue,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{operator: string, automationCount: int, configValue: int, expected: bool}>
     */
    public static function operatorDataProvider(): iterable
    {
        // Greater than or equal (>=, gte)
        yield 'gte operator - count equals threshold' => [
            'operator' => '>=',
            'automationCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'gte operator - count greater than threshold' => [
            'operator' => '>=',
            'automationCount' => 10,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'gte operator - count less than threshold' => [
            'operator' => '>=',
            'automationCount' => 3,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'gte alias - count equals threshold' => [
            'operator' => 'gte',
            'automationCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];

        // Less than or equal (<=, lte)
        yield 'lte operator - count equals threshold' => [
            'operator' => '<=',
            'automationCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'lte operator - count less than threshold' => [
            'operator' => '<=',
            'automationCount' => 3,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'lte operator - count greater than threshold' => [
            'operator' => '<=',
            'automationCount' => 10,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'lte alias - count equals threshold' => [
            'operator' => 'lte',
            'automationCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];

        // Equal (==, eq)
        yield 'eq operator - count equals threshold' => [
            'operator' => '==',
            'automationCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'eq operator - count not equal to threshold' => [
            'operator' => '==',
            'automationCount' => 6,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'eq alias - count equals threshold' => [
            'operator' => 'eq',
            'automationCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];

        // Not equal (!=, neq)
        yield 'neq operator - count not equal to threshold' => [
            'operator' => '!=',
            'automationCount' => 6,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'neq operator - count equals threshold' => [
            'operator' => '!=',
            'automationCount' => 5,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'neq alias - count not equal to threshold' => [
            'operator' => 'neq',
            'automationCount' => 6,
            'configValue' => 5,
            'expected' => true,
        ];

        // Greater than (>, gt)
        yield 'gt operator - count greater than threshold' => [
            'operator' => '>',
            'automationCount' => 6,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'gt operator - count equals threshold' => [
            'operator' => '>',
            'automationCount' => 5,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'gt operator - count less than threshold' => [
            'operator' => '>',
            'automationCount' => 4,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'gt alias - count greater than threshold' => [
            'operator' => 'gt',
            'automationCount' => 6,
            'configValue' => 5,
            'expected' => true,
        ];

        // Less than (<, lt)
        yield 'lt operator - count less than threshold' => [
            'operator' => '<',
            'automationCount' => 4,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'lt operator - count equals threshold' => [
            'operator' => '<',
            'automationCount' => 5,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'lt operator - count greater than threshold' => [
            'operator' => '<',
            'automationCount' => 6,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'lt alias - count less than threshold' => [
            'operator' => 'lt',
            'automationCount' => 4,
            'configValue' => 5,
            'expected' => true,
        ];
    }

    public function testEvaluateWithDefaultValues(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getAutomationCount')->willReturn(0);

        // Default operator is ==, default value is 0
        $result = $this->condition->evaluate($cart, []);

        static::assertTrue($result);
    }

    public function testEvaluateWithZeroAutomationCount(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getAutomationCount')->willReturn(0);

        $config = [
            'operator' => '==',
            'value' => 0,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertTrue($result);
    }

    public function testEvaluateWithUnknownOperatorReturnsFalse(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getAutomationCount')->willReturn(5);

        $config = [
            'operator' => 'invalid_operator',
            'value' => 5,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertFalse($result);
    }

    public function testEvaluateFirstAutomation(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getAutomationCount')->willReturn(0);

        // Check if this is the first automation (count == 0)
        $config = [
            'operator' => '==',
            'value' => 0,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertTrue($result);
    }

    public function testEvaluateLimitAutomations(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getAutomationCount')->willReturn(3);

        // Check if we haven't exceeded max automations (count < 5)
        $config = [
            'operator' => '<',
            'value' => 5,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertTrue($result);
    }

    public function testEvaluateExceededLimitAutomations(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getAutomationCount')->willReturn(5);

        // Check if we've exceeded max automations (count >= 5)
        $config = [
            'operator' => '<',
            'value' => 5,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertFalse($result);
    }
}
