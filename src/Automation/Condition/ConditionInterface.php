<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;

interface ConditionInterface
{
    public function getType(): string;

    /**
     * Apply SQL conditions to the query builder.
     * The query builder selects from `frosh_abandoned_cart` aliased as `cart`.
     *
     * @param array<string, mixed> $config
     */
    public function apply(QueryBuilder $query, array $config, Context $context): void;
}
