<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Condition;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Frosh\AbandonedCart\Automation\Condition\CartAgeCondition;
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

    #[DataProvider('operatorMappingDataProvider')]
    public function testApplyWithDifferentOperators(string $operator, string $expectedSqlOperator): void
    {
        $query = $this->createQueryBuilder();

        $config = [
            'operator' => $operator,
            'value' => 24,
            'unit' => 'hours',
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        static::assertStringContainsString("cart.created_at {$expectedSqlOperator}", $sql);
    }

    /**
     * @return iterable<string, array{operator: string, expectedSqlOperator: string}>
     */
    public static function operatorMappingDataProvider(): iterable
    {
        // Cart age >= X means created_at <= threshold (cart was created at or before threshold)
        yield 'gte operator maps to <=' => [
            'operator' => '>=',
            'expectedSqlOperator' => '<=',
        ];
        yield 'gte alias maps to <=' => [
            'operator' => 'gte',
            'expectedSqlOperator' => '<=',
        ];
        yield 'lte operator maps to >=' => [
            'operator' => '<=',
            'expectedSqlOperator' => '>=',
        ];
        yield 'lte alias maps to >=' => [
            'operator' => 'lte',
            'expectedSqlOperator' => '>=',
        ];
        yield 'gt operator maps to <' => [
            'operator' => '>',
            'expectedSqlOperator' => '<',
        ];
        yield 'gt alias maps to <' => [
            'operator' => 'gt',
            'expectedSqlOperator' => '<',
        ];
        yield 'lt operator maps to >' => [
            'operator' => '<',
            'expectedSqlOperator' => '>',
        ];
        yield 'lt alias maps to >' => [
            'operator' => 'lt',
            'expectedSqlOperator' => '>',
        ];
    }

    #[DataProvider('unitDataProvider')]
    public function testApplyWithDifferentUnits(string $unit, int $configValue, int $expectedSeconds): void
    {
        $query = $this->createQueryBuilder();
        $beforeTime = new \DateTimeImmutable();

        $config = [
            'operator' => '>=',
            'value' => $configValue,
            'unit' => $unit,
        ];

        $this->condition->apply($query, $config, $this->context);

        // Get the parameter value
        $params = $query->getParameters();
        static::assertCount(1, $params);
        $paramValue = array_values($params)[0];

        // The threshold should be approximately now minus the expected seconds
        $expectedThreshold = $beforeTime->modify("-{$expectedSeconds} seconds");
        $actualThreshold = new \DateTimeImmutable($paramValue);

        // Allow 2 seconds tolerance for test execution time
        $diff = abs($expectedThreshold->getTimestamp() - $actualThreshold->getTimestamp());
        static::assertLessThanOrEqual(2, $diff, "Threshold difference too large: {$diff} seconds");
    }

    /**
     * @return iterable<string, array{unit: string, configValue: int, expectedSeconds: int}>
     */
    public static function unitDataProvider(): iterable
    {
        yield 'minutes unit' => [
            'unit' => 'minutes',
            'configValue' => 30,
            'expectedSeconds' => 30 * 60,
        ];
        yield 'hours unit' => [
            'unit' => 'hours',
            'configValue' => 2,
            'expectedSeconds' => 2 * 3600,
        ];
        yield 'days unit' => [
            'unit' => 'days',
            'configValue' => 2,
            'expectedSeconds' => 2 * 86400,
        ];
        yield 'unknown unit defaults to hours' => [
            'unit' => 'unknown',
            'configValue' => 2,
            'expectedSeconds' => 2 * 3600,
        ];
    }

    public function testApplyWithDefaultValues(): void
    {
        $query = $this->createQueryBuilder();

        // Empty config should use defaults: operator >= , value 24, unit hours
        $this->condition->apply($query, [], $this->context);

        $sql = $query->getSQL();
        // >= maps to <=
        static::assertStringContainsString('cart.created_at <=', $sql);

        $params = $query->getParameters();
        static::assertCount(1, $params);
    }

    public function testApplyWithUnknownOperatorDefaultsToLte(): void
    {
        $query = $this->createQueryBuilder();

        $config = [
            'operator' => 'invalid_operator',
            'value' => 24,
            'unit' => 'hours',
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        // unknown operator defaults to <=
        static::assertStringContainsString('cart.created_at <=', $sql);
    }

    public function testApplyAddsWhereClauseWithParameter(): void
    {
        $query = $this->createQueryBuilder();

        $config = [
            'operator' => '>=',
            'value' => 24,
            'unit' => 'hours',
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        $params = $query->getParameters();

        // Should have a WHERE clause
        static::assertStringContainsString('WHERE', $sql);
        static::assertStringContainsString('cart.created_at', $sql);

        // Should have exactly one parameter
        static::assertCount(1, $params);

        // Parameter should be a datetime string
        $paramValue = array_values($params)[0];
        static::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $paramValue);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $query = $connection->createQueryBuilder();
        $query->select('cart.id')
            ->from('frosh_abandoned_cart', 'cart');

        return $query;
    }
}
