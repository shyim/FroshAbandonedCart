<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class AbandonedCartLineItemDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'frosh_abandoned_cart_line_item';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AbandonedCartLineItemEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AbandonedCartLineItemCollection::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return AbandonedCartDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),
            (new FkField('abandoned_cart_id', 'abandonedCartId', AbandonedCartDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new ApiAware()),
            (new StringField('type', 'type'))->addFlags(new Required(), new ApiAware()),
            (new StringField('referenced_id', 'referencedId'))->addFlags(new ApiAware()),
            (new IntField('quantity', 'quantity'))->addFlags(new Required(), new ApiAware()),
            (new StringField('label', 'label'))->addFlags(new ApiAware()),
            (new FloatField('unit_price', 'unitPrice'))->addFlags(new ApiAware()),
            (new FloatField('total_price', 'totalPrice'))->addFlags(new ApiAware()),

            new ManyToOneAssociationField('abandonedCart', 'abandoned_cart_id', AbandonedCartDefinition::class, 'id', false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
        ]);
    }
}
