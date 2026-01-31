<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity\AbandonedCartAutomationLog;

use Frosh\AbandonedCart\Entity\AbandonedCartAutomation\AbandonedCartAutomationDefinition;
use Frosh\AbandonedCart\Entity\AbandonedCartDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AbandonedCartAutomationLogDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'frosh_abandoned_cart_automation_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AbandonedCartAutomationLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AbandonedCartAutomationLogCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new FkField('automation_id', 'automationId', AbandonedCartAutomationDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new FkField('abandoned_cart_id', 'abandonedCartId', AbandonedCartDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new StringField('status', 'status'))->addFlags(new Required(), new ApiAware()),
            (new JsonField('action_results', 'actionResults'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('automation', 'automation_id', AbandonedCartAutomationDefinition::class, 'id', false),
            new ManyToOneAssociationField('abandonedCart', 'abandoned_cart_id', AbandonedCartDefinition::class, 'id', false),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
        ]);
    }
}
