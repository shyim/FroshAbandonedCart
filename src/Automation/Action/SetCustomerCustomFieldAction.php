<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation\Action;

use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class SetCustomerCustomFieldAction implements ActionInterface
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
        return 'set_customer_custom_field';
    }

    /**
     * @param array<string, mixed> $config
     */
    public function execute(AbandonedCartEntity $cart, array $config, ActionContext $context): void
    {
        $customFieldName = $config['customFieldName'] ?? null;
        $value = $config['value'] ?? null;

        if ($customFieldName === null || $customFieldName === '') {
            $this->logger->warning('SetCustomerCustomFieldAction: No custom field name configured');

            return;
        }

        $customerId = $cart->getCustomerId();
        $dbContext = Context::createDefaultContext();

        try {
            $customer = $this->customerRepository->search(
                new Criteria([$customerId]),
                $dbContext
            )->getEntities()->first();

            if ($customer === null) {
                $this->logger->warning('SetCustomerCustomFieldAction: Customer not found', [
                    'customerId' => $customerId,
                    'cartId' => $cart->getId(),
                ]);

                return;
            }

            $customFields = $customer->getCustomFields() ?? [];
            $customFields[$customFieldName] = $value;

            $this->customerRepository->update([
                [
                    'id' => $customerId,
                    'customFields' => $customFields,
                ],
            ], $dbContext);

            $this->logger->info('SetCustomerCustomFieldAction: Set custom field on customer', [
                'customFieldName' => $customFieldName,
                'value' => $value,
                'customerId' => $customerId,
                'cartId' => $cart->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('SetCustomerCustomFieldAction: Failed to set custom field on customer', [
                'error' => $e->getMessage(),
                'customFieldName' => $customFieldName,
                'customerId' => $customerId,
                'cartId' => $cart->getId(),
            ]);
        }
    }
}
