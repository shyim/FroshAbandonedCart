<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;

class CartValueCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'cart_value';
    }

    public function apply(QueryBuilder $query, array $config, Context $context): void
    {
        $operator = $config['operator'] ?? '>=';
        $value = (float) ($config['value'] ?? 0);

        $sqlOperator = match ($operator) {
            '>=', 'gte' => '>=',
            '<=', 'lte' => '<=',
            '>', 'gt' => '>',
            '<', 'lt' => '<',
            '==', 'eq' => '=',
            '!=', 'neq' => '!=',
            default => '>=',
        };

        $paramName = 'cart_value_' . uniqid();
        $query->andWhere("cart.total_price {$sqlOperator} :{$paramName}");
        $query->setParameter($paramName, $value);
    }
}
