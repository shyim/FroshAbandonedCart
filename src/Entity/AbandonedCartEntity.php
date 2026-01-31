<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity;

use Frosh\AbandonedCart\Entity\AbandonedCartAutomationLog\AbandonedCartAutomationLogCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class AbandonedCartEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected string $salesChannelId;

    protected float $totalPrice;

    protected string $currencyIsoCode;

    protected ?AbandonedCartLineItemCollection $lineItems = null;

    protected ?\DateTimeInterface $lastAutomationAt = null;

    protected int $automationCount = 0;

    protected ?CustomerEntity $customer = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?AbandonedCartAutomationLogCollection $automationLogs = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

    public function getCurrencyIsoCode(): string
    {
        return $this->currencyIsoCode;
    }

    public function setCurrencyIsoCode(string $currencyIsoCode): void
    {
        $this->currencyIsoCode = $currencyIsoCode;
    }

    public function getLineItems(): ?AbandonedCartLineItemCollection
    {
        return $this->lineItems;
    }

    public function setLineItems(?AbandonedCartLineItemCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getLastAutomationAt(): ?\DateTimeInterface
    {
        return $this->lastAutomationAt;
    }

    public function setLastAutomationAt(?\DateTimeInterface $lastAutomationAt): void
    {
        $this->lastAutomationAt = $lastAutomationAt;
    }

    public function getAutomationCount(): int
    {
        return $this->automationCount;
    }

    public function setAutomationCount(int $automationCount): void
    {
        $this->automationCount = $automationCount;
    }

    public function getAutomationLogs(): ?AbandonedCartAutomationLogCollection
    {
        return $this->automationLogs;
    }

    public function setAutomationLogs(?AbandonedCartAutomationLogCollection $automationLogs): void
    {
        $this->automationLogs = $automationLogs;
    }
}
