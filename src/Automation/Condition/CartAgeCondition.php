<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;

class CartAgeCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'cart_age';
    }

    public function apply(QueryBuilder $query, array $config, Context $context): void
    {
        $operator = $config['operator'] ?? '>=';
        $value = (int) ($config['value'] ?? 24);
        $unit = $config['unit'] ?? 'hours';

        $seconds = match ($unit) {
            'minutes' => $value * 60,
            'hours' => $value * 3600,
            'days' => $value * 86400,
            default => $value * 3600,
        };

        $threshold = (new \DateTimeImmutable())->modify("-{$seconds} seconds");

        // Cart age >= X means created_at <= threshold (older than X)
        $sqlOperator = match ($operator) {
            '>=', 'gte' => '<=',
            '<=', 'lte' => '>=',
            '>', 'gt' => '<',
            '<', 'lt' => '>',
            default => '<=',
        };

        $paramName = 'cart_age_' . uniqid();
        $query->andWhere("cart.created_at {$sqlOperator} :{$paramName}");
        $query->setParameter($paramName, $threshold->format('Y-m-d H:i:s'));
    }
}
