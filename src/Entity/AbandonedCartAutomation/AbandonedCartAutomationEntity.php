<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Entity\AbandonedCartAutomation;

use Frosh\AbandonedCart\Entity\AbandonedCartAutomationLog\AbandonedCartAutomationLogCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class AbandonedCartAutomationEntity extends Entity
{
    use EntityIdTrait;

    protected string $name;

    protected bool $active = true;

    protected int $priority = 0;

    /**
     * @var array<string, mixed>
     */
    protected array $conditions = [];

    /**
     * @var array<string, mixed>
     */
    protected array $actions = [];

    protected ?string $salesChannelId = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?AbandonedCartAutomationLogCollection $logs = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }

    /**
     * @param array<string, mixed> $conditions
     */
    public function setConditions(array $conditions): void
    {
        $this->conditions = $conditions;
    }

    /**
     * @return array<string, mixed>
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * @param array<string, mixed> $actions
     */
    public function setActions(array $actions): void
    {
        $this->actions = $actions;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getLogs(): ?AbandonedCartAutomationLogCollection
    {
        return $this->logs;
    }

    public function setLogs(?AbandonedCartAutomationLogCollection $logs): void
    {
        $this->logs = $logs;
    }
}
