<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1706800000CreateAutomationTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1706800000;
    }

    public function update(Connection $connection): void
    {
        $this->createAutomationTable($connection);
        $this->createAutomationLogTable($connection);
        $this->alterAbandonedCartTable($connection);
    }

    private function createAutomationTable(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `frosh_abandoned_cart_automation` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `active` TINYINT(1) NOT NULL DEFAULT 1,
                `priority` INT NOT NULL DEFAULT 0,
                `conditions` JSON NOT NULL,
                `actions` JSON NOT NULL,
                `sales_channel_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.frosh_abandoned_cart_automation.active` (`active`),
                INDEX `idx.frosh_abandoned_cart_automation.priority` (`priority`),
                CONSTRAINT `fk.frosh_abandoned_cart_automation.sales_channel_id` FOREIGN KEY (`sales_channel_id`)
                    REFERENCES `sales_channel` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function createAutomationLogTable(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `frosh_abandoned_cart_automation_log` (
                `id` BINARY(16) NOT NULL,
                `automation_id` BINARY(16) NOT NULL,
                `abandoned_cart_id` BINARY(16) NOT NULL,
                `customer_id` BINARY(16) NOT NULL,
                `status` VARCHAR(50) NOT NULL,
                `action_results` JSON NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                INDEX `idx.frosh_abandoned_cart_automation_log.automation_id` (`automation_id`),
                INDEX `idx.frosh_abandoned_cart_automation_log.abandoned_cart_id` (`abandoned_cart_id`),
                INDEX `idx.frosh_abandoned_cart_automation_log.customer_id` (`customer_id`),
                INDEX `idx.frosh_abandoned_cart_automation_log.status` (`status`),
                CONSTRAINT `fk.frosh_abandoned_cart_automation_log.automation_id` FOREIGN KEY (`automation_id`)
                    REFERENCES `frosh_abandoned_cart_automation` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.frosh_abandoned_cart_automation_log.abandoned_cart_id` FOREIGN KEY (`abandoned_cart_id`)
                    REFERENCES `frosh_abandoned_cart` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.frosh_abandoned_cart_automation_log.customer_id` FOREIGN KEY (`customer_id`)
                    REFERENCES `customer` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    private function alterAbandonedCartTable(Connection $connection): void
    {
        $columns = $connection->fetchAllKeyValue('SHOW COLUMNS FROM `frosh_abandoned_cart`');

        if (!isset($columns['last_automation_at'])) {
            $connection->executeStatement('
                ALTER TABLE `frosh_abandoned_cart`
                ADD COLUMN `last_automation_at` DATETIME(3) NULL
            ');
        }

        if (!isset($columns['automation_count'])) {
            $connection->executeStatement('
                ALTER TABLE `frosh_abandoned_cart`
                ADD COLUMN `automation_count` INT NOT NULL DEFAULT 0
            ');
        }
    }
}
