<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;

class CartAgeCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'cart_age';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(AbandonedCartEntity $cart, array $config): bool
    {
        $operator = $config['operator'] ?? '>=';
        $value = (int) ($config['value'] ?? 24);
        $unit = $config['unit'] ?? 'hours';

        $createdAt = $cart->getCreatedAt();
        if ($createdAt === null) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $createdAt->getTimestamp();

        $ageInSeconds = match ($unit) {
            'minutes' => $value * 60,
            'hours' => $value * 3600,
            'days' => $value * 86400,
            default => $value * 3600,
        };

        return $this->compare($diff, $ageInSeconds, $operator);
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
