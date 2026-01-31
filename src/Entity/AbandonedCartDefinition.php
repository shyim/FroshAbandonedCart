<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity;

use Frosh\AbandonedCart\Entity\AbandonedCartAutomationLog\AbandonedCartAutomationLogDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class AbandonedCartDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'frosh_abandoned_cart';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AbandonedCartEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AbandonedCartCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new FkField('customer_id', 'customerId', CustomerDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new FloatField('total_price', 'totalPrice'))->addFlags(new Required(), new ApiAware()),
            (new StringField('currency_iso_code', 'currencyIsoCode', 3))->addFlags(new Required(), new ApiAware()),
            (new DateTimeField('last_automation_at', 'lastAutomationAt'))->addFlags(new ApiAware()),
            (new IntField('automation_count', 'automationCount'))->addFlags(new ApiAware()),

            (new OneToManyAssociationField('lineItems', AbandonedCartLineItemDefinition::class, 'abandoned_cart_id'))->addFlags(new CascadeDelete(), new ApiAware()),
            (new OneToManyAssociationField('automationLogs', AbandonedCartAutomationLogDefinition::class, 'abandoned_cart_id'))->addFlags(new CascadeDelete(), new ApiAware()),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, 'id', false),
        ]);
    }
}
