<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;

class CartValueCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'cart_value';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(AbandonedCartEntity $cart, array $config, \Shopware\Core\Framework\Context $context): bool
    {
        $operator = $config['operator'] ?? '>=';
        $value = (float) ($config['value'] ?? 0);

        $totalPrice = $cart->getTotalPrice();

        return $this->compare($totalPrice, $value, $operator);
    }

    private function compare(float $actual, float $expected, string $operator): bool
    {
        return match ($operator) {
            '>=', 'gte' => $actual >= $expected,
            '<=', 'lte' => $actual <= $expected,
            '==', 'eq' => abs($actual - $expected) < 0.001,
            '!=', 'neq' => abs($actual - $expected) >= 0.001,
            '>', 'gt' => $actual > $expected,
            '<', 'lt' => $actual < $expected,
            default => false,
        };
    }
}
