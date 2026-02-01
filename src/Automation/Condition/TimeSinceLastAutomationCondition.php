<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;

class TimeSinceLastAutomationCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'time_since_last_automation';
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

        // Time since last automation >= X means last_automation_at <= threshold OR is NULL
        $sqlOperator = match ($operator) {
            '>=', 'gte' => '<=',
            '<=', 'lte' => '>=',
            '>', 'gt' => '<',
            '<', 'lt' => '>',
            default => '<=',
        };

        $paramName = 'last_automation_' . uniqid();
        $query->andWhere("(cart.last_automation_at IS NULL OR cart.last_automation_at {$sqlOperator} :{$paramName})");
        $query->setParameter($paramName, $threshold->format('Y-m-d H:i:s'));
    }
}
