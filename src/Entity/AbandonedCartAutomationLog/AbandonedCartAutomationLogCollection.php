<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity\AbandonedCartAutomationLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AbandonedCartAutomationLogEntity>
 */
class AbandonedCartAutomationLogCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'frosh_abandoned_cart_automation_log_collection';
    }

    protected function getExpectedClass(): string
    {
        return AbandonedCartAutomationLogEntity::class;
    }
}
