<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Condition;

use Frosh\AbandonedCart\Automation\Condition\CartValueCondition;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(CartValueCondition::class)]
class CartValueConditionTest extends TestCase
{
    private CartValueCondition $condition;

    private Context $context;

    protected function setUp(): void
    {
        $this->condition = new CartValueCondition();
        $this->context = Context::createDefaultContext();
    }

    public function testGetType(): void
    {
        static::assertSame('cart_value', $this->condition->getType());
    }

    #[DataProvider('operatorDataProvider')]
    public function testEvaluateWithDifferentOperators(
        string $operator,
        float $cartTotalPrice,
        float $configValue,
        bool $expected
    ): void {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getTotalPrice')->willReturn($cartTotalPrice);

        $config = [
            'operator' => $operator,
            'value' => $configValue,
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{operator: string, cartTotalPrice: float, configValue: float, expected: bool}>
     */
    public static function operatorDataProvider(): iterable
    {
        // Greater than or equal (>=, gte)
        yield 'gte operator - cart value equals threshold' => [
            'operator' => '>=',
            'cartTotalPrice' => 100.0,
            'configValue' => 100.0,
            'expected' => true,
        ];
        yield 'gte operator - cart value greater than threshold' => [
            'operator' => '>=',
            'cartTotalPrice' => 150.0,
            'configValue' => 100.0,
            'expected' => true,
        ];
        yield 'gte operator - cart value less than threshold' => [
            'operator' => '>=',
            'cartTotalPrice' => 50.0,
            'configValue' => 100.0,
            'expected' => false,
        ];
        yield 'gte alias - cart value equals threshold' => [
            'operator' => 'gte',
            'cartTotalPrice' => 100.0,
            'configValue' => 100.0,
            'expected' => true,
        ];

        // Less than or equal (<=, lte)
        yield 'lte operator - cart value equals threshold' => [
            'operator' => '<=',
            'cartTotalPrice' => 100.0,
            'configValue' => 100.0,
            'expected' => true,
        ];
        yield 'lte operator - cart value less than threshold' => [
            'operator' => '<=',
            'cartTotalPrice' => 50.0,
            'configValue' => 100.0,
            'expected' => true,
        ];
        yield 'lte operator - cart value greater than threshold' => [
            'operator' => '<=',
            'cartTotalPrice' => 150.0,
            'configValue' => 100.0,
            'expected' => false,
        ];
        yield 'lte alias - cart value equals threshold' => [
            'operator' => 'lte',
            'cartTotalPrice' => 100.0,
            'configValue' => 100.0,
            'expected' => true,
        ];

        // Equal (==, eq) - uses floating point comparison with tolerance
        yield 'eq operator - cart value equals threshold' => [
            'operator' => '==',
            'cartTotalPrice' => 100.0,
            'configValue' => 100.0,
            'expected' => true,
        ];
        yield 'eq operator - cart value not equal to threshold' => [
            'operator' => '==',
            'cartTotalPrice' => 100.5,
            'configValue' => 100.0,
            'expected' => false,
        ];
        yield 'eq operator - cart value within tolerance' => [
            'operator' => '==',
            'cartTotalPrice' => 100.0005,
            'configValue' => 100.0,
            'expected' => true,
        ];
        yield 'eq alias - cart value equals threshold' => [
            'operator' => 'eq',
            'cartTotalPrice' => 100.0,
            'configValue' => 100.0,
            'expected' => true,
        ];

        // Not equal (!=, neq) - uses floating point comparison with tolerance
        yield 'neq operator - cart value not equal to threshold' => [
            'operator' => '!=',
            'cartTotalPrice' => 100.5,
            'configValue' => 100.0,
            'expected' => true,
        ];
        yield 'neq operator - cart value equals threshold' => [
            'operator' => '!=',
            'cartTotalPrice' => 100.0,
            'configValue' => 100.0,
            'expected' => false,
        ];
        yield 'neq operator - cart value within tolerance is equal' => [
            'operator' => '!=',
            'cartTotalPrice' => 100.0005,
            'configValue' => 100.0,
            'expected' => false,
        ];
        yield 'neq alias - cart value not equal to threshold' => [
            'operator' => 'neq',
            'cartTotalPrice' => 100.5,
            'configValue' => 100.0,
            'expected' => true,
        ];

        // Greater than (>, gt)
        yield 'gt operator - cart value greater than threshold' => [
            'operator' => '>',
            'cartTotalPrice' => 100.5,
            'configValue' => 100.0,
            'expected' => true,
        ];
        yield 'gt operator - cart value equals threshold' => [
            'operator' => '>',
            'cartTotalPrice' => 100.0,
            'configValue' => 100.0,
            'expected' => false,
        ];
        yield 'gt operator - cart value less than threshold' => [
            'operator' => '>',
            'cartTotalPrice' => 99.5,
            'configValue' => 100.0,
            'expected' => false,
        ];
        yield 'gt alias - cart value greater than threshold' => [
            'operator' => 'gt',
            'cartTotalPrice' => 100.5,
            'configValue' => 100.0,
            'expected' => true,
        ];

        // Less than (<, lt)
        yield 'lt operator - cart value less than threshold' => [
            'operator' => '<',
            'cartTotalPrice' => 99.5,
            'configValue' => 100.0,
            'expected' => true,
        ];
        yield 'lt operator - cart value equals threshold' => [
            'operator' => '<',
            'cartTotalPrice' => 100.0,
            'configValue' => 100.0,
            'expected' => false,
        ];
        yield 'lt operator - cart value greater than threshold' => [
            'operator' => '<',
            'cartTotalPrice' => 100.5,
            'configValue' => 100.0,
            'expected' => false,
        ];
        yield 'lt alias - cart value less than threshold' => [
            'operator' => 'lt',
            'cartTotalPrice' => 99.5,
            'configValue' => 100.0,
            'expected' => true,
        ];
    }

    public function testEvaluateWithDefaultValues(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getTotalPrice')->willReturn(50.0);

        // Default operator is >=, default value is 0
        $result = $this->condition->evaluate($cart, [], $this->context);

        static::assertTrue($result);
    }

    public function testEvaluateWithZeroCartValue(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getTotalPrice')->willReturn(0.0);

        $config = [
            'operator' => '>=',
            'value' => 0.0,
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertTrue($result);
    }

    public function testEvaluateWithUnknownOperatorReturnsFalse(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getTotalPrice')->willReturn(100.0);

        $config = [
            'operator' => 'invalid_operator',
            'value' => 50.0,
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertFalse($result);
    }

    #[DataProvider('floatingPointEdgeCaseDataProvider')]
    public function testEvaluateFloatingPointEdgeCases(
        string $operator,
        float $cartTotalPrice,
        float $configValue,
        bool $expected
    ): void {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getTotalPrice')->willReturn($cartTotalPrice);

        $config = [
            'operator' => $operator,
            'value' => $configValue,
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{operator: string, cartTotalPrice: float, configValue: float, expected: bool}>
     */
    public static function floatingPointEdgeCaseDataProvider(): iterable
    {
        yield 'very small difference should be equal' => [
            'operator' => '==',
            'cartTotalPrice' => 99.999999,
            'configValue' => 100.0,
            'expected' => true,
        ];
        yield 'very small difference should not be not equal' => [
            'operator' => '!=',
            'cartTotalPrice' => 99.999999,
            'configValue' => 100.0,
            'expected' => false,
        ];
        yield 'large values with small difference' => [
            'operator' => '==',
            'cartTotalPrice' => 10000.0001,
            'configValue' => 10000.0,
            'expected' => true,
        ];
        yield 'negative values' => [
            'operator' => '>=',
            'cartTotalPrice' => -50.0,
            'configValue' => -100.0,
            'expected' => true,
        ];
    }
}
