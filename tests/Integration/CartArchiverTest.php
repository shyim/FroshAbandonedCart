<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Integration;

use Doctrine\DBAL\Connection;
use Frosh\AbandonedCart\ScheduledTask\CartArchiverTaskHandler;
use Frosh\AbandonedCart\Subscriber\CartRestoreSubscriber;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

class CartArchiverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private CartPersister $cartPersister;

    private SalesChannelContextPersister $contextPersister;

    private CartService $cartService;

    private string $customerId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->cartPersister = static::getContainer()->get(CartPersister::class);
        $this->contextPersister = static::getContainer()->get(SalesChannelContextPersister::class);
        $this->cartService = static::getContainer()->get(CartService::class);

        $this->customerId = $this->createCustomer()->getId();
    }

    public function testArchiveOldCartWithProducts(): void
    {
        $context = $this->createSalesChannelContext(Uuid::randomHex());

        $this->contextPersister->save(
            $context->getToken(),
            [],
            $context->getSalesChannelId(),
            $this->customerId
        );

        $productId = $this->createProduct($context->getContext());
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 3);
        $lineItem->setStackable(true);

        $cart = new Cart($context->getToken());
        $cart->addLineItems(new LineItemCollection([$lineItem]));
        $cart->markUnmodified();

        $this->cartPersister->save($cart, $context);

        $this->makeCartOld($context->getToken());

        $handler = static::getContainer()->get(CartArchiverTaskHandler::class);
        $handler->run();

        $abandonedCart = $this->connection->fetchAssociative(
            'SELECT * FROM frosh_abandoned_cart WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($this->customerId)]
        );

        static::assertNotFalse($abandonedCart, 'Abandoned cart should exist');
        static::assertSame(Uuid::fromHexToBytes($this->customerId), $abandonedCart['customer_id']);

        $lineItems = $this->connection->fetchAllAssociative(
            'SELECT * FROM frosh_abandoned_cart_line_item WHERE abandoned_cart_id = :abandonedCartId',
            ['abandonedCartId' => $abandonedCart['id']]
        );

        static::assertCount(1, $lineItems);
        static::assertSame($productId, Uuid::fromBytesToHex($lineItems[0]['product_id']));
        static::assertSame(3, (int) $lineItems[0]['quantity']);
        static::assertSame(LineItem::PRODUCT_LINE_ITEM_TYPE, $lineItems[0]['type']);

        $cartExists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM cart WHERE token = :token',
            ['token' => $context->getToken()]
        );
        static::assertFalse($cartExists, 'Original cart should be deleted');
    }

    public function testDoNotArchiveRecentCart(): void
    {
        $context = $this->createSalesChannelContext(Uuid::randomHex());

        $this->contextPersister->save(
            $context->getToken(),
            [],
            $context->getSalesChannelId(),
            $this->customerId
        );

        $productId = $this->createProduct($context->getContext());
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 1);
        $lineItem->setStackable(true);

        $cart = new Cart($context->getToken());
        $cart->addLineItems(new LineItemCollection([$lineItem]));
        $cart->markUnmodified();

        $this->cartPersister->save($cart, $context);

        $handler = static::getContainer()->get(CartArchiverTaskHandler::class);
        $handler->run();

        $abandonedCart = $this->connection->fetchAssociative(
            'SELECT * FROM frosh_abandoned_cart WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($this->customerId)]
        );

        static::assertFalse($abandonedCart, 'Recent cart should not be abandoned');

        $cartExists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM cart WHERE token = :token',
            ['token' => $context->getToken()]
        );
        static::assertTrue($cartExists, 'Recent cart should still exist');
    }

    public function testDoNotArchiveGuestCart(): void
    {
        $context = $this->createSalesChannelContext(Uuid::randomHex());

        $this->contextPersister->save(
            $context->getToken(),
            [],
            $context->getSalesChannelId(),
            null
        );

        $productId = $this->createProduct($context->getContext());
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 1);
        $lineItem->setStackable(true);

        $cart = new Cart($context->getToken());
        $cart->addLineItems(new LineItemCollection([$lineItem]));
        $cart->markUnmodified();

        $this->cartPersister->save($cart, $context);

        $this->makeCartOld($context->getToken());

        $handler = static::getContainer()->get(CartArchiverTaskHandler::class);
        $handler->run();

        $abandonedCartCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM frosh_abandoned_cart'
        );

        static::assertSame(0, $abandonedCartCount, 'Guest cart should not be abandoned');
    }

    public function testRestoreArchivedCartOnLogin(): void
    {
        $productId = $this->createProduct(Context::createDefaultContext());
        $abandonedCartId = Uuid::randomBytes();

        $this->connection->executeStatement(
            'INSERT INTO frosh_abandoned_cart (id, customer_id, sales_channel_id, total_price, currency_iso_code, created_at)
            VALUES (:id, :customerId, :salesChannelId, :totalPrice, :currencyIsoCode, :createdAt)',
            [
                'id' => $abandonedCartId,
                'customerId' => Uuid::fromHexToBytes($this->customerId),
                'salesChannelId' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                'totalPrice' => 100.0,
                'currencyIsoCode' => 'EUR',
                'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]
        );

        $this->connection->executeStatement(
            'INSERT INTO frosh_abandoned_cart_line_item (id, abandoned_cart_id, product_id, product_version_id, type, referenced_id, quantity, label, unit_price, total_price, created_at)
            VALUES (:id, :abandonedCartId, :productId, :productVersionId, :type, :referencedId, :quantity, :label, :unitPrice, :totalPrice, :createdAt)',
            [
                'id' => Uuid::randomBytes(),
                'abandonedCartId' => $abandonedCartId,
                'productId' => Uuid::fromHexToBytes($productId),
                'productVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'referencedId' => $productId,
                'quantity' => 2,
                'label' => 'Test Product',
                'unitPrice' => 50.0,
                'totalPrice' => 100.0,
                'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]
        );

        $context = $this->createSalesChannelContext(Uuid::randomHex());

        $this->contextPersister->save(
            $context->getToken(),
            [],
            $context->getSalesChannelId(),
            $this->customerId
        );

        $customer = static::getContainer()->get('customer.repository')
            ->search(new Criteria([$this->customerId]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);

        $event = new CustomerLoginEvent($context, $customer, $context->getToken());

        $subscriber = static::getContainer()->get(CartRestoreSubscriber::class);
        $subscriber->onCustomerLogin($event);

        $cart = $this->cartService->getCart($context->getToken(), $context);

        static::assertCount(1, $cart->getLineItems());

        $restoredLineItem = $cart->getLineItems()->first();
        static::assertNotNull($restoredLineItem);
        static::assertSame($productId, $restoredLineItem->getReferencedId());
        static::assertSame(2, $restoredLineItem->getQuantity());

        $abandonedCartCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM frosh_abandoned_cart WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($this->customerId)]
        );

        static::assertSame(0, $abandonedCartCount, 'Abandoned cart should be deleted after restore');
    }

    public function testDoNotRestoreWhenCartHasItems(): void
    {
        $productId1 = $this->createProduct(Context::createDefaultContext());
        $productId2 = $this->createProduct(Context::createDefaultContext());
        $abandonedCartId = Uuid::randomBytes();

        $this->connection->executeStatement(
            'INSERT INTO frosh_abandoned_cart (id, customer_id, sales_channel_id, total_price, currency_iso_code, created_at)
            VALUES (:id, :customerId, :salesChannelId, :totalPrice, :currencyIsoCode, :createdAt)',
            [
                'id' => $abandonedCartId,
                'customerId' => Uuid::fromHexToBytes($this->customerId),
                'salesChannelId' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
                'totalPrice' => 100.0,
                'currencyIsoCode' => 'EUR',
                'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]
        );

        $this->connection->executeStatement(
            'INSERT INTO frosh_abandoned_cart_line_item (id, abandoned_cart_id, product_id, product_version_id, type, referenced_id, quantity, label, unit_price, total_price, created_at)
            VALUES (:id, :abandonedCartId, :productId, :productVersionId, :type, :referencedId, :quantity, :label, :unitPrice, :totalPrice, :createdAt)',
            [
                'id' => Uuid::randomBytes(),
                'abandonedCartId' => $abandonedCartId,
                'productId' => Uuid::fromHexToBytes($productId1),
                'productVersionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'referencedId' => $productId1,
                'quantity' => 2,
                'label' => 'Test Product 1',
                'unitPrice' => 50.0,
                'totalPrice' => 100.0,
                'createdAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.v'),
            ]
        );

        $context = $this->createSalesChannelContext(Uuid::randomHex());

        $this->contextPersister->save(
            $context->getToken(),
            [],
            $context->getSalesChannelId(),
            $this->customerId
        );

        $existingLineItem = new LineItem($productId2, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId2, 1);
        $existingLineItem->setStackable(true);

        $cart = new Cart($context->getToken());
        $cart->addLineItems(new LineItemCollection([$existingLineItem]));
        $cart->markUnmodified();

        $this->cartPersister->save($cart, $context);

        $customer = static::getContainer()->get('customer.repository')
            ->search(new Criteria([$this->customerId]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);

        $event = new CustomerLoginEvent($context, $customer, $context->getToken());

        $subscriber = static::getContainer()->get(CartRestoreSubscriber::class);
        $subscriber->onCustomerLogin($event);

        $cart = $this->cartService->getCart($context->getToken(), $context);

        static::assertCount(1, $cart->getLineItems());
        static::assertSame($productId2, $cart->getLineItems()->first()?->getReferencedId());

        $abandonedCartCount = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM frosh_abandoned_cart WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($this->customerId)]
        );

        static::assertSame(0, $abandonedCartCount, 'Abandoned cart should still be deleted even when not restored');
    }

    public function testMergeArchivedCartsOnMultipleArchives(): void
    {
        $productId1 = $this->createProduct(Context::createDefaultContext());
        $productId2 = $this->createProduct(Context::createDefaultContext());

        $context1 = $this->createSalesChannelContext(Uuid::randomHex());
        $this->contextPersister->save($context1->getToken(), [], $context1->getSalesChannelId(), $this->customerId);

        $lineItem1 = new LineItem($productId1, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId1, 2);
        $lineItem1->setStackable(true);

        $cart1 = new Cart($context1->getToken());
        $cart1->addLineItems(new LineItemCollection([$lineItem1]));
        $cart1->markUnmodified();
        $this->cartPersister->save($cart1, $context1);
        $this->makeCartOld($context1->getToken());

        $handler = static::getContainer()->get(CartArchiverTaskHandler::class);
        $handler->run();

        $context2 = $this->createSalesChannelContext(Uuid::randomHex());
        $this->contextPersister->save($context2->getToken(), [], $context2->getSalesChannelId(), $this->customerId);

        $lineItem2 = new LineItem($productId1, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId1, 3);
        $lineItem2->setStackable(true);
        $lineItem3 = new LineItem($productId2, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId2, 1);
        $lineItem3->setStackable(true);

        $cart2 = new Cart($context2->getToken());
        $cart2->addLineItems(new LineItemCollection([$lineItem2, $lineItem3]));
        $cart2->markUnmodified();
        $this->cartPersister->save($cart2, $context2);
        $this->makeCartOld($context2->getToken());

        $handler->run();

        $abandonedCart = $this->connection->fetchAssociative(
            'SELECT * FROM frosh_abandoned_cart WHERE customer_id = :customerId',
            ['customerId' => Uuid::fromHexToBytes($this->customerId)]
        );

        static::assertNotFalse($abandonedCart);

        $lineItems = $this->connection->fetchAllAssociative(
            'SELECT * FROM frosh_abandoned_cart_line_item WHERE abandoned_cart_id = :abandonedCartId',
            ['abandonedCartId' => $abandonedCart['id']]
        );

        static::assertCount(2, $lineItems);

        $product1Item = null;
        $product2Item = null;
        foreach ($lineItems as $item) {
            $itemProductId = Uuid::fromBytesToHex($item['product_id']);
            if ($itemProductId === $productId1) {
                $product1Item = $item;
            } elseif ($itemProductId === $productId2) {
                $product2Item = $item;
            }
        }

        static::assertNotNull($product1Item);
        static::assertSame(5, (int) $product1Item['quantity'], 'Quantities should be merged (2 + 3 = 5)');

        static::assertNotNull($product2Item);
        static::assertSame(1, (int) $product2Item['quantity']);
    }

    public function testFullArchiveAndRestoreFlow(): void
    {
        $context = $this->createSalesChannelContext(Uuid::randomHex());

        $this->contextPersister->save(
            $context->getToken(),
            [],
            $context->getSalesChannelId(),
            $this->customerId
        );

        $productId = $this->createProduct($context->getContext());
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 5);
        $lineItem->setStackable(true);

        $cart = new Cart($context->getToken());
        $cart->addLineItems(new LineItemCollection([$lineItem]));
        $cart->markUnmodified();

        $this->cartPersister->save($cart, $context);

        $this->makeCartOld($context->getToken());

        $handler = static::getContainer()->get(CartArchiverTaskHandler::class);
        $handler->run();

        $newContext = $this->createSalesChannelContext(Uuid::randomHex());

        $this->contextPersister->save(
            $newContext->getToken(),
            [],
            $newContext->getSalesChannelId(),
            $this->customerId
        );

        $customer = static::getContainer()->get('customer.repository')
            ->search(new Criteria([$this->customerId]), Context::createDefaultContext())
            ->first();

        static::assertInstanceOf(CustomerEntity::class, $customer);

        $event = new CustomerLoginEvent($newContext, $customer, $newContext->getToken());

        $subscriber = static::getContainer()->get(CartRestoreSubscriber::class);
        $subscriber->onCustomerLogin($event);

        $restoredCart = $this->cartService->getCart($newContext->getToken(), $newContext);

        static::assertCount(1, $restoredCart->getLineItems());

        $restoredLineItem = $restoredCart->getLineItems()->first();
        static::assertNotNull($restoredLineItem);
        static::assertSame($productId, $restoredLineItem->getReferencedId());
        static::assertSame(5, $restoredLineItem->getQuantity());
    }

    private function createSalesChannelContext(string $contextToken): SalesChannelContext
    {
        return static::getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::SALES_CHANNEL
        );
    }

    private function createProduct(Context $context): string
    {
        $productId = Uuid::randomHex();

        $data = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 100,
            'name' => 'Test Product',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 9.24, 'linked' => false]],
            'manufacturer' => ['name' => 'Test Manufacturer'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('product.repository')->create([$data], $context);

        return $productId;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'password',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        $repo = static::getContainer()->get('customer.repository');
        $repo->create([$customer], Context::createDefaultContext());

        $entity = $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
        static::assertInstanceOf(CustomerEntity::class, $entity);

        return $entity;
    }

    private function makeCartOld(string $token): void
    {
        $this->connection->executeStatement(
            'UPDATE cart SET created_at = :createdAt WHERE token = :token',
            [
                'token' => $token,
                'createdAt' => (new \DateTimeImmutable())->modify('-2 days')->format('Y-m-d H:i:s'),
            ]
        );
    }
}
