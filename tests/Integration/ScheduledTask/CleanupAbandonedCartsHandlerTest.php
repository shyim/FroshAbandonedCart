<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Integration\ScheduledTask;

use Doctrine\DBAL\Connection;
use Frosh\AbandonedCart\ScheduledTask\CleanupAbandonedCartsHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;

class CleanupAbandonedCartsHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);

        $this->connection->executeStatement('DELETE FROM frosh_abandoned_cart');
    }

    public function testDeletesOldAbandonedCarts(): void
    {
        $this->systemConfigService->set('FroshAbandonedCart.config.retentionDays', 14);

        $oldCartId = $this->createAbandonedCart(20);
        $recentCartId = $this->createAbandonedCart(5);

        $handler = static::getContainer()->get(CleanupAbandonedCartsHandler::class);
        $handler->run();

        $oldCartExists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM frosh_abandoned_cart WHERE id = :id',
            ['id' => $oldCartId]
        );
        static::assertFalse($oldCartExists, 'Old cart should be deleted');

        $recentCartExists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM frosh_abandoned_cart WHERE id = :id',
            ['id' => $recentCartId]
        );
        static::assertTrue($recentCartExists, 'Recent cart should still exist');
    }

    public function testDoesNotDeleteWhenRetentionIsZero(): void
    {
        $this->systemConfigService->set('FroshAbandonedCart.config.retentionDays', 0);

        $oldCartId = $this->createAbandonedCart(100);

        $handler = static::getContainer()->get(CleanupAbandonedCartsHandler::class);
        $handler->run();

        $cartExists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM frosh_abandoned_cart WHERE id = :id',
            ['id' => $oldCartId]
        );
        static::assertTrue($cartExists, 'Cart should not be deleted when retention is 0');
    }

    public function testDeletesCartsExactlyAtThreshold(): void
    {
        $this->systemConfigService->set('FroshAbandonedCart.config.retentionDays', 14);

        $cartAtThreshold = $this->createAbandonedCart(15);
        $cartJustBefore = $this->createAbandonedCart(13);

        $handler = static::getContainer()->get(CleanupAbandonedCartsHandler::class);
        $handler->run();

        $thresholdCartExists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM frosh_abandoned_cart WHERE id = :id',
            ['id' => $cartAtThreshold]
        );
        static::assertFalse($thresholdCartExists, 'Cart at threshold should be deleted');

        $beforeCartExists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM frosh_abandoned_cart WHERE id = :id',
            ['id' => $cartJustBefore]
        );
        static::assertTrue($beforeCartExists, 'Cart just before threshold should still exist');
    }

    public function testDeletesMultipleOldCarts(): void
    {
        $this->systemConfigService->set('FroshAbandonedCart.config.retentionDays', 7);

        $oldCart1 = $this->createAbandonedCart(30);
        $oldCart2 = $this->createAbandonedCart(20);
        $oldCart3 = $this->createAbandonedCart(10);
        $recentCart = $this->createAbandonedCart(3);

        $handler = static::getContainer()->get(CleanupAbandonedCartsHandler::class);
        $handler->run();

        $remainingCount = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM frosh_abandoned_cart');
        static::assertSame(1, $remainingCount, 'Only the recent cart should remain');

        $recentCartExists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM frosh_abandoned_cart WHERE id = :id',
            ['id' => $recentCart]
        );
        static::assertTrue($recentCartExists, 'Recent cart should still exist');
    }

    public function testCascadeDeletesLineItems(): void
    {
        $this->systemConfigService->set('FroshAbandonedCart.config.retentionDays', 14);

        $oldCartId = $this->createAbandonedCart(20);
        $lineItemId = $this->createLineItem($oldCartId);

        $handler = static::getContainer()->get(CleanupAbandonedCartsHandler::class);
        $handler->run();

        $lineItemExists = (bool) $this->connection->fetchOne(
            'SELECT 1 FROM frosh_abandoned_cart_line_item WHERE id = :id',
            ['id' => $lineItemId]
        );
        static::assertFalse($lineItemExists, 'Line items should be cascade deleted');
    }

    private function createAbandonedCart(int $daysOld): string
    {
        $id = Uuid::randomBytes();
        $createdAt = (new \DateTimeImmutable())->modify(\sprintf('-%d days', $daysOld));
        $customer = $this->createCustomer();

        $this->connection->insert('frosh_abandoned_cart', [
            'id' => $id,
            'customer_id' => Uuid::fromHexToBytes($customer->getId()),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'total_price' => 100.0,
            'currency_iso_code' => 'EUR',
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    private function createLineItem(string $abandonedCartId): string
    {
        $id = Uuid::randomBytes();

        $this->connection->insert('frosh_abandoned_cart_line_item', [
            'id' => $id,
            'abandoned_cart_id' => $abandonedCartId,
            'product_id' => null,
            'product_version_id' => null,
            'type' => 'product',
            'referenced_id' => Uuid::randomHex(),
            'quantity' => 1,
            'label' => 'Test Product',
            'unit_price' => 50.0,
            'total_price' => 50.0,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $id;
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
}
