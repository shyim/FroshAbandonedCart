<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Integration;

use Doctrine\DBAL\Connection;
use Frosh\AbandonedCart\Controller\Api\AbandonedCartStatisticsController;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

class StatisticsControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private AbandonedCartStatisticsController $controller;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->controller = static::getContainer()->get(AbandonedCartStatisticsController::class);
        $this->ids = new IdsCollection();
    }

    public function testGetStatisticsWithEmptyDatabase(): void
    {
        $request = new Request();
        $response = $this->controller->getStatistics($request, Context::createDefaultContext());

        $data = json_decode($response->getContent(), true);

        static::assertIsArray($data);
        static::assertArrayHasKey('totalCount', $data);
        static::assertArrayHasKey('totalValue', $data);
        static::assertArrayHasKey('todayCount', $data);
        static::assertArrayHasKey('todayValue', $data);
        static::assertArrayHasKey('dailyStats', $data);
        static::assertArrayHasKey('topProducts', $data);

        static::assertSame(0, $data['totalCount']);
        static::assertSame(0, $data['todayCount']);
        static::assertSame([], $data['dailyStats']);
        static::assertSame([], $data['topProducts']);
    }

    public function testGetStatisticsWithData(): void
    {
        $customerId = $this->createCustomer('c1');
        $productId = $this->createProduct('p1');

        $cartId = $this->createAbandonedCart($customerId, 99.99);
        $this->createLineItem($cartId, $productId, 'Test Product', 2, 49.99, 99.98);

        $request = new Request();
        $response = $this->controller->getStatistics($request, Context::createDefaultContext());

        $data = json_decode($response->getContent(), true);

        static::assertIsArray($data);
        static::assertSame(1, $data['totalCount']);
        static::assertSame(99.99, $data['totalValue']);
        static::assertSame(1, $data['todayCount']);
        static::assertSame(99.99, $data['todayValue']);

        static::assertCount(1, $data['dailyStats']);
        static::assertSame(1, $data['dailyStats'][0]['count']);

        static::assertCount(1, $data['topProducts']);
        static::assertSame($productId, $data['topProducts'][0]['productId']);
        static::assertSame(1, $data['topProducts'][0]['count']);
        static::assertSame(2, $data['topProducts'][0]['totalQuantity']);
        static::assertSame(99.98, $data['topProducts'][0]['totalValue']);
    }

    public function testGetStatisticsWithMultipleCartsAndProducts(): void
    {
        $customerId1 = $this->createCustomer('c1');
        $customerId2 = $this->createCustomer('c2');
        $productId1 = $this->createProduct('p1');
        $productId2 = $this->createProduct('p2');

        $cartId1 = $this->createAbandonedCart($customerId1, 150.00);
        $this->createLineItem($cartId1, $productId1, 'Product A', 2, 50.00, 100.00);
        $this->createLineItem($cartId1, $productId2, 'Product B', 1, 50.00, 50.00);

        $cartId2 = $this->createAbandonedCart($customerId2, 100.00);
        $this->createLineItem($cartId2, $productId1, 'Product A', 1, 50.00, 50.00);

        $request = new Request();
        $response = $this->controller->getStatistics($request, Context::createDefaultContext());

        $data = json_decode($response->getContent(), true);

        static::assertSame(2, $data['totalCount']);
        static::assertEqualsWithDelta(250.00, $data['totalValue'], 0.01);

        static::assertCount(2, $data['topProducts']);

        $topProduct = $data['topProducts'][0];
        static::assertSame($productId1, $topProduct['productId']);
        static::assertSame(2, $topProduct['count']);
        static::assertSame(3, $topProduct['totalQuantity']);
        static::assertEqualsWithDelta(150.00, $topProduct['totalValue'], 0.01);
    }

    public function testGetStatisticsWithDeletedProduct(): void
    {
        $customerId = $this->createCustomer('c1');
        $productId = $this->createProduct('p1');

        $cartId = $this->createAbandonedCart($customerId, 50.00);
        $this->createLineItem($cartId, $productId, 'Deleted Product', 1, 50.00, 50.00);

        static::getContainer()->get('product.repository')->delete([['id' => $productId]], Context::createDefaultContext());

        $request = new Request();
        $response = $this->controller->getStatistics($request, Context::createDefaultContext());

        $data = json_decode($response->getContent(), true);

        static::assertCount(1, $data['topProducts']);
        static::assertNull($data['topProducts'][0]['productId']);
        static::assertSame('Deleted Product', $data['topProducts'][0]['label']);
        static::assertNull($data['topProducts'][0]['productNumber']);
    }

    public function testGetStatisticsWithLineItemWithoutProduct(): void
    {
        $customerId = $this->createCustomer('c1');

        $cartId = $this->createAbandonedCart($customerId, 25.00);
        $this->createLineItem($cartId, null, 'Custom Line Item', 1, 25.00, 25.00);

        $request = new Request();
        $response = $this->controller->getStatistics($request, Context::createDefaultContext());

        $data = json_decode($response->getContent(), true);

        static::assertCount(1, $data['topProducts']);
        static::assertNull($data['topProducts'][0]['productId']);
        static::assertSame('Custom Line Item', $data['topProducts'][0]['label']);
        static::assertNull($data['topProducts'][0]['productNumber']);
    }

    public function testGetStatisticsWithCustomLimit(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $customerId = $this->createCustomer("c$i");
            $productId = $this->createProduct("p$i");
            $cartId = $this->createAbandonedCart($customerId, 10.00 * $i);
            $this->createLineItem($cartId, $productId, "Product $i", 1, 10.00, 10.00);
        }

        $request = new Request(['topProductsLimit' => 3]);
        $response = $this->controller->getStatistics($request, Context::createDefaultContext());

        $data = json_decode($response->getContent(), true);

        static::assertCount(3, $data['topProducts']);
    }

    public function testGetStatisticsWithTimezone(): void
    {
        $customerId = $this->createCustomer('c1');
        $this->createAbandonedCart($customerId, 100.00);

        $request = new Request(['timezone' => 'Europe/Berlin']);
        $response = $this->controller->getStatistics($request, Context::createDefaultContext());

        $data = json_decode($response->getContent(), true);

        static::assertIsArray($data);
        static::assertSame(1, $data['totalCount']);
    }

    private function createCustomer(string $key): string
    {
        $customerId = $this->ids->get($key);
        $addressId = $this->ids->get($key . '-address');

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
            'email' => $customerId . '@example.com',
            'password' => 'password',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => $customerId,
        ];

        static::getContainer()->get('customer.repository')->create([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function createProduct(string $key): string
    {
        $product = (new ProductBuilder($this->ids, $key))
            ->price(50)
            ->visibility()
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        return $this->ids->get($key);
    }

    private function createAbandonedCart(string $customerId, float $totalPrice): string
    {
        $cartId = Uuid::randomHex();

        $this->connection->insert('frosh_abandoned_cart', [
            'id' => Uuid::fromHexToBytes($cartId),
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'total_price' => $totalPrice,
            'currency_iso_code' => 'EUR',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $cartId;
    }

    private function createLineItem(
        string $cartId,
        ?string $productId,
        string $label,
        int $quantity,
        float $unitPrice,
        float $totalPrice
    ): void {
        $this->connection->insert('frosh_abandoned_cart_line_item', [
            'id' => Uuid::randomBytes(),
            'abandoned_cart_id' => Uuid::fromHexToBytes($cartId),
            'product_id' => $productId !== null ? Uuid::fromHexToBytes($productId) : null,
            'product_version_id' => $productId !== null ? Uuid::fromHexToBytes(Defaults::LIVE_VERSION) : null,
            'type' => 'product',
            'referenced_id' => $productId,
            'quantity' => $quantity,
            'label' => $label,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
