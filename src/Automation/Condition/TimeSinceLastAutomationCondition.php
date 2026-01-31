<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;

class TimeSinceLastAutomationCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'time_since_last_automation';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(AbandonedCartEntity $cart, array $config): bool
    {
        $operator = $config['operator'] ?? '>=';
        $value = (int) ($config['value'] ?? 24);
        $unit = $config['unit'] ?? 'hours';

        $lastAutomationAt = $cart->getLastAutomationAt();

        // If no automation has been run yet, the condition passes
        if ($lastAutomationAt === null) {
            return true;
        }

        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $lastAutomationAt->getTimestamp();

        $timeInSeconds = match ($unit) {
            'minutes' => $value * 60,
            'hours' => $value * 3600,
            'days' => $value * 86400,
            default => $value * 3600,
        };

        return $this->compare($diff, $timeInSeconds, $operator);
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
