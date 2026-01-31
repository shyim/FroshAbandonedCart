<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769869379AddLineItemTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769869379;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `frosh_abandoned_cart_line_item` (
                `id` BINARY(16) NOT NULL,
                `abandoned_cart_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NULL,
                `product_version_id` BINARY(16) NULL,
                `type` VARCHAR(50) NOT NULL,
                `referenced_id` VARCHAR(64) NULL,
                `quantity` INT NOT NULL,
                `label` VARCHAR(255) NULL,
                `unit_price` DOUBLE NOT NULL DEFAULT 0,
                `total_price` DOUBLE NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.abandoned_cart_line_item.abandoned_cart_id` (`abandoned_cart_id`),
                INDEX `idx.abandoned_cart_line_item.product_id` (`product_id`, `product_version_id`),
                CONSTRAINT `fk.abandoned_cart_line_item.abandoned_cart_id` FOREIGN KEY (`abandoned_cart_id`)
                    REFERENCES `frosh_abandoned_cart` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.abandoned_cart_line_item.product_id` FOREIGN KEY (`product_id`, `product_version_id`)
                    REFERENCES `product` (`id`, `version_id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }
}
