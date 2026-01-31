<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Action;

use Frosh\AbandonedCart\Automation\Action\ActionContext;
use Frosh\AbandonedCart\Automation\Action\SetCustomerCustomFieldAction;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(SetCustomerCustomFieldAction::class)]
class SetCustomerCustomFieldActionTest extends TestCase
{
    private EntityRepository&MockObject $customerRepository;

    private LoggerInterface&MockObject $logger;

    private SetCustomerCustomFieldAction $action;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(EntityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = new SetCustomerCustomFieldAction(
            $this->customerRepository,
            $this->logger
        );
    }

    public function testGetType(): void
    {
        static::assertSame('set_customer_custom_field', $this->action->getType());
    }

    public function testExecuteWithMissingCustomFieldName(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('SetCustomerCustomFieldAction: No custom field name configured');

        $this->customerRepository->expects(static::never())
            ->method('search');

        $this->customerRepository->expects(static::never())
            ->method('update');

        $this->action->execute($cart, [], $context);
    }

    public function testExecuteWithNullCustomFieldName(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('SetCustomerCustomFieldAction: No custom field name configured');

        $this->action->execute($cart, ['customFieldName' => null], $context);
    }

    public function testExecuteWithEmptyCustomFieldName(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('SetCustomerCustomFieldAction: No custom field name configured');

        $this->action->execute($cart, ['customFieldName' => ''], $context);
    }

    public function testExecuteWithCustomerNotFound(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new CustomerCollection();
        $searchResult->method('getEntities')->willReturn($collection);

        $this->customerRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->logger->expects(static::once())
            ->method('warning')
            ->with(
                'SetCustomerCustomFieldAction: Customer not found',
                static::callback(function (array $logContext) use ($cart): bool {
                    static::assertSame($cart->getCustomerId(), $logContext['customerId']);
                    static::assertSame($cart->getId(), $logContext['cartId']);

                    return true;
                })
            );

        $this->customerRepository->expects(static::never())
            ->method('update');

        $this->action->execute($cart, [
            'customFieldName' => 'my_custom_field',
            'value' => 'test_value',
        ], $context);
    }

    public function testExecuteSuccessfullyWithNewCustomField(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $customer = $this->createCustomer($cart->getCustomerId(), customFields: null);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new CustomerCollection([$customer]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->customerRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->customerRepository->expects(static::once())
            ->method('update')
            ->with(
                static::callback(function (array $data) use ($cart): bool {
                    static::assertCount(1, $data);
                    static::assertSame($cart->getCustomerId(), $data[0]['id']);
                    static::assertArrayHasKey('customFields', $data[0]);
                    static::assertSame('test_value', $data[0]['customFields']['my_custom_field']);

                    return true;
                }),
                static::anything()
            );

        $this->logger->expects(static::once())
            ->method('info')
            ->with(
                'SetCustomerCustomFieldAction: Set custom field on customer',
                static::callback(function (array $logContext) use ($cart): bool {
                    static::assertSame('my_custom_field', $logContext['customFieldName']);
                    static::assertSame('test_value', $logContext['value']);
                    static::assertSame($cart->getCustomerId(), $logContext['customerId']);
                    static::assertSame($cart->getId(), $logContext['cartId']);

                    return true;
                })
            );

        $this->action->execute($cart, [
            'customFieldName' => 'my_custom_field',
            'value' => 'test_value',
        ], $context);
    }

    public function testExecuteSuccessfullyMergesWithExistingCustomFields(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $existingCustomFields = [
            'existing_field' => 'existing_value',
            'another_field' => 123,
        ];
        $customer = $this->createCustomer($cart->getCustomerId(), customFields: $existingCustomFields);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new CustomerCollection([$customer]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->customerRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->customerRepository->expects(static::once())
            ->method('update')
            ->with(
                static::callback(function (array $data): bool {
                    $customFields = $data[0]['customFields'];
                    // Existing fields should be preserved
                    static::assertSame('existing_value', $customFields['existing_field']);
                    static::assertSame(123, $customFields['another_field']);
                    // New field should be added
                    static::assertSame('new_value', $customFields['new_field']);

                    return true;
                }),
                static::anything()
            );

        $this->action->execute($cart, [
            'customFieldName' => 'new_field',
            'value' => 'new_value',
        ], $context);
    }

    public function testExecuteSuccessfullyOverwritesExistingCustomField(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $existingCustomFields = [
            'my_custom_field' => 'old_value',
        ];
        $customer = $this->createCustomer($cart->getCustomerId(), customFields: $existingCustomFields);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new CustomerCollection([$customer]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->customerRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->customerRepository->expects(static::once())
            ->method('update')
            ->with(
                static::callback(function (array $data): bool {
                    static::assertSame('new_value', $data[0]['customFields']['my_custom_field']);

                    return true;
                }),
                static::anything()
            );

        $this->action->execute($cart, [
            'customFieldName' => 'my_custom_field',
            'value' => 'new_value',
        ], $context);
    }

    public function testExecuteWithNullValue(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $customer = $this->createCustomer($cart->getCustomerId(), customFields: ['my_field' => 'old']);
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new CustomerCollection([$customer]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->customerRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $this->customerRepository->expects(static::once())
            ->method('update')
            ->with(
                static::callback(function (array $data): bool {
                    static::assertNull($data[0]['customFields']['my_field']);

                    return true;
                }),
                static::anything()
            );

        $this->action->execute($cart, [
            'customFieldName' => 'my_field',
            'value' => null,
        ], $context);
    }

    public function testExecuteWithDifferentValueTypes(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $customer = $this->createCustomer($cart->getCustomerId());
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new CustomerCollection([$customer]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->customerRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        // Test with array value
        $arrayValue = ['key' => 'value', 'nested' => ['data']];

        $this->customerRepository->expects(static::once())
            ->method('update')
            ->with(
                static::callback(function (array $data) use ($arrayValue): bool {
                    static::assertSame($arrayValue, $data[0]['customFields']['array_field']);

                    return true;
                }),
                static::anything()
            );

        $this->action->execute($cart, [
            'customFieldName' => 'array_field',
            'value' => $arrayValue,
        ], $context);
    }

    public function testExecuteLogsErrorOnUpdateFailure(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $customer = $this->createCustomer($cart->getCustomerId());
        $searchResult = $this->createMock(EntitySearchResult::class);
        $collection = new CustomerCollection([$customer]);
        $searchResult->method('getEntities')->willReturn($collection);

        $this->customerRepository->expects(static::once())
            ->method('search')
            ->willReturn($searchResult);

        $exception = new \Exception('Database error during update');
        $this->customerRepository->expects(static::once())
            ->method('update')
            ->willThrowException($exception);

        $this->logger->expects(static::once())
            ->method('error')
            ->with(
                'SetCustomerCustomFieldAction: Failed to set custom field on customer',
                static::callback(function (array $logContext) use ($cart): bool {
                    static::assertSame('Database error during update', $logContext['error']);
                    static::assertSame('my_field', $logContext['customFieldName']);
                    static::assertSame($cart->getCustomerId(), $logContext['customerId']);
                    static::assertSame($cart->getId(), $logContext['cartId']);

                    return true;
                })
            );

        $this->action->execute($cart, [
            'customFieldName' => 'my_field',
            'value' => 'test',
        ], $context);
    }

    public function testExecuteLogsErrorOnSearchFailure(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $exception = new \Exception('Search failed');
        $this->customerRepository->expects(static::once())
            ->method('search')
            ->willThrowException($exception);

        $this->logger->expects(static::once())
            ->method('error')
            ->with(
                'SetCustomerCustomFieldAction: Failed to set custom field on customer',
                static::callback(function (array $logContext): bool {
                    static::assertSame('Search failed', $logContext['error']);

                    return true;
                })
            );

        $this->customerRepository->expects(static::never())
            ->method('update');

        $this->action->execute($cart, [
            'customFieldName' => 'my_field',
            'value' => 'test',
        ], $context);
    }

    private function createAbandonedCart(?string $customerId = null): AbandonedCartEntity
    {
        $cart = new AbandonedCartEntity();
        $cart->setId(Uuid::randomHex());
        $cart->setCustomerId($customerId ?? Uuid::randomHex());
        $cart->setSalesChannelId(Uuid::randomHex());
        $cart->setTotalPrice(99.99);
        $cart->setCurrencyIsoCode('EUR');

        return $cart;
    }

    private function createCustomer(string $id, ?array $customFields = []): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId($id);
        $customer->setCustomFields($customFields);

        return $customer;
    }
}
