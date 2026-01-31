<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AbandonedCartLineItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $abandonedCartId;

    protected ?string $productId = null;

    protected ?string $productVersionId = null;

    protected string $type;

    protected ?string $referencedId = null;

    protected int $quantity;

    protected ?string $label = null;

    protected float $unitPrice = 0;

    protected float $totalPrice = 0;

    protected ?AbandonedCartEntity $abandonedCart = null;

    protected ?ProductEntity $product = null;

    public function getAbandonedCartId(): string
    {
        return $this->abandonedCartId;
    }

    public function setAbandonedCartId(string $abandonedCartId): void
    {
        $this->abandonedCartId = $abandonedCartId;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProductVersionId(): ?string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(?string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getReferencedId(): ?string
    {
        return $this->referencedId;
    }

    public function setReferencedId(?string $referencedId): void
    {
        $this->referencedId = $referencedId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

    public function getAbandonedCart(): ?AbandonedCartEntity
    {
        return $this->abandonedCart;
    }

    public function setAbandonedCart(?AbandonedCartEntity $abandonedCart): void
    {
        $this->abandonedCart = $abandonedCart;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }
}
