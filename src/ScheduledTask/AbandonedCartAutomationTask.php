<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class AbandonedCartAutomationTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'frosh_abandoned_cart.run_automations';
    }

    public static function getDefaultInterval(): int
    {
        return 3600; // Hourly
    }
}
