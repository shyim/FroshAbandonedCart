<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Automation;

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

        $carts = $this->loadAbandonedCarts($context);

        if ($carts->count() === 0) {
            $this->logger->debug('AutomationProcessor: No abandoned carts to process');

            return;
        }

        $this->logger->info('AutomationProcessor: Processing abandoned carts', [
            'automationCount' => $automations->count(),
            'cartCount' => $carts->count(),
        ]);

        foreach ($carts as $cart) {
            $this->processCart($cart, $automations, $context);
        }
    }

    private function processCart(AbandonedCartEntity $cart, AbandonedCartAutomationCollection $automations, Context $context): void
    {
        foreach ($automations as $automation) {
            // Check if automation is applicable to this cart's sales channel
            if ($automation->getSalesChannelId() !== null && $automation->getSalesChannelId() !== $cart->getSalesChannelId()) {
                continue;
            }

            $conditions = $automation->getConditions();

            if (!$this->evaluateConditions($cart, $conditions, $context)) {
                continue;
            }

            // All conditions matched, execute actions
            $this->logger->debug('AutomationProcessor: Automation matched', [
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

            // First matching automation wins (highest priority), stop processing
            break;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $conditionConfigs
     */
    private function evaluateConditions(AbandonedCartEntity $cart, array $conditionConfigs, Context $context): bool
    {
        // All conditions must match (AND logic)
        foreach ($conditionConfigs as $conditionConfig) {
            $type = $conditionConfig['type'] ?? null;

            if ($type === null) {
                $this->logger->warning('AutomationProcessor: Condition config missing type', ['config' => $conditionConfig]);

                return false;
            }

            $handler = $this->conditionHandlers[$type] ?? null;

            if ($handler === null) {
                $this->logger->warning('AutomationProcessor: No handler found for condition type', ['type' => $type]);

                return false;
            }

            if (!$handler->evaluate($cart, $conditionConfig, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function executeActions(AbandonedCartEntity $cart, AbandonedCartAutomationEntity $automation, Context $context): array
    {
        $actionContext = new ActionContext($context);
        $results = [];

        $actionConfigs = $automation->getActions();

        foreach ($actionConfigs as $index => $actionConfig) {
            $type = $actionConfig['type'] ?? null;

            if ($type === null) {
                $this->logger->warning('AutomationProcessor: Action config missing type', ['config' => $actionConfig]);
                continue;
            }

            $handler = $this->actionHandlers[$type] ?? null;

            if ($handler === null) {
                $this->logger->warning('AutomationProcessor: No handler found for action type', ['type' => $type]);
                continue;
            }

            try {
                $handler->execute($cart, $actionConfig, $actionContext);
                $results["action_{$index}_{$type}"] = ['status' => 'success'];
            } catch (\Throwable $e) {
                $this->logger->error('AutomationProcessor: Action execution failed', [
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

    private function loadAbandonedCarts(Context $context): AbandonedCartCollection
    {
        $criteria = new Criteria();
        $criteria->addAssociation('customer');
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('lineItems');

        $criteria->setLimit(100);

        return $this->abandonedCartRepository->search($criteria, $context)->getEntities();
    }
}
