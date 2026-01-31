<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Action;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

class RemoveCustomerTagAction implements ActionInterface
{
    /**
     * @param EntityRepository<EntityCollection<Entity>> $customerTagRepository
     */
    public function __construct(
        private readonly EntityRepository $customerTagRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getType(): string
    {
        return 'remove_customer_tag';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(AbandonedCartEntity $cart, array $config, ActionContext $context): void
    {
        $tagId = $config['tagId'] ?? null;

        if ($tagId === null) {
            $this->logger->warning('RemoveCustomerTagAction: No tag ID configured');

            return;
        }

        $customerId = $cart->getCustomerId();

        try {
            $this->customerTagRepository->delete([
                [
                    'customerId' => $customerId,
                    'tagId' => $tagId,
                ],
            ], $context->getContext());

            $this->logger->info('RemoveCustomerTagAction: Removed tag from customer', [
                'tagId' => $tagId,
                'customerId' => $customerId,
                'cartId' => $cart->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('RemoveCustomerTagAction: Failed to remove tag from customer', [
                'error' => $e->getMessage(),
                'tagId' => $tagId,
                'customerId' => $customerId,
                'cartId' => $cart->getId(),
            ]);
        }
    }
}
