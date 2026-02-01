<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation;

use Doctrine\DBAL\Connection;
use Frosh\AbandonedCart\Automation\Action\ActionContext;
use Frosh\AbandonedCart\Automation\Action\ActionInterface;
use Frosh\AbandonedCart\Automation\Condition\ConditionInterface;
use Frosh\AbandonedCart\Entity\AbandonedCartAutomation\AbandonedCartAutomationCollection;
use Frosh\AbandonedCart\Entity\AbandonedCartAutomation\AbandonedCartAutomationEntity;
use Frosh\AbandonedCart\Entity\AbandonedCartCollection;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class AutomationProcessor
{
    private const BATCH_SIZE = 500;

    /**
     * @var array<string, ConditionInterface>
     */
    private array $conditionHandlers = [];

    /**
     * @var array<string, ActionInterface>
     */
    private array $actionHandlers = [];

    /**
     * @param EntityRepository<AbandonedCartAutomationCollection> $automationRepository
     * @param EntityRepository<AbandonedCartCollection> $abandonedCartRepository
     * @param iterable<ConditionInterface> $conditions
     * @param iterable<ActionInterface> $actions
     */
    public function __construct(
        private readonly EntityRepository $automationRepository,
        private readonly EntityRepository $abandonedCartRepository,
        private readonly EntityRepository $automationLogRepository,
        private readonly Connection $connection,
        iterable $conditions,
        iterable $actions,
        private readonly LoggerInterface $logger,
    ) {
        foreach ($conditions as $condition) {
            $this->conditionHandlers[$condition->getType()] = $condition;
        }

        foreach ($actions as $action) {
            $this->actionHandlers[$action->getType()] = $action;
        }
    }

    public function process(Context $context): void
    {
        $automations = $this->loadActiveAutomations($context);

        if ($automations->count() === 0) {
            $this->logger->debug('AutomationProcessor: No active automations found');

            return;
        }

        $this->logger->info('AutomationProcessor: Processing automations', [
            'automationCount' => $automations->count(),
        ]);

        foreach ($automations as $automation) {
            $this->processAutomation($automation, $context);
        }
    }

    private function processAutomation(AbandonedCartAutomationEntity $automation, Context $context): void
    {
        $offset = 0;

        do {
            $cartIds = $this->findMatchingCartIds($automation, $offset, $context);

            if (empty($cartIds)) {
                break;
            }

            $this->logger->debug('AutomationProcessor: Found matching carts', [
                'automationId' => $automation->getId(),
                'automationName' => $automation->getName(),
                'cartCount' => \count($cartIds),
                'offset' => $offset,
            ]);

            // Load full cart entities for action execution
            $carts = $this->loadCartsById($cartIds, $context);

            foreach ($carts as $cart) {
                $this->executeAutomationForCart($cart, $automation, $context);
            }

            $offset += self::BATCH_SIZE;
        } while (\count($cartIds) === self::BATCH_SIZE);
    }

    /**
     * @return array<string>
     */
    private function findMatchingCartIds(AbandonedCartAutomationEntity $automation, int $offset, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('LOWER(HEX(cart.id)) as id')
            ->from('frosh_abandoned_cart', 'cart')
            ->setMaxResults(self::BATCH_SIZE)
            ->setFirstResult($offset);

        // Filter by sales channel if specified
        if ($automation->getSalesChannelId() !== null) {
            $query->andWhere('cart.sales_channel_id = :sales_channel_id');
            $query->setParameter('sales_channel_id', Uuid::fromHexToBytes($automation->getSalesChannelId()));
        }

        // Apply all conditions
        foreach ($automation->getConditions() as $conditionConfig) {
            $type = $conditionConfig['type'] ?? null;

            if ($type === null) {
                continue;
            }

            $handler = $this->conditionHandlers[$type] ?? null;

            if ($handler === null) {
                $this->logger->warning('AutomationProcessor: Unknown condition type', ['type' => $type]);
                continue;
            }

            $handler->apply($query, $conditionConfig, $context);
        }

        return $query->executeQuery()->fetchFirstColumn();
    }

    /**
     * @param array<string> $cartIds
     */
    private function loadCartsById(array $cartIds, Context $context): AbandonedCartCollection
    {
        $criteria = new Criteria($cartIds);
        $criteria->addAssociation('customer');
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('lineItems');

        return $this->abandonedCartRepository->search($criteria, $context)->getEntities();
    }

    private function executeAutomationForCart(AbandonedCartEntity $cart, AbandonedCartAutomationEntity $automation, Context $context): void
    {
        $this->logger->debug('AutomationProcessor: Executing automation', [
            'automationId' => $automation->getId(),
            'automationName' => $automation->getName(),
            'cartId' => $cart->getId(),
        ]);

        try {
            $results = $this->executeActions($cart, $automation, $context);
            $this->logExecution($automation->getId(), $cart->getId(), $cart->getCustomerId(), 'success', $results, $context);
            $this->updateCartAutomationStatus($cart, $context);
        } catch (\Throwable $e) {
            $this->logger->error('AutomationProcessor: Failed to execute actions', [
                'automationId' => $automation->getId(),
                'cartId' => $cart->getId(),
                'error' => $e->getMessage(),
            ]);
            $this->logExecution($automation->getId(), $cart->getId(), $cart->getCustomerId(), 'error', ['error' => $e->getMessage()], $context);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function executeActions(AbandonedCartEntity $cart, AbandonedCartAutomationEntity $automation, Context $context): array
    {
        $actionContext = new ActionContext($context);
        $results = [];

        foreach ($automation->getActions() as $index => $actionConfig) {
            $type = $actionConfig['type'] ?? null;

            if ($type === null) {
                $this->logger->warning('AutomationProcessor: Action missing type', ['config' => $actionConfig]);
                continue;
            }

            $handler = $this->actionHandlers[$type] ?? null;

            if ($handler === null) {
                $this->logger->warning('AutomationProcessor: Unknown action type', ['type' => $type]);
                continue;
            }

            try {
                $handler->execute($cart, $actionConfig, $actionContext);
                $results["action_{$index}_{$type}"] = ['status' => 'success'];
            } catch (\Throwable $e) {
                $this->logger->error('AutomationProcessor: Action failed', [
                    'type' => $type,
                    'error' => $e->getMessage(),
                ]);
                $results["action_{$index}_{$type}"] = ['status' => 'error', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $results
     */
    private function logExecution(string $automationId, string $cartId, string $customerId, string $status, array $results, Context $context): void
    {
        $this->automationLogRepository->create([
            [
                'id' => Uuid::randomHex(),
                'automationId' => $automationId,
                'abandonedCartId' => $cartId,
                'customerId' => $customerId,
                'status' => $status,
                'actionResults' => $results,
            ],
        ], $context);
    }

    private function updateCartAutomationStatus(AbandonedCartEntity $cart, Context $context): void
    {
        $this->abandonedCartRepository->update([
            [
                'id' => $cart->getId(),
                'lastAutomationAt' => new \DateTimeImmutable(),
                'automationCount' => $cart->getAutomationCount() + 1,
            ],
        ], $context);
    }

    private function loadActiveAutomations(Context $context): AbandonedCartAutomationCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addSorting(new FieldSorting('priority', FieldSorting::DESCENDING));

        return $this->automationRepository->search($criteria, $context)->getEntities();
    }
}
