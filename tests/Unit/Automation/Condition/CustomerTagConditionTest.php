<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Condition;

use Frosh\AbandonedCart\Automation\Condition\CustomerTagCondition;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tag\TagEntity;

#[CoversClass(CustomerTagCondition::class)]
class CustomerTagConditionTest extends TestCase
{
    public function testGetType(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $condition = new CustomerTagCondition($customerRepository);

        static::assertSame('customer_tag', $condition->getType());
    }

    public function testEvaluateReturnsFalseWhenTagIdIsNull(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $condition = new CustomerTagCondition($customerRepository);

        $cart = $this->createMock(AbandonedCartEntity::class);

        $result = $condition->evaluate($cart, ['tagId' => null]);

        static::assertFalse($result);
    }

    public function testEvaluateReturnsFalseWhenTagIdIsMissing(): void
    {
        $customerRepository = $this->createMock(EntityRepository::class);
        $condition = new CustomerTagCondition($customerRepository);

        $cart = $this->createMock(AbandonedCartEntity::class);

        $result = $condition->evaluate($cart, []);

        static::assertFalse($result);
    }

    public function testEvaluateReturnsNegateWhenCustomerNotFound(): void
    {
        $customerId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $customerCollection = new CustomerCollection([]);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn($customerCollection);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($searchResult);

        $condition = new CustomerTagCondition($customerRepository);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCustomerId')->willReturn($customerId);

        // When negate is false and customer not found, return false
        $result = $condition->evaluate($cart, ['tagId' => $tagId, 'negate' => false]);
        static::assertFalse($result);

        // When negate is true and customer not found, return true
        $result = $condition->evaluate($cart, ['tagId' => $tagId, 'negate' => true]);
        static::assertTrue($result);
    }

    public function testEvaluateReturnsNegateWhenTagsIsNull(): void
    {
        $customerId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getTags')->willReturn(null);

        $customerCollection = new CustomerCollection([$customer]);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn($customerCollection);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($searchResult);

        $condition = new CustomerTagCondition($customerRepository);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCustomerId')->willReturn($customerId);

        // When negate is false and tags is null, return false
        $result = $condition->evaluate($cart, ['tagId' => $tagId, 'negate' => false]);
        static::assertFalse($result);

        // When negate is true and tags is null, return true
        $result = $condition->evaluate($cart, ['tagId' => $tagId, 'negate' => true]);
        static::assertTrue($result);
    }

    #[DataProvider('tagConditionDataProvider')]
    public function testEvaluateWithTagConditions(
        bool $hasTag,
        bool $negate,
        bool $expected
    ): void {
        $customerId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $tag = new TagEntity();
        $tag->setId($tagId);
        $tag->setName('Test Tag');

        $tags = new TagCollection($hasTag ? [$tag] : []);

        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getTags')->willReturn($tags);

        $customerCollection = new CustomerCollection([$customer]);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn($customerCollection);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($searchResult);

        $condition = new CustomerTagCondition($customerRepository);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCustomerId')->willReturn($customerId);

        $config = [
            'tagId' => $tagId,
            'negate' => $negate,
        ];

        $result = $condition->evaluate($cart, $config);

        static::assertSame($expected, $result);
    }

    /**
     * @return iterable<string, array{hasTag: bool, negate: bool, expected: bool}>
     */
    public static function tagConditionDataProvider(): iterable
    {
        yield 'customer has tag - not negated - returns true' => [
            'hasTag' => true,
            'negate' => false,
            'expected' => true,
        ];
        yield 'customer has tag - negated - returns false' => [
            'hasTag' => true,
            'negate' => true,
            'expected' => false,
        ];
        yield 'customer does not have tag - not negated - returns false' => [
            'hasTag' => false,
            'negate' => false,
            'expected' => false,
        ];
        yield 'customer does not have tag - negated - returns true' => [
            'hasTag' => false,
            'negate' => true,
            'expected' => true,
        ];
    }

    public function testEvaluateWithDefaultNegate(): void
    {
        $customerId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $tag = new TagEntity();
        $tag->setId($tagId);
        $tag->setName('Test Tag');

        $tags = new TagCollection([$tag]);

        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getTags')->willReturn($tags);

        $customerCollection = new CustomerCollection([$customer]);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn($customerCollection);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($searchResult);

        $condition = new CustomerTagCondition($customerRepository);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCustomerId')->willReturn($customerId);

        // Default negate is false
        $result = $condition->evaluate($cart, ['tagId' => $tagId]);

        static::assertTrue($result);
    }

    public function testEvaluateWithMultipleTags(): void
    {
        $customerId = Uuid::randomHex();
        $tagId1 = Uuid::randomHex();
        $tagId2 = Uuid::randomHex();
        $tagId3 = Uuid::randomHex();

        $tag1 = new TagEntity();
        $tag1->setId($tagId1);
        $tag1->setName('Tag 1');

        $tag2 = new TagEntity();
        $tag2->setId($tagId2);
        $tag2->setName('Tag 2');

        $tags = new TagCollection([$tag1, $tag2]);

        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getTags')->willReturn($tags);

        $customerCollection = new CustomerCollection([$customer]);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn($customerCollection);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($searchResult);

        $condition = new CustomerTagCondition($customerRepository);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCustomerId')->willReturn($customerId);

        // Check for tag that customer has
        $result = $condition->evaluate($cart, ['tagId' => $tagId1, 'negate' => false]);
        static::assertTrue($result);

        // Check for another tag that customer has
        $result = $condition->evaluate($cart, ['tagId' => $tagId2, 'negate' => false]);
        static::assertTrue($result);

        // Check for tag that customer does not have
        $result = $condition->evaluate($cart, ['tagId' => $tagId3, 'negate' => false]);
        static::assertFalse($result);
    }

    public function testEvaluateWithNegateAsString(): void
    {
        $customerId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $tag = new TagEntity();
        $tag->setId($tagId);
        $tag->setName('Test Tag');

        $tags = new TagCollection([$tag]);

        $customer = $this->createMock(CustomerEntity::class);
        $customer->method('getTags')->willReturn($tags);

        $customerCollection = new CustomerCollection([$customer]);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('getEntities')->willReturn($customerCollection);

        $customerRepository = $this->createMock(EntityRepository::class);
        $customerRepository->method('search')->willReturn($searchResult);

        $condition = new CustomerTagCondition($customerRepository);

        $cart = $this->createMock(AbandonedCartEntity::class);
        $cart->method('getCustomerId')->willReturn($customerId);

        // Test with negate as truthy string value (will be cast to bool)
        $result = $condition->evaluate($cart, ['tagId' => $tagId, 'negate' => '1']);
        static::assertFalse($result);

        // Test with negate as falsy string value
        $result = $condition->evaluate($cart, ['tagId' => $tagId, 'negate' => '0']);
        static::assertTrue($result);
    }
}
