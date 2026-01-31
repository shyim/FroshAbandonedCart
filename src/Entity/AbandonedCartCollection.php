<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AbandonedCartEntity>
 */
class AbandonedCartCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'frosh_abandoned_cart_collection';
    }

    protected function getExpectedClass(): string
    {
        return AbandonedCartEntity::class;
    }
}
