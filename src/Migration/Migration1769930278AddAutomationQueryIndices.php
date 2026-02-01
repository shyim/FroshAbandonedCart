<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Adds database indices to optimize SQL-based automation condition queries.
 *
 * These indices support the following conditions:
 * - CartAgeCondition: filters by created_at
 * - CartValueCondition: filters by total_price
 * - AutomationCountCondition: filters by automation_count
 * - TimeSinceLastAutomationCondition: filters by last_automation_at
 * - AutomationProcessor: filters by sales_channel_id
 */
class Migration1769930278AddAutomationQueryIndices extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769930278;
    }

    public function update(Connection $connection): void
    {
        $this->addIndexIfNotExists(
            $connection,
            'frosh_abandoned_cart',
            'idx.frosh_abandoned_cart.created_at',
            '(`created_at`)'
        );

        $this->addIndexIfNotExists(
            $connection,
            'frosh_abandoned_cart',
            'idx.frosh_abandoned_cart.total_price',
            '(`total_price`)'
        );

        $this->addIndexIfNotExists(
            $connection,
            'frosh_abandoned_cart',
            'idx.frosh_abandoned_cart.automation_count',
            '(`automation_count`)'
        );

        $this->addIndexIfNotExists(
            $connection,
            'frosh_abandoned_cart',
            'idx.frosh_abandoned_cart.last_automation_at',
            '(`last_automation_at`)'
        );

        $this->addIndexIfNotExists(
            $connection,
            'frosh_abandoned_cart',
            'idx.frosh_abandoned_cart.sales_channel_id',
            '(`sales_channel_id`)'
        );

        // Composite index for common automation query pattern:
        // filtering by sales_channel + created_at (cart age)
        $this->addIndexIfNotExists(
            $connection,
            'frosh_abandoned_cart',
            'idx.frosh_abandoned_cart.automation_filter',
            '(`sales_channel_id`, `created_at`, `automation_count`, `last_automation_at`)'
        );
    }

    private function addIndexIfNotExists(Connection $connection, string $table, string $indexName, string $columns): void
    {
        $indices = $connection->fetchAllAssociative(
            'SHOW INDEX FROM `' . $table . '` WHERE Key_name = :indexName',
            ['indexName' => $indexName]
        );

        if (\count($indices) > 0) {
            return;
        }

        $connection->executeStatement(
            'ALTER TABLE `' . $table . '` ADD INDEX `' . $indexName . '` ' . $columns
        );
    }
}
