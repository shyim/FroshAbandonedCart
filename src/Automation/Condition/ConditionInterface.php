<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Shopware\Core\Framework\Context;

interface ConditionInterface
{
    public function getType(): string;

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(AbandonedCartEntity $cart, array $config, Context $context): bool;
}
