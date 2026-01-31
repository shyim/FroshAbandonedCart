<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity\AbandonedCartAutomation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AbandonedCartAutomationEntity>
 */
class AbandonedCartAutomationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'frosh_abandoned_cart_automation_collection';
    }

    protected function getExpectedClass(): string
    {
        return AbandonedCartAutomationEntity::class;
    }
}
