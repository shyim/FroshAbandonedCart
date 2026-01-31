<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Unit\Automation\Action;

use Frosh\AbandonedCart\Automation\Action\ActionContext;
use Frosh\AbandonedCart\Automation\Action\RemoveCustomerTagAction;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

#[CoversClass(RemoveCustomerTagAction::class)]
class RemoveCustomerTagActionTest extends TestCase
{
    private EntityRepository&MockObject $customerTagRepository;

    private LoggerInterface&MockObject $logger;

    private RemoveCustomerTagAction $action;

    protected function setUp(): void
    {
        $this->customerTagRepository = $this->createMock(EntityRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = new RemoveCustomerTagAction(
            $this->customerTagRepository,
            $this->logger
        );
    }

    public function testGetType(): void
    {
        static::assertSame('remove_customer_tag', $this->action->getType());
    }

    public function testExecuteWithMissingTagId(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('RemoveCustomerTagAction: No tag ID configured');

        $this->customerTagRepository->expects(static::never())
            ->method('delete');

        $this->action->execute($cart, [], $context);
    }

    public function testExecuteWithNullTagId(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();

        $this->logger->expects(static::once())
            ->method('warning')
            ->with('RemoveCustomerTagAction: No tag ID configured');

        $this->customerTagRepository->expects(static::never())
            ->method('delete');

        $this->action->execute($cart, ['tagId' => null], $context);
    }

    public function testExecuteSuccessfully(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();
        $tagId = Uuid::randomHex();

        $this->customerTagRepository->expects(static::once())
            ->method('delete')
            ->with(
                static::callback(function (array $data) use ($cart, $tagId): bool {
                    static::assertCount(1, $data);
                    static::assertSame($cart->getCustomerId(), $data[0]['customerId']);
                    static::assertSame($tagId, $data[0]['tagId']);

                    return true;
                }),
                static::anything()
            );

        $this->logger->expects(static::once())
            ->method('info')
            ->with(
                'RemoveCustomerTagAction: Removed tag from customer',
                static::callback(function (array $logContext) use ($tagId, $cart): bool {
                    static::assertSame($tagId, $logContext['tagId']);
                    static::assertSame($cart->getCustomerId(), $logContext['customerId']);
                    static::assertSame($cart->getId(), $logContext['cartId']);

                    return true;
                })
            );

        $this->action->execute($cart, ['tagId' => $tagId], $context);
    }

    public function testExecuteLogsErrorOnDeleteFailure(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();
        $tagId = Uuid::randomHex();

        $exception = new \Exception('Database connection lost');
        $this->customerTagRepository->expects(static::once())
            ->method('delete')
            ->willThrowException($exception);

        $this->logger->expects(static::once())
            ->method('error')
            ->with(
                'RemoveCustomerTagAction: Failed to remove tag from customer',
                static::callback(function (array $logContext) use ($tagId, $cart): bool {
                    static::assertSame('Database connection lost', $logContext['error']);
                    static::assertSame($tagId, $logContext['tagId']);
                    static::assertSame($cart->getCustomerId(), $logContext['customerId']);
                    static::assertSame($cart->getId(), $logContext['cartId']);

                    return true;
                })
            );

        $this->action->execute($cart, ['tagId' => $tagId], $context);
    }

    public function testExecuteWithTagNotAssignedToCustomer(): void
    {
        $cart = $this->createAbandonedCart();
        $context = new ActionContext();
        $tagId = Uuid::randomHex();

        // Even if tag is not assigned, the delete should still be called
        // The repository will handle the case where the relationship doesn't exist
        $this->customerTagRepository->expects(static::once())
            ->method('delete')
            ->with(
                static::callback(function (array $data) use ($cart, $tagId): bool {
                    static::assertSame($cart->getCustomerId(), $data[0]['customerId']);
                    static::assertSame($tagId, $data[0]['tagId']);

                    return true;
                }),
                static::anything()
            );

        $this->logger->expects(static::once())
            ->method('info');

        $this->action->execute($cart, ['tagId' => $tagId], $context);
    }

    public function testExecuteWithDifferentCustomerId(): void
    {
        $customerId = Uuid::randomHex();
        $cart = $this->createAbandonedCart($customerId);
        $context = new ActionContext();
        $tagId = Uuid::randomHex();

        $this->customerTagRepository->expects(static::once())
            ->method('delete')
            ->with(
                static::callback(function (array $data) use ($customerId): bool {
                    static::assertSame($customerId, $data[0]['customerId']);

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
