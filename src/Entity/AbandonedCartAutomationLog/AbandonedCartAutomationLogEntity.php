<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity\AbandonedCartAutomationLog;

use Frosh\AbandonedCart\Entity\AbandonedCartAutomation\AbandonedCartAutomationEntity;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AbandonedCartAutomationLogEntity extends Entity
{
    use EntityIdTrait;

    protected string $automationId;

    protected string $abandonedCartId;

    protected string $customerId;

    protected string $status;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $actionResults = null;

    protected ?AbandonedCartAutomationEntity $automation = null;

    protected ?AbandonedCartEntity $abandonedCart = null;

    protected ?CustomerEntity $customer = null;

    public function getAutomationId(): string
    {
        return $this->automationId;
    }

    public function setAutomationId(string $automationId): void
    {
        $this->automationId = $automationId;
    }

    public function getAbandonedCartId(): string
    {
        return $this->abandonedCartId;
    }

    public function setAbandonedCartId(string $abandonedCartId): void
    {
        $this->abandonedCartId = $abandonedCartId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getActionResults(): ?array
    {
        return $this->actionResults;
    }

    /**
     * @param array<string, mixed>|null $actionResults
     */
    public function setActionResults(?array $actionResults): void
    {
        $this->actionResults = $actionResults;
    }

    public function getAutomation(): ?AbandonedCartAutomationEntity
    {
        return $this->automation;
    }

    public function setAutomation(?AbandonedCartAutomationEntity $automation): void
    {
        $this->automation = $automation;
    }

    public function getAbandonedCart(): ?AbandonedCartEntity
    {
        return $this->abandonedCart;
    }

    public function setAbandonedCart(?AbandonedCartEntity $abandonedCart): void
    {
        $this->abandonedCart = $abandonedCart;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}
