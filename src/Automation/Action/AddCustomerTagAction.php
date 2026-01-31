<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Action;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class AddCustomerTagAction implements ActionInterface
{
    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getType(): string
    {
        return 'add_customer_tag';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(AbandonedCartEntity $cart, array $config, ActionContext $context): void
    {
        $tagId = $config['tagId'] ?? null;

        if ($tagId === null) {
            $this->logger->warning('AddCustomerTagAction: No tag ID configured');

            return;
        }

        $customerId = $cart->getCustomerId();

        try {
            $this->customerRepository->update([
                [
                    'id' => $customerId,
                    'tags' => [
                        ['id' => $tagId],
                    ],
                ],
            ], $context->getContext());

            $this->logger->info('AddCustomerTagAction: Added tag to customer', [
                'tagId' => $tagId,
                'customerId' => $customerId,
                'cartId' => $cart->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('AddCustomerTagAction: Failed to add tag to customer', [
                'error' => $e->getMessage(),
                'tagId' => $tagId,
                'customerId' => $customerId,
                'cartId' => $cart->getId(),
            ]);
        }
    }
}
