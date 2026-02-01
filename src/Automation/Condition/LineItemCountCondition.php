<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;

class LineItemCountCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'line_item_count';
    }

    public function apply(QueryBuilder $query, array $config, Context $context): void
    {
        $operator = $config['operator'] ?? '>=';
        $value = (int) ($config['value'] ?? 1);

        $sqlOperator = match ($operator) {
            '>=', 'gte' => '>=',
            '<=', 'lte' => '<=',
            '>', 'gt' => '>',
            '<', 'lt' => '<',
            '==', 'eq' => '=',
            '!=', 'neq' => '!=',
            default => '>=',
        };

        $paramName = 'line_item_count_' . uniqid();

        $query->andWhere(
            "(SELECT COUNT(*) FROM frosh_abandoned_cart_line_item WHERE abandoned_cart_id = cart.id) {$sqlOperator} :{$paramName}"
        );
        $query->setParameter($paramName, $value);
    }
}
