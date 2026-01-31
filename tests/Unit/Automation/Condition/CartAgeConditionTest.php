<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Condition;

use Frosh\AbandonedCart\Automation\Condition\CartAgeCondition;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(CartAgeCondition::class)]
class CartAgeConditionTest extends TestCase
{
    private CartAgeCondition $condition;

    private Context $context;

    protected function setUp(): void
    {
        $this->condition = new CartAgeCondition();
        $this->context = Context::createDefaultContext();
    }

    public function testGetType(): void
    {
        static::assertSame('cart_age', $this->condition->getType());
    }

    public function testEvaluateReturnsFalseWhenCreatedAtIsNull(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCreatedAt')->willReturn(null);

        $result = $this->condition->evaluate($cart, ['operator' => '>=', 'value' => 24, 'unit' => 'hours'], $this->context);

        static::assertFalse($result);
    }

    #[DataProvider('operatorDataProvider')]
    public function testEvaluateWithDifferentOperators(
        string $operator,
        int $cartAgeInHours,
        int $configValue,
        bool $expected
    ): void {
        $createdAt = new \DateTimeImmutable(sprintf('-%d hours', $cartAgeInHours));

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCreatedAt')->willReturn($createdAt);

        $config = [
            'operator' => $operator,
            'value' => $configValue,
            'unit' => 'hours',
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{operator: string, cartAgeInHours: int, configValue: int, expected: bool}>
     */
    public static function operatorDataProvider(): iterable
    {
        // Greater than or equal (>=, gte)
        yield 'gte operator - cart age equals threshold' => [
            'operator' => '>=',
            'cartAgeInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'gte operator - cart age greater than threshold' => [
            'operator' => '>=',
            'cartAgeInHours' => 48,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'gte operator - cart age less than threshold' => [
            'operator' => '>=',
            'cartAgeInHours' => 12,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'gte alias - cart age equals threshold' => [
            'operator' => 'gte',
            'cartAgeInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];

        // Less than or equal (<=, lte)
        yield 'lte operator - cart age equals threshold' => [
            'operator' => '<=',
            'cartAgeInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'lte operator - cart age less than threshold' => [
            'operator' => '<=',
            'cartAgeInHours' => 12,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'lte operator - cart age greater than threshold' => [
            'operator' => '<=',
            'cartAgeInHours' => 48,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'lte alias - cart age equals threshold' => [
            'operator' => 'lte',
            'cartAgeInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];

        // Equal (==, eq)
        yield 'eq operator - cart age equals threshold' => [
            'operator' => '==',
            'cartAgeInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'eq operator - cart age not equal to threshold' => [
            'operator' => '==',
            'cartAgeInHours' => 25,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'eq alias - cart age equals threshold' => [
            'operator' => 'eq',
            'cartAgeInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];

        // Not equal (!=, neq)
        yield 'neq operator - cart age not equal to threshold' => [
            'operator' => '!=',
            'cartAgeInHours' => 25,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'neq operator - cart age equals threshold' => [
            'operator' => '!=',
            'cartAgeInHours' => 24,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'neq alias - cart age not equal to threshold' => [
            'operator' => 'neq',
            'cartAgeInHours' => 25,
            'configValue' => 24,
            'expected' => true,
        ];

        // Greater than (>, gt)
        yield 'gt operator - cart age greater than threshold' => [
            'operator' => '>',
            'cartAgeInHours' => 25,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'gt operator - cart age equals threshold' => [
            'operator' => '>',
            'cartAgeInHours' => 24,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'gt operator - cart age less than threshold' => [
            'operator' => '>',
            'cartAgeInHours' => 23,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'gt alias - cart age greater than threshold' => [
            'operator' => 'gt',
            'cartAgeInHours' => 25,
            'configValue' => 24,
            'expected' => true,
        ];

        // Less than (<, lt)
        yield 'lt operator - cart age less than threshold' => [
            'operator' => '<',
            'cartAgeInHours' => 23,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'lt operator - cart age equals threshold' => [
            'operator' => '<',
            'cartAgeInHours' => 24,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'lt operator - cart age greater than threshold' => [
            'operator' => '<',
            'cartAgeInHours' => 25,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'lt alias - cart age less than threshold' => [
            'operator' => 'lt',
            'cartAgeInHours' => 23,
            'configValue' => 24,
            'expected' => true,
        ];
    }

    #[DataProvider('unitDataProvider')]
    public function testEvaluateWithDifferentUnits(
        string $unit,
        int $cartAgeInMinutes,
        int $configValue,
        bool $expected
    ): void {
        $createdAt = new \DateTimeImmutable(sprintf('-%d minutes', $cartAgeInMinutes));

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCreatedAt')->willReturn($createdAt);

        $config = [
            'operator' => '>=',
            'value' => $configValue,
            'unit' => $unit,
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{unit: string, cartAgeInMinutes: int, configValue: int, expected: bool}>
     */
    public static function unitDataProvider(): iterable
    {
        // Minutes
        yield 'minutes - age equals threshold' => [
            'unit' => 'minutes',
            'cartAgeInMinutes' => 30,
            'configValue' => 30,
            'expected' => true,
        ];
        yield 'minutes - age greater than threshold' => [
            'unit' => 'minutes',
            'cartAgeInMinutes' => 45,
            'configValue' => 30,
            'expected' => true,
        ];
        yield 'minutes - age less than threshold' => [
            'unit' => 'minutes',
            'cartAgeInMinutes' => 15,
            'configValue' => 30,
            'expected' => false,
        ];

        // Hours
        yield 'hours - age equals threshold' => [
            'unit' => 'hours',
            'cartAgeInMinutes' => 120, // 2 hours
            'configValue' => 2,
            'expected' => true,
        ];
        yield 'hours - age greater than threshold' => [
            'unit' => 'hours',
            'cartAgeInMinutes' => 180, // 3 hours
            'configValue' => 2,
            'expected' => true,
        ];
        yield 'hours - age less than threshold' => [
            'unit' => 'hours',
            'cartAgeInMinutes' => 60, // 1 hour
            'configValue' => 2,
            'expected' => false,
        ];

        // Days
        yield 'days - age equals threshold' => [
            'unit' => 'days',
            'cartAgeInMinutes' => 2880, // 2 days
            'configValue' => 2,
            'expected' => true,
        ];
        yield 'days - age greater than threshold' => [
            'unit' => 'days',
            'cartAgeInMinutes' => 4320, // 3 days
            'configValue' => 2,
            'expected' => true,
        ];
        yield 'days - age less than threshold' => [
            'unit' => 'days',
            'cartAgeInMinutes' => 1440, // 1 day
            'configValue' => 2,
            'expected' => false,
        ];
    }

    public function testEvaluateWithDefaultValues(): void
    {
        // Cart created 25 hours ago should pass default config (>= 24 hours)
        $createdAt = new \DateTimeImmutable('-25 hours');

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCreatedAt')->willReturn($createdAt);

        $result = $this->condition->evaluate($cart, [], $this->context);

        static::assertTrue($result);
    }

    public function testEvaluateWithUnknownOperatorReturnsFalse(): void
    {
        $createdAt = new \DateTimeImmutable('-25 hours');

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCreatedAt')->willReturn($createdAt);

        $config = [
            'operator' => 'invalid_operator',
            'value' => 24,
            'unit' => 'hours',
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertFalse($result);
    }

    public function testEvaluateWithUnknownUnitDefaultsToHours(): void
    {
        // Cart created 25 hours ago with unknown unit should default to hours
        $createdAt = new \DateTimeImmutable('-25 hours');

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCreatedAt')->willReturn($createdAt);

        $config = [
            'operator' => '>=',
            'value' => 24,
            'unit' => 'unknown_unit',
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertTrue($result);
    }
}
