<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;

class AutomationCountCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'automation_count';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(AbandonedCartEntity $cart, array $config): bool
    {
        $operator = $config['operator'] ?? '==';
        $value = (int) ($config['value'] ?? 0);

        $automationCount = $cart->getAutomationCount();

        return $this->compare($automationCount, $value, $operator);
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
