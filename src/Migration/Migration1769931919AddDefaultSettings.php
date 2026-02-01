<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769931919AddDefaultSettings extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769931919;
    }

    public function update(Connection $connection): void
    {
        $this->insertDefaultConfig($connection, 'FroshAbandonedCart.config.cartAbandonmentThreshold', 120);
        $this->insertDefaultConfig($connection, 'FroshAbandonedCart.config.retentionDays', 14);
    }

    private function insertDefaultConfig(Connection $connection, string $key, int $value): void
    {
        $exists = $connection->fetchOne(
            'SELECT 1 FROM `system_config` WHERE `configuration_key` = :key',
            ['key' => $key]
        );

        if ($exists) {
            return;
        }

        $connection->insert('system_config', [
            'id' => $connection->fetchOne('SELECT UNHEX(REPLACE(UUID(), \'-\', \'\'))'),
            'configuration_key' => $key,
            'configuration_value' => json_encode(['_value' => $value]),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }
}
