<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Condition;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Frosh\AbandonedCart\Automation\Condition\CustomerTagCondition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(CustomerTagCondition::class)]
class CustomerTagConditionTest extends TestCase
{
    private CustomerTagCondition $condition;

    private Context $context;

    protected function setUp(): void
    {
        $this->condition = new CustomerTagCondition();
        $this->context = Context::createDefaultContext();
    }

    public function testGetType(): void
    {
        static::assertSame('customer_tag', $this->condition->getType());
    }

    public function testApplyDoesNothingWhenTagIdIsNull(): void
    {
        $query = $this->createQueryBuilder();

        $this->condition->apply($query, ['tagId' => null], $this->context);

        $sql = $query->getSQL();
        $params = $query->getParameters();

        // Should not add any WHERE clause
        static::assertStringNotContainsString('WHERE', $sql);
        static::assertEmpty($params);
    }

    public function testApplyDoesNothingWhenTagIdIsMissing(): void
    {
        $query = $this->createQueryBuilder();

        $this->condition->apply($query, [], $this->context);

        $sql = $query->getSQL();
        $params = $query->getParameters();

        // Should not add any WHERE clause
        static::assertStringNotContainsString('WHERE', $sql);
        static::assertEmpty($params);
    }

    public function testApplyWithExistsSubquery(): void
    {
        $query = $this->createQueryBuilder();
        $tagId = Uuid::randomHex();

        $config = [
            'tagId' => $tagId,
            'negate' => false,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        $params = $query->getParameters();

        // Should use EXISTS subquery
        static::assertStringContainsString('EXISTS', $sql);
        static::assertStringContainsString('SELECT 1 FROM customer_tag', $sql);
        static::assertStringContainsString('customer_tag.customer_id = cart.customer_id', $sql);
        static::assertStringContainsString('customer_tag.tag_id', $sql);

        // Should have parameter for tag_id (as bytes)
        static::assertCount(1, $params);
        static::assertEquals(Uuid::fromHexToBytes($tagId), array_values($params)[0]);
    }

    public function testApplyWithNegatedCondition(): void
    {
        $query = $this->createQueryBuilder();
        $tagId = Uuid::randomHex();

        $config = [
            'tagId' => $tagId,
            'negate' => true,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();

        // Should use NOT EXISTS subquery
        static::assertStringContainsString('NOT EXISTS', $sql);
        static::assertStringContainsString('SELECT 1 FROM customer_tag', $sql);
    }

    #[DataProvider('negateDataProvider')]
    public function testApplyWithDifferentNegateValues(mixed $negate, bool $expectNotExists): void
    {
        $query = $this->createQueryBuilder();
        $tagId = Uuid::randomHex();

        $config = [
            'tagId' => $tagId,
            'negate' => $negate,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();

        if ($expectNotExists) {
            static::assertStringContainsString('NOT EXISTS', $sql);
        } else {
            static::assertStringContainsString('EXISTS', $sql);
            static::assertStringNotContainsString('NOT EXISTS', $sql);
        }
    }

    /**
     * @return iterable<string, array{negate: mixed, expectNotExists: bool}>
     */
    public static function negateDataProvider(): iterable
    {
        yield 'negate true' => [
            'negate' => true,
            'expectNotExists' => true,
        ];
        yield 'negate false' => [
            'negate' => false,
            'expectNotExists' => false,
        ];
        yield 'negate truthy string' => [
            'negate' => '1',
            'expectNotExists' => true,
        ];
        yield 'negate falsy string' => [
            'negate' => '0',
            'expectNotExists' => false,
        ];
        yield 'negate empty string' => [
            'negate' => '',
            'expectNotExists' => false,
        ];
    }

    public function testApplyWithDefaultNegate(): void
    {
        $query = $this->createQueryBuilder();
        $tagId = Uuid::randomHex();

        // Default negate is false
        $config = [
            'tagId' => $tagId,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();

        // Should use EXISTS (not NOT EXISTS)
        static::assertStringContainsString('EXISTS', $sql);
        static::assertStringNotContainsString('NOT EXISTS', $sql);
    }

    public function testApplyAddsWhereClauseWithParameter(): void
    {
        $query = $this->createQueryBuilder();
        $tagId = Uuid::randomHex();

        $config = [
            'tagId' => $tagId,
            'negate' => false,
        ];

        $this->condition->apply($query, $config, $this->context);

        $sql = $query->getSQL();
        $params = $query->getParameters();

        // Should have a WHERE clause
        static::assertStringContainsString('WHERE', $sql);

        // Should have exactly one parameter
        static::assertCount(1, $params);

        // Parameter should be binary representation of tag ID
        static::assertEquals(Uuid::fromHexToBytes($tagId), array_values($params)[0]);
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
