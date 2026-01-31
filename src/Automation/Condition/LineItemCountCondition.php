<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;

class LineItemCountCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'line_item_count';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(AbandonedCartEntity $cart, array $config, \Shopware\Core\Framework\Context $context): bool
    {
        $operator = $config['operator'] ?? '>=';
        $value = (int) ($config['value'] ?? 1);

        $lineItems = $cart->getLineItems();
        $count = $lineItems !== null ? $lineItems->count() : 0;

        return $this->compare($count, $value, $operator);
    }

    private function compare(int $actual, int $expected, string $operator): bool
    {
        return match ($operator) {
            '>=', 'gte' => $actual >= $expected,
            '<=', 'lte' => $actual <= $expected,
            '==', 'eq' => $actual === $expected,
            '!=', 'neq' => $actual !== $expected,
            '>', 'gt' => $actual > $expected,
            '<', 'lt' => $actual < $expected,
            default => false,
        };
    }
}
