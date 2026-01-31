<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Condition;

use Frosh\AbandonedCart\Automation\Condition\LineItemCountCondition;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Frosh\AbandonedCart\Entity\AbandonedCartLineItemCollection;
use Frosh\AbandonedCart\Entity\AbandonedCartLineItemEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(LineItemCountCondition::class)]
class LineItemCountConditionTest extends TestCase
{
    private LineItemCountCondition $condition;

    protected function setUp(): void
    {
        $this->condition = new LineItemCountCondition();
    }

    public function testGetType(): void
    {
        static::assertSame('line_item_count', $this->condition->getType());
    }

    public function testEvaluateReturnsZeroCountWhenLineItemsIsNull(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLineItems')->willReturn(null);

        // With null line items, count should be 0
        $result = $this->condition->evaluate($cart, ['operator' => '==', 'value' => 0]);
        static::assertTrue($result);

        $result = $this->condition->evaluate($cart, ['operator' => '>=', 'value' => 1]);
        static::assertFalse($result);
    }

    #[DataProvider('operatorDataProvider')]
    public function testEvaluateWithDifferentOperators(
        string $operator,
        int $lineItemCount,
        int $configValue,
        bool $expected
    ): void {
        $lineItems = $this->createLineItemCollection($lineItemCount);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLineItems')->willReturn($lineItems);

        $config = [
            'operator' => $operator,
            'value' => $configValue,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{operator: string, lineItemCount: int, configValue: int, expected: bool}>
     */
    public static function operatorDataProvider(): iterable
    {
        // Greater than or equal (>=, gte)
        yield 'gte operator - count equals threshold' => [
            'operator' => '>=',
            'lineItemCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'gte operator - count greater than threshold' => [
            'operator' => '>=',
            'lineItemCount' => 10,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'gte operator - count less than threshold' => [
            'operator' => '>=',
            'lineItemCount' => 3,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'gte alias - count equals threshold' => [
            'operator' => 'gte',
            'lineItemCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];

        // Less than or equal (<=, lte)
        yield 'lte operator - count equals threshold' => [
            'operator' => '<=',
            'lineItemCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'lte operator - count less than threshold' => [
            'operator' => '<=',
            'lineItemCount' => 3,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'lte operator - count greater than threshold' => [
            'operator' => '<=',
            'lineItemCount' => 10,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'lte alias - count equals threshold' => [
            'operator' => 'lte',
            'lineItemCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];

        // Equal (==, eq)
        yield 'eq operator - count equals threshold' => [
            'operator' => '==',
            'lineItemCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'eq operator - count not equal to threshold' => [
            'operator' => '==',
            'lineItemCount' => 6,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'eq alias - count equals threshold' => [
            'operator' => 'eq',
            'lineItemCount' => 5,
            'configValue' => 5,
            'expected' => true,
        ];

        // Not equal (!=, neq)
        yield 'neq operator - count not equal to threshold' => [
            'operator' => '!=',
            'lineItemCount' => 6,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'neq operator - count equals threshold' => [
            'operator' => '!=',
            'lineItemCount' => 5,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'neq alias - count not equal to threshold' => [
            'operator' => 'neq',
            'lineItemCount' => 6,
            'configValue' => 5,
            'expected' => true,
        ];

        // Greater than (>, gt)
        yield 'gt operator - count greater than threshold' => [
            'operator' => '>',
            'lineItemCount' => 6,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'gt operator - count equals threshold' => [
            'operator' => '>',
            'lineItemCount' => 5,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'gt operator - count less than threshold' => [
            'operator' => '>',
            'lineItemCount' => 4,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'gt alias - count greater than threshold' => [
            'operator' => 'gt',
            'lineItemCount' => 6,
            'configValue' => 5,
            'expected' => true,
        ];

        // Less than (<, lt)
        yield 'lt operator - count less than threshold' => [
            'operator' => '<',
            'lineItemCount' => 4,
            'configValue' => 5,
            'expected' => true,
        ];
        yield 'lt operator - count equals threshold' => [
            'operator' => '<',
            'lineItemCount' => 5,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'lt operator - count greater than threshold' => [
            'operator' => '<',
            'lineItemCount' => 6,
            'configValue' => 5,
            'expected' => false,
        ];
        yield 'lt alias - count less than threshold' => [
            'operator' => 'lt',
            'lineItemCount' => 4,
            'configValue' => 5,
            'expected' => true,
        ];
    }

    public function testEvaluateWithDefaultValues(): void
    {
        $lineItems = $this->createLineItemCollection(2);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLineItems')->willReturn($lineItems);

        // Default operator is >=, default value is 1
        $result = $this->condition->evaluate($cart, []);

        static::assertTrue($result);
    }

    public function testEvaluateWithEmptyLineItemCollection(): void
    {
        $lineItems = new AbandonedCartLineItemCollection([]);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLineItems')->willReturn($lineItems);

        $config = [
            'operator' => '==',
            'value' => 0,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertTrue($result);
    }

    public function testEvaluateWithUnknownOperatorReturnsFalse(): void
    {
        $lineItems = $this->createLineItemCollection(5);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLineItems')->willReturn($lineItems);

        $config = [
            'operator' => 'invalid_operator',
            'value' => 5,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertFalse($result);
    }

    public function testEvaluateMinimumItemsRequired(): void
    {
        $lineItems = $this->createLineItemCollection(3);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLineItems')->willReturn($lineItems);

        // Require at least 5 items
        $config = [
            'operator' => '>=',
            'value' => 5,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertFalse($result);
    }

    public function testEvaluateMinimumItemsRequiredPasses(): void
    {
        $lineItems = $this->createLineItemCollection(5);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLineItems')->willReturn($lineItems);

        // Require at least 5 items
        $config = [
            'operator' => '>=',
            'value' => 5,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertTrue($result);
    }

    public function testEvaluateMaximumItemsLimit(): void
    {
        $lineItems = $this->createLineItemCollection(15);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLineItems')->willReturn($lineItems);

        // Cart should have at most 10 items
        $config = [
            'operator' => '<=',
            'value' => 10,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertFalse($result);
    }

    public function testEvaluateSingleItemCart(): void
    {
        $lineItems = $this->createLineItemCollection(1);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLineItems')->willReturn($lineItems);

        // Check for exactly 1 item
        $config = [
            'operator' => '==',
            'value' => 1,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertTrue($result);
    }

    public function testEvaluateLargeCart(): void
    {
        $lineItems = $this->createLineItemCollection(100);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLineItems')->willReturn($lineItems);

        // Check for at least 50 items
        $config = [
            'operator' => '>=',
            'value' => 50,
        ];

        $result = $this->condition->evaluate($cart, $config);

        static::assertTrue($result);
    }

    private function createLineItemCollection(int $count): AbandonedCartLineItemCollection
    {
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $item = new AbandonedCartLineItemEntity();
            $item->setId(Uuid::randomHex());
            $item->setAbandonedCartId(Uuid::randomHex());
            $item->setType('product');
            $item->setQuantity(1);
            $items[] = $item;
        }

        return new AbandonedCartLineItemCollection($items);
    }
}
