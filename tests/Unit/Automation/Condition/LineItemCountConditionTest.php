<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Condition;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Frosh\AbandonedCart\Automation\Condition\LineItemCountCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(LineItemCountCondition::class)]
class LineItemCountConditionTest extends TestCase
{
    private LineItemCountCondition $condition;

    private Context $context;

    protected function setUp(): void
    {
        $this->condition = new LineItemCountCondition();
        $this->context = Context::createDefaultContext();
    }

    public function testGetType(): void
    {
        static::assertSame('line_item_count', $this->condition->getType());
    }

    #[DataProvider('operatorMappingDataProvider')]
    public function testApplyWithDifferentOperators(string $operator, string $expectedSqlOperator): void
    {
        $query = $this->createQueryBuilder();

        $config = [
            'operator' => $operator,
            'value' => 5,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        static::assertStringContainsString($expectedSqlOperator, $sql);
    }

    /**
     * @return iterable<string, array{operator: string, expectedSqlOperator: string}>
     */
    public static function operatorMappingDataProvider(): iterable
    {
        yield 'gte operator maps to >=' => [
            'operator' => '>=',
            'expectedSqlOperator' => '>=',
        ];
        yield 'gte alias maps to >=' => [
            'operator' => 'gte',
            'expectedSqlOperator' => '>=',
        ];
        yield 'lte operator maps to <=' => [
            'operator' => '<=',
            'expectedSqlOperator' => '<=',
        ];
        yield 'lte alias maps to <=' => [
            'operator' => 'lte',
            'expectedSqlOperator' => '<=',
        ];
        yield 'gt operator maps to >' => [
            'operator' => '>',
            'expectedSqlOperator' => '>',
        ];
        yield 'gt alias maps to >' => [
            'operator' => 'gt',
            'expectedSqlOperator' => '>',
        ];
        yield 'lt operator maps to <' => [
            'operator' => '<',
            'expectedSqlOperator' => '<',
        ];
        yield 'lt alias maps to <' => [
            'operator' => 'lt',
            'expectedSqlOperator' => '<',
        ];
        yield 'eq operator maps to =' => [
            'operator' => '==',
            'expectedSqlOperator' => '=',
        ];
        yield 'eq alias maps to =' => [
            'operator' => 'eq',
            'expectedSqlOperator' => '=',
        ];
        yield 'neq operator maps to !=' => [
            'operator' => '!=',
            'expectedSqlOperator' => '!=',
        ];
        yield 'neq alias maps to !=' => [
            'operator' => 'neq',
            'expectedSqlOperator' => '!=',
        ];
    }

    public function testApplyWithDefaultValues(): void
    {
        $query = $this->createQueryBuilder();

        // Empty config should use defaults: operator >= , value 1
        $this->condition->apply($query, [], $this->context);

        $sql = $query->getSQL();
        static::assertStringContainsString('>=', $sql);

        $params = $query->getParameters();
        static::assertCount(1, $params);
        static::assertSame(1, array_values($params)[0]);
    }

    public function testApplyWithUnknownOperatorDefaultsToGte(): void
    {
        $query = $this->createQueryBuilder();

        $config = [
            'operator' => 'invalid_operator',
            'value' => 5,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        static::assertStringContainsString('>=', $sql);
    }

    public function testApplyUsesSubquery(): void
    {
        $query = $this->createQueryBuilder();

        $config = [
            'operator' => '>=',
            'value' => 1,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();

        // Should use a subquery to count line items
        static::assertStringContainsString('SELECT COUNT(*) FROM frosh_abandoned_cart_line_item', $sql);
        static::assertStringContainsString('abandoned_cart_id = cart.id', $sql);
    }

    public function testApplyWithZeroValue(): void
    {
        $query = $this->createQueryBuilder();

        $config = [
            'operator' => '==',
            'value' => 0,
        ];

        $this->condition->apply($query, $config, $this->context);

        $params = $query->getParameters();
        static::assertCount(1, $params);
        static::assertSame(0, array_values($params)[0]);
    }

    public function testApplyMinimumItemsRequired(): void
    {
        $query = $this->createQueryBuilder();

        // Require at least 5 items
        $config = [
            'operator' => '>=',
            'value' => 5,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        $params = $query->getParameters();

        static::assertStringContainsString('SELECT COUNT(*) FROM frosh_abandoned_cart_line_item', $sql);
        static::assertStringContainsString('>=', $sql);
        static::assertSame(5, array_values($params)[0]);
    }

    public function testApplyMaximumItemsLimit(): void
    {
        $query = $this->createQueryBuilder();

        // Cart should have at most 10 items
        $config = [
            'operator' => '<=',
            'value' => 10,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        $params = $query->getParameters();

        static::assertStringContainsString('SELECT COUNT(*) FROM frosh_abandoned_cart_line_item', $sql);
        static::assertStringContainsString('<=', $sql);
        static::assertSame(10, array_values($params)[0]);
    }

    public function testApplyExactItemCount(): void
    {
        $query = $this->createQueryBuilder();

        // Check for exactly 1 item
        $config = [
            'operator' => '==',
            'value' => 1,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        $params = $query->getParameters();

        static::assertStringContainsString('SELECT COUNT(*) FROM frosh_abandoned_cart_line_item', $sql);
        static::assertStringContainsString('=', $sql);
        static::assertSame(1, array_values($params)[0]);
    }

    public function testApplyAddsWhereClauseWithParameter(): void
    {
        $query = $this->createQueryBuilder();

        $config = [
            'operator' => '>=',
            'value' => 3,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        $params = $query->getParameters();

        // Should have a WHERE clause
        static::assertStringContainsString('WHERE', $sql);

        // Should have exactly one parameter
        static::assertCount(1, $params);
        static::assertSame(3, array_values($params)[0]);
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
