<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class CustomerTagCondition implements ConditionInterface
{
    public function getType(): string
    {
        return 'customer_tag';
    }

    public function apply(QueryBuilder $query, array $config, Context $context): void
    {
        $tagId = $config['tagId'] ?? null;
        $negate = (bool) ($config['negate'] ?? false);

        if ($tagId === null) {
            return;
        }

        $paramName = 'customer_tag_' . uniqid();

        $subQuery = 'SELECT 1 FROM customer_tag WHERE customer_tag.customer_id = cart.customer_id AND customer_tag.tag_id = :' . $paramName;

        if ($negate) {
            $query->andWhere("NOT EXISTS ({$subQuery})");
        } else {
            $query->andWhere("EXISTS ({$subQuery})");
        }

        $query->setParameter($paramName, Uuid::fromHexToBytes($tagId));
    }
}
