<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Action;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;

interface ActionInterface
{
    public function getType(): string;

    /**
     * @param array<string, mixed> $config
     */
    public function execute(AbandonedCartEntity $cart, array $config, ActionContext $context): void;
}
