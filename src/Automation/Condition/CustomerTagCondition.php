<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Condition;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class CustomerTagCondition implements ConditionInterface
{
    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository
    ) {
    }

    public function getType(): string
    {
        return 'customer_tag';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function evaluate(AbandonedCartEntity $cart, array $config): bool
    {
        $tagId = $config['tagId'] ?? null;
        $negate = (bool) ($config['negate'] ?? false);

        if ($tagId === null) {
            return false;
        }

        $customerId = $cart->getCustomerId();
        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('tags');

        $customer = $this->customerRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        if ($customer === null) {
            return $negate;
        }

        $tags = $customer->getTags();
        if ($tags === null) {
            return $negate;
        }

        $hasTag = $tags->has($tagId);

        return $negate ? !$hasTag : $hasTag;
    }
}
