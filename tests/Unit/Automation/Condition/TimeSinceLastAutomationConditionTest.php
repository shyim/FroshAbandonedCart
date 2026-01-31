<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Condition;

use Frosh\AbandonedCart\Automation\Condition\TimeSinceLastAutomationCondition;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(TimeSinceLastAutomationCondition::class)]
class TimeSinceLastAutomationConditionTest extends TestCase
{
    private TimeSinceLastAutomationCondition $condition;

    private Context $context;

    protected function setUp(): void
    {
        $this->condition = new TimeSinceLastAutomationCondition();
        $this->context = Context::createDefaultContext();
    }

    public function testGetType(): void
    {
        static::assertSame('time_since_last_automation', $this->condition->getType());
    }

    public function testEvaluateReturnsTrueWhenLastAutomationAtIsNull(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLastAutomationAt')->willReturn(null);

        $result = $this->condition->evaluate($cart, ['operator' => '>=', 'value' => 24, 'unit' => 'hours'], $this->context);

        static::assertTrue($result);
    }

    #[DataProvider('operatorDataProvider')]
    public function testEvaluateWithDifferentOperators(
        string $operator,
        int $timeSinceLastAutomationInHours,
        int $configValue,
        bool $expected
    ): void {
        $lastAutomationAt = new \DateTimeImmutable(sprintf('-%d hours', $timeSinceLastAutomationInHours));

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLastAutomationAt')->willReturn($lastAutomationAt);

        $config = [
            'operator' => $operator,
            'value' => $configValue,
            'unit' => 'hours',
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{operator: string, timeSinceLastAutomationInHours: int, configValue: int, expected: bool}>
     */
    public static function operatorDataProvider(): iterable
    {
        // Greater than or equal (>=, gte)
        yield 'gte operator - time since equals threshold' => [
            'operator' => '>=',
            'timeSinceLastAutomationInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'gte operator - time since greater than threshold' => [
            'operator' => '>=',
            'timeSinceLastAutomationInHours' => 48,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'gte operator - time since less than threshold' => [
            'operator' => '>=',
            'timeSinceLastAutomationInHours' => 12,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'gte alias - time since equals threshold' => [
            'operator' => 'gte',
            'timeSinceLastAutomationInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];

        // Less than or equal (<=, lte)
        yield 'lte operator - time since equals threshold' => [
            'operator' => '<=',
            'timeSinceLastAutomationInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'lte operator - time since less than threshold' => [
            'operator' => '<=',
            'timeSinceLastAutomationInHours' => 12,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'lte operator - time since greater than threshold' => [
            'operator' => '<=',
            'timeSinceLastAutomationInHours' => 48,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'lte alias - time since equals threshold' => [
            'operator' => 'lte',
            'timeSinceLastAutomationInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];

        // Equal (==, eq)
        yield 'eq operator - time since equals threshold' => [
            'operator' => '==',
            'timeSinceLastAutomationInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'eq operator - time since not equal to threshold' => [
            'operator' => '==',
            'timeSinceLastAutomationInHours' => 25,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'eq alias - time since equals threshold' => [
            'operator' => 'eq',
            'timeSinceLastAutomationInHours' => 24,
            'configValue' => 24,
            'expected' => true,
        ];

        // Not equal (!=, neq)
        yield 'neq operator - time since not equal to threshold' => [
            'operator' => '!=',
            'timeSinceLastAutomationInHours' => 25,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'neq operator - time since equals threshold' => [
            'operator' => '!=',
            'timeSinceLastAutomationInHours' => 24,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'neq alias - time since not equal to threshold' => [
            'operator' => 'neq',
            'timeSinceLastAutomationInHours' => 25,
            'configValue' => 24,
            'expected' => true,
        ];

        // Greater than (>, gt)
        yield 'gt operator - time since greater than threshold' => [
            'operator' => '>',
            'timeSinceLastAutomationInHours' => 25,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'gt operator - time since equals threshold' => [
            'operator' => '>',
            'timeSinceLastAutomationInHours' => 24,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'gt operator - time since less than threshold' => [
            'operator' => '>',
            'timeSinceLastAutomationInHours' => 23,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'gt alias - time since greater than threshold' => [
            'operator' => 'gt',
            'timeSinceLastAutomationInHours' => 25,
            'configValue' => 24,
            'expected' => true,
        ];

        // Less than (<, lt)
        yield 'lt operator - time since less than threshold' => [
            'operator' => '<',
            'timeSinceLastAutomationInHours' => 23,
            'configValue' => 24,
            'expected' => true,
        ];
        yield 'lt operator - time since equals threshold' => [
            'operator' => '<',
            'timeSinceLastAutomationInHours' => 24,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'lt operator - time since greater than threshold' => [
            'operator' => '<',
            'timeSinceLastAutomationInHours' => 25,
            'configValue' => 24,
            'expected' => false,
        ];
        yield 'lt alias - time since less than threshold' => [
            'operator' => 'lt',
            'timeSinceLastAutomationInHours' => 23,
            'configValue' => 24,
            'expected' => true,
        ];
    }

    #[DataProvider('unitDataProvider')]
    public function testEvaluateWithDifferentUnits(
        string $unit,
        int $timeSinceInMinutes,
        int $configValue,
        bool $expected
    ): void {
        $lastAutomationAt = new \DateTimeImmutable(sprintf('-%d minutes', $timeSinceInMinutes));

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLastAutomationAt')->willReturn($lastAutomationAt);

        $config = [
            'operator' => '>=',
            'value' => $configValue,
            'unit' => $unit,
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{unit: string, timeSinceInMinutes: int, configValue: int, expected: bool}>
     */
    public static function unitDataProvider(): iterable
    {
        // Minutes
        yield 'minutes - time since equals threshold' => [
            'unit' => 'minutes',
            'timeSinceInMinutes' => 30,
            'configValue' => 30,
            'expected' => true,
        ];
        yield 'minutes - time since greater than threshold' => [
            'unit' => 'minutes',
            'timeSinceInMinutes' => 45,
            'configValue' => 30,
            'expected' => true,
        ];
        yield 'minutes - time since less than threshold' => [
            'unit' => 'minutes',
            'timeSinceInMinutes' => 15,
            'configValue' => 30,
            'expected' => false,
        ];

        // Hours
        yield 'hours - time since equals threshold' => [
            'unit' => 'hours',
            'timeSinceInMinutes' => 120, // 2 hours
            'configValue' => 2,
            'expected' => true,
        ];
        yield 'hours - time since greater than threshold' => [
            'unit' => 'hours',
            'timeSinceInMinutes' => 180, // 3 hours
            'configValue' => 2,
            'expected' => true,
        ];
        yield 'hours - time since less than threshold' => [
            'unit' => 'hours',
            'timeSinceInMinutes' => 60, // 1 hour
            'configValue' => 2,
            'expected' => false,
        ];

        // Days
        yield 'days - time since equals threshold' => [
            'unit' => 'days',
            'timeSinceInMinutes' => 2880, // 2 days
            'configValue' => 2,
            'expected' => true,
        ];
        yield 'days - time since greater than threshold' => [
            'unit' => 'days',
            'timeSinceInMinutes' => 4320, // 3 days
            'configValue' => 2,
            'expected' => true,
        ];
        yield 'days - time since less than threshold' => [
            'unit' => 'days',
            'timeSinceInMinutes' => 1440, // 1 day
            'configValue' => 2,
            'expected' => false,
        ];
    }

    public function testEvaluateWithDefaultValues(): void
    {
        // Last automation was 25 hours ago - should pass default config (>= 24 hours)
        $lastAutomationAt = new \DateTimeImmutable('-25 hours');

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLastAutomationAt')->willReturn($lastAutomationAt);

        $result = $this->condition->evaluate($cart, [], $this->context);

        static::assertTrue($result);
    }

    public function testEvaluateWithUnknownOperatorReturnsFalse(): void
    {
        $lastAutomationAt = new \DateTimeImmutable('-25 hours');

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLastAutomationAt')->willReturn($lastAutomationAt);

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
        // Last automation was 25 hours ago with unknown unit - should default to hours
        $lastAutomationAt = new \DateTimeImmutable('-25 hours');

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLastAutomationAt')->willReturn($lastAutomationAt);

        $config = [
            'operator' => '>=',
            'value' => 24,
            'unit' => 'unknown_unit',
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertTrue($result);
    }

    public function testEvaluateFirstAutomationAlwaysPasses(): void
    {
        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLastAutomationAt')->willReturn(null);

        // Even with restrictive conditions, first automation should pass
        $config = [
            'operator' => '>=',
            'value' => 1000,
            'unit' => 'days',
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertTrue($result);
    }

    public function testEvaluateRateLimiting(): void
    {
        // Last automation was 1 hour ago
        $lastAutomationAt = new \DateTimeImmutable('-1 hour');

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLastAutomationAt')->willReturn($lastAutomationAt);

        // Rate limit: must be at least 24 hours since last automation
        $config = [
            'operator' => '>=',
            'value' => 24,
            'unit' => 'hours',
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertFalse($result);
    }

    public function testEvaluateRateLimitingPasses(): void
    {
        // Last automation was 25 hours ago
        $lastAutomationAt = new \DateTimeImmutable('-25 hours');

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getLastAutomationAt')->willReturn($lastAutomationAt);

        // Rate limit: must be at least 24 hours since last automation
        $config = [
            'operator' => '>=',
            'value' => 24,
            'unit' => 'hours',
        ];

        $result = $this->condition->evaluate($cart, $config, $this->context);

        static::assertTrue($result);
    }
}
