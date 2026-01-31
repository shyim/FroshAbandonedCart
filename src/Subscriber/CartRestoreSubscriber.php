<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CartRestoreSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CartService $cartService,
        private readonly LineItemFactoryRegistry $lineItemFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => ['onCustomerLogin', -100],
        ];
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $customer = $event->getCustomer();
        $context = $event->getSalesChannelContext();

        $abandonedCart = $this->connection->fetchAssociative(
            'SELECT id FROM frosh_abandoned_cart
            WHERE customer_id = :customerId AND sales_channel_id = :salesChannelId
            LIMIT 1',
            [
                'customerId' => Uuid::fromHexToBytes($customer->getId()),
                'salesChannelId' => Uuid::fromHexToBytes($context->getSalesChannelId()),
            ]
        );

        if ($abandonedCart === false) {
            return;
        }

        $abandonedCartId = $abandonedCart['id'];
        $cart = $this->cartService->getCart($context->getToken(), $context);

        if ($cart->getLineItems()->count() > 0) {
            $this->deleteAbandonedCart($abandonedCartId);

            return;
        }

        $lineItems = $this->connection->fetchAllAssociative(
            'SELECT type, referenced_id, quantity FROM frosh_abandoned_cart_line_item
            WHERE abandoned_cart_id = :abandonedCartId',
            ['abandonedCartId' => $abandonedCartId]
        );

        if (empty($lineItems)) {
            $this->deleteAbandonedCart($abandonedCartId);

            return;
        }

        $lineItemsToAdd = [];
        foreach ($lineItems as $item) {
            try {
                $lineItemsToAdd[] = $this->createLineItem($item, $context);
            } catch (\Throwable) {
                // Skip invalid line items (e.g., product no longer exists)
            }
        }

        if (!empty($lineItemsToAdd)) {
            try {
                $this->cartService->add($cart, $lineItemsToAdd, $context);
            } catch (\Throwable) {
                // Cart add may fail if products are out of stock, etc.
            }
        }

        $this->deleteAbandonedCart($abandonedCartId);
    }

    /**
     * @param array{type: string, referenced_id: string|null, quantity: int|string} $item
     */
    private function createLineItem(array $item, SalesChannelContext $context): LineItem
    {
        if ($item['type'] === LineItem::PROMOTION_LINE_ITEM_TYPE) {
            return $this->lineItemFactory->create([
                'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                'referencedId' => $item['referenced_id'],
            ], $context);
        }

        return $this->lineItemFactory->create([
            'id' => Uuid::randomHex(),
            'type' => $item['type'],
            'referencedId' => $item['referenced_id'],
            'quantity' => (int) $item['quantity'],
        ], $context);
    }

    private function deleteAbandonedCart(string $id): void
    {
        $this->connection->executeStatement(
            'DELETE FROM frosh_abandoned_cart WHERE id = :id',
            ['id' => $id]
        );
    }
}
