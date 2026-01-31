<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Action;

use Frosh\AbandonedCart\Automation\Action\ActionContext;
use Frosh\AbandonedCart\Automation\Action\AddCustomerTagAction;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(AddCustomerTagAction::class)]
class AddCustomerTagActionTest extends TestCase
{
    private EntityRepository&MockObject $customerRepository;

    private LoggerInterface&MockObject $logger;

    private AddCustomerTagAction $action;

    protected function setUp(): void
    {
        $this->customerRepository = $this->createMock(EntityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = new AddCustomerTagAction(
            $this->customerRepository,
            $this->logger
        );
    }

    public function testGetType(): void
    {
        static::assertSame('add_customer_tag', $this->action->getType());
    }

    public function testExecuteWithMissingTagId(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('AddCustomerTagAction: No tag ID configured');

        $this->customerRepository->expects(static::never())
            ->method('update');

        $this->action->execute($cart, [], $context);
    }

    public function testExecuteWithNullTagId(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('AddCustomerTagAction: No tag ID configured');

        $this->customerRepository->expects(static::never())
            ->method('update');

        $this->action->execute($cart, ['tagId' => null], $context);
    }

    public function testExecuteSuccessfully(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();
        $tagId = Uuid::randomHex();

        $this->customerRepository->expects(static::once())
            ->method('update')
            ->with(
                static::callback(function (array $data) use ($cart, $tagId): bool {
                    static::assertCount(1, $data);
                    static::assertSame($cart->getCustomerId(), $data[0]['id']);
                    static::assertArrayHasKey('tags', $data[0]);
                    static::assertCount(1, $data[0]['tags']);
                    static::assertSame($tagId, $data[0]['tags'][0]['id']);

                    return true;
                }),
                static::anything()
            );

        $this->logger->expects(static::once())
            ->method('info')
            ->with(
                'AddCustomerTagAction: Added tag to customer',
                static::callback(function (array $logContext) use ($tagId, $cart): bool {
                    static::assertSame($tagId, $logContext['tagId']);
                    static::assertSame($cart->getCustomerId(), $logContext['customerId']);
                    static::assertSame($cart->getId(), $logContext['cartId']);

                    return true;
                })
            );

        $this->action->execute($cart, ['tagId' => $tagId], $context);
    }

    public function testExecuteLogsErrorOnUpdateFailure(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();
        $tagId = Uuid::randomHex();

        $exception = new \Exception('Tag does not exist');
        $this->customerRepository->expects(static::once())
            ->method('update')
            ->willThrowException($exception);

        $this->logger->expects(static::once())
            ->method('error')
            ->with(
                'AddCustomerTagAction: Failed to add tag to customer',
                static::callback(function (array $logContext) use ($tagId, $cart): bool {
                    static::assertSame('Tag does not exist', $logContext['error']);
                    static::assertSame($tagId, $logContext['tagId']);
                    static::assertSame($cart->getCustomerId(), $logContext['customerId']);
                    static::assertSame($cart->getId(), $logContext['cartId']);

                    return true;
                })
            );

        $this->action->execute($cart, ['tagId' => $tagId], $context);
    }

    public function testExecuteWithDifferentCustomerId(): void
    {
        $customerId = Uuid::randomHex();
        $cart = $this->createAbandonedCart($customerId);
        $context = new ActionContext();
        $tagId = Uuid::randomHex();

        $this->customerRepository->expects(static::once())
            ->method('update')
            ->with(
                static::callback(function (array $data) use ($customerId): bool {
                    static::assertSame($customerId, $data[0]['id']);

                    return true;
                }),
                static::anything()
            );

        $this->action->execute($cart, ['tagId' => $tagId], $context);
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
}
