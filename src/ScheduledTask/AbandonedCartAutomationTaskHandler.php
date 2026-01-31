<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\ScheduledTask;

use Frosh\AbandonedCart\Automation\AutomationProcessor;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: AbandonedCartAutomationTask::class)]
class AbandonedCartAutomationTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly AutomationProcessor $processor,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->processor->process(Context::createCLIContext());
    }
}
