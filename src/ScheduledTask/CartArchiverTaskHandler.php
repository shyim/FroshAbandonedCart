<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\ScheduledTask;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CartArchiverTask::class)]
class CartArchiverTaskHandler extends ScheduledTaskHandler
{
    private const BATCH_SIZE = 100;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly Connection $connection,
        private readonly CartCompressor $cartCompressor,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $this->archiveOldCarts();
    }

    private function archiveOldCarts(): void
    {
        $oneDayAgo = (new \DateTimeImmutable())->modify('-1 day')->format('Y-m-d H:i:s');

        do {
            $carts = $this->connection->fetchAllAssociative(
                'SELECT
                    c.token,
                    c.payload,
                    c.compressed,
                    sac.customer_id,
                    sac.sales_channel_id,
                    cur.iso_code AS currency_iso_code
                FROM cart c
                INNER JOIN sales_channel_api_context sac ON c.token = sac.token
                INNER JOIN sales_channel sc ON sac.sales_channel_id = sc.id
                INNER JOIN currency cur ON sc.currency_id = cur.id
                WHERE c.created_at <= :oneDayAgo
                    AND sac.customer_id IS NOT NULL
                LIMIT :limit',
                [
                    'oneDayAgo' => $oneDayAgo,
                    'limit' => self::BATCH_SIZE,
                ],
                [
                    'limit' => ParameterType::INTEGER,
                ]
            );

            if (empty($carts)) {
                break;
            }

            $tokensToDelete = [];

            foreach ($carts as $cartRow) {
                $this->processAndSaveCart($cartRow);
                $tokensToDelete[] = $cartRow['token'];
            }

            if (!empty($tokensToDelete)) {
                $this->deleteOldCarts($tokensToDelete);
            }
        } while (\count($carts) === self::BATCH_SIZE);
    }

    /**
     * @param array{token: string, payload: string, compressed: int, customer_id: string, sales_channel_id: string, currency_iso_code: string} $cartRow
     */
    private function processAndSaveCart(array $cartRow): void
    {
        try {
            $cart = $this->cartCompressor->unserialize($cartRow['payload'], (int) $cartRow['compressed']);

            if (!$cart instanceof Cart) {
                return;
            }

            $lineItems = $this->extractLineItems($cart);

            if (empty($lineItems)) {
                return;
            }

            $existing = $this->connection->fetchAssociative(
                'SELECT id FROM frosh_abandoned_cart
                WHERE customer_id = :customer_id AND sales_channel_id = :sales_channel_id',
                [
                    'customer_id' => $cartRow['customer_id'],
                    'sales_channel_id' => $cartRow['sales_channel_id'],
                ]
            );

            $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            if ($existing !== false) {
                $abandonedCartId = $existing['id'];

                $this->connection->executeStatement(
                    'UPDATE frosh_abandoned_cart
                    SET total_price = total_price + :total_price, updated_at = :updated_at
                    WHERE id = :id',
                    [
                        'id' => $abandonedCartId,
                        'total_price' => $cart->getPrice()->getTotalPrice(),
                        'updated_at' => $now,
                    ]
                );

                $this->mergeLineItems($abandonedCartId, $lineItems, $now);
            } else {
                $abandonedCartId = Uuid::randomBytes();

                $this->connection->executeStatement(
                    'INSERT INTO frosh_abandoned_cart (id, customer_id, sales_channel_id, total_price, currency_iso_code, created_at)
                    VALUES (:id, :customer_id, :sales_channel_id, :total_price, :currency_iso_code, :created_at)',
                    [
                        'id' => $abandonedCartId,
                        'customer_id' => $cartRow['customer_id'],
                        'sales_channel_id' => $cartRow['sales_channel_id'],
                        'total_price' => $cart->getPrice()->getTotalPrice(),
                        'currency_iso_code' => $cartRow['currency_iso_code'],
                        'created_at' => $now,
                    ]
                );

                $this->insertLineItems($abandonedCartId, $lineItems, $now);
            }
        } catch (\Throwable) {
            // Skip invalid carts
        }
    }

    /**
     * @return array<string, array{id: string, type: string, referencedId: string|null, productId: string|null, quantity: int, label: string|null, unitPrice: float, totalPrice: float, stackable: bool}>
     */
    private function extractLineItems(Cart $cart): array
    {
        $lineItems = [];

        foreach ($cart->getLineItems() as $lineItem) {
            $type = $lineItem->getType();

            if (!\in_array($type, [LineItem::PRODUCT_LINE_ITEM_TYPE, LineItem::PROMOTION_LINE_ITEM_TYPE, LineItem::CUSTOM_LINE_ITEM_TYPE], true)) {
                continue;
            }

            $referencedId = $lineItem->getReferencedId();
            $key = $type . '-' . ($referencedId ?? $lineItem->getId());

            $productId = null;
            if ($type === LineItem::PRODUCT_LINE_ITEM_TYPE && $referencedId !== null && Uuid::isValid($referencedId)) {
                $productId = $referencedId;
            }

            $price = $lineItem->getPrice();

            $lineItems[$key] = [
                'id' => $lineItem->getId(),
                'type' => $type,
                'referencedId' => $referencedId,
                'productId' => $productId,
                'quantity' => $lineItem->getQuantity(),
                'label' => $lineItem->getLabel(),
                'unitPrice' => $price?->getUnitPrice() ?? 0,
                'totalPrice' => $price?->getTotalPrice() ?? 0,
                'stackable' => $lineItem->isStackable(),
            ];
        }

        return $lineItems;
    }

    /**
     * @param array<string, array{id: string, type: string, referencedId: string|null, productId: string|null, quantity: int, label: string|null, unitPrice: float, totalPrice: float, stackable: bool}> $lineItems
     */
    private function insertLineItems(string $abandonedCartId, array $lineItems, string $now): void
    {
        foreach ($lineItems as $item) {
            $this->connection->executeStatement(
                'INSERT INTO frosh_abandoned_cart_line_item
                (id, abandoned_cart_id, product_id, product_version_id, type, referenced_id, quantity, label, unit_price, total_price, created_at)
                VALUES (:id, :abandoned_cart_id, :product_id, :product_version_id, :type, :referenced_id, :quantity, :label, :unit_price, :total_price, :created_at)',
                [
                    'id' => Uuid::randomBytes(),
                    'abandoned_cart_id' => $abandonedCartId,
                    'product_id' => $item['productId'] !== null ? Uuid::fromHexToBytes($item['productId']) : null,
                    'product_version_id' => $item['productId'] !== null ? Uuid::fromHexToBytes(Defaults::LIVE_VERSION) : null,
                    'type' => $item['type'],
                    'referenced_id' => $item['referencedId'],
                    'quantity' => $item['quantity'],
                    'label' => $item['label'],
                    'unit_price' => $item['unitPrice'],
                    'total_price' => $item['totalPrice'],
                    'created_at' => $now,
                ]
            );
        }
    }

    /**
     * @param array<string, array{id: string, type: string, referencedId: string|null, productId: string|null, quantity: int, label: string|null, unitPrice: float, totalPrice: float, stackable: bool}> $newLineItems
     */
    private function mergeLineItems(string $abandonedCartId, array $newLineItems, string $now): void
    {
        $existingItems = $this->connection->fetchAllAssociative(
            'SELECT id, type, referenced_id, quantity FROM frosh_abandoned_cart_line_item WHERE abandoned_cart_id = :abandoned_cart_id',
            ['abandoned_cart_id' => $abandonedCartId]
        );

        $existingByKey = [];
        foreach ($existingItems as $item) {
            $key = $item['type'] . '-' . ($item['referenced_id'] ?? Uuid::fromBytesToHex($item['id']));
            $existingByKey[$key] = $item;
        }

        foreach ($newLineItems as $key => $newItem) {
            if (isset($existingByKey[$key]) && $newItem['stackable']) {
                $this->connection->executeStatement(
                    'UPDATE frosh_abandoned_cart_line_item
                    SET quantity = quantity + :quantity, total_price = total_price + :total_price, updated_at = :updated_at
                    WHERE id = :id',
                    [
                        'id' => $existingByKey[$key]['id'],
                        'quantity' => $newItem['quantity'],
                        'total_price' => $newItem['totalPrice'],
                        'updated_at' => $now,
                    ]
                );
            } else {
                $this->connection->executeStatement(
                    'INSERT INTO frosh_abandoned_cart_line_item
                    (id, abandoned_cart_id, product_id, product_version_id, type, referenced_id, quantity, label, unit_price, total_price, created_at)
                    VALUES (:id, :abandoned_cart_id, :product_id, :product_version_id, :type, :referenced_id, :quantity, :label, :unit_price, :total_price, :created_at)',
                    [
                        'id' => Uuid::randomBytes(),
                        'abandoned_cart_id' => $abandonedCartId,
                        'product_id' => $newItem['productId'] !== null ? Uuid::fromHexToBytes($newItem['productId']) : null,
                        'product_version_id' => $newItem['productId'] !== null ? Uuid::fromHexToBytes(Defaults::LIVE_VERSION) : null,
                        'type' => $newItem['type'],
                        'referenced_id' => $newItem['referencedId'],
                        'quantity' => $newItem['quantity'],
                        'label' => $newItem['label'],
                        'unit_price' => $newItem['unitPrice'],
                        'total_price' => $newItem['totalPrice'],
                        'created_at' => $now,
                    ]
                );
            }
        }
    }

    /**
     * @param array<string> $tokens
     */
    private function deleteOldCarts(array $tokens): void
    {
        $this->connection->executeStatement(
            'DELETE FROM cart WHERE token IN (:tokens)',
            ['tokens' => $tokens],
            ['tokens' => ArrayParameterType::STRING]
        );
    }
}
