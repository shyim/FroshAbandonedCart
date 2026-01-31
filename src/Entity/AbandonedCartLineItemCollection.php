<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AbandonedCartLineItemEntity>
 */
class AbandonedCartLineItemCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'frosh_abandoned_cart_line_item_collection';
    }

    protected function getExpectedClass(): string
    {
        return AbandonedCartLineItemEntity::class;
    }
}
