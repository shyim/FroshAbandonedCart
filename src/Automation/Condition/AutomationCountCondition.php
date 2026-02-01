<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;

class AutomationCountCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'automation_count';
    }

    public function apply(QueryBuilder $query, array $config, Context $context): void
    {
        $operator = $config['operator'] ?? '==';
        $value = (int) ($config['value'] ?? 0);

        $sqlOperator = match ($operator) {
            '>=', 'gte' => '>=',
            '<=', 'lte' => '<=',
            '>', 'gt' => '>',
            '<', 'lt' => '<',
            '==', 'eq' => '=',
            '!=', 'neq' => '!=',
            default => '=',
        };

        $paramName = 'automation_count_' . uniqid();
        $query->andWhere("COALESCE(cart.automation_count, 0) {$sqlOperator} :{$paramName}");
        $query->setParameter($paramName, $value);
    }
}
