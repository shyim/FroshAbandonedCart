<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupAbandonedCartsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'frosh_abandoned_cart.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }
}
