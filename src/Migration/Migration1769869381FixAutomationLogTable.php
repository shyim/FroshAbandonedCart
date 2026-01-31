<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769869381FixAutomationLogTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769869381;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->fetchAllKeyValue('SHOW COLUMNS FROM `frosh_abandoned_cart_automation_log`');

        if (!isset($columns['updated_at'])) {
            $connection->executeStatement('
                ALTER TABLE `frosh_abandoned_cart_automation_log`
                ADD COLUMN `updated_at` DATETIME(3) NULL
            ');
        }
    }
}
