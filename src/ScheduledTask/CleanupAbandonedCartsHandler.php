<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\ScheduledTask;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CleanupAbandonedCartsTask::class)]
final class CleanupAbandonedCartsHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        protected EntityRepository $scheduledTaskRepository,
        protected readonly LoggerInterface $logger,
        private readonly Connection $connection,
        private readonly SystemConfigService $systemConfigService,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $retentionDays = $this->systemConfigService->getInt('FroshAbandonedCart.config.retentionDays');

        if ($retentionDays <= 0) {
            return;
        }

        $threshold = (new \DateTimeImmutable())->modify(\sprintf('-%d days', $retentionDays));

        $this->connection->executeStatement(
            'DELETE FROM `frosh_abandoned_cart` WHERE `created_at` < :threshold',
            ['threshold' => $threshold->format('Y-m-d H:i:s')]
        );
    }
}
