<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Tests\Integration\Automation;

use Doctrine\DBAL\Connection;
use Frosh\AbandonedCart\Automation\Action\ActionContext;
use Frosh\AbandonedCart\Automation\Action\ActionInterface;
use Frosh\AbandonedCart\Automation\AutomationProcessor;
use Frosh\AbandonedCart\Automation\Condition\ConditionInterface;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

class AutomationProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private Connection $connection;

    private EntityRepository $automationRepository;

    private EntityRepository $abandonedCartRepository;

    private EntityRepository $automationLogRepository;

    private string $customerId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->automationRepository = static::getContainer()->get('frosh_abandoned_cart_automation.repository');
        $this->abandonedCartRepository = static::getContainer()->get('frosh_abandoned_cart.repository');
        $this->automationLogRepository = static::getContainer()->get('frosh_abandoned_cart_automation_log.repository');

        $this->customerId = $this->createCustomer();
    }

    public function testProcessAutomationWithMatchingConditionsExecutesActions(): void
    {
        $actionExecuted = false;
        $executedCartId = null;

        $mockCondition = $this->createMockCondition('test_condition', true);
        $mockAction = $this->createMockAction('test_action', function (AbandonedCartEntity $cart) use (&$actionExecuted, &$executedCartId): void {
            $actionExecuted = true;
            $executedCartId = $cart->getId();
        });

        $cartId = $this->createAbandonedCart($this->customerId, 100.0);

        $automationId = $this->createAutomation(
            'Test Automation',
            true,
            100,
            [['type' => 'test_condition', 'value' => 50]],
            [['type' => 'test_action']]
        );

        $processor = $this->createProcessor([$mockCondition], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        static::assertTrue($actionExecuted, 'Action should have been executed');
        static::assertSame($cartId, $executedCartId, 'Action should have been executed for the correct cart');

        $log = $this->automationLogRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('automationId', $automationId)),
            Context::createDefaultContext()
        )->first();

        static::assertNotNull($log, 'Automation log should be created');
        static::assertSame('success', $log->getStatus());
    }

    public function testProcessAutomationWithNonMatchingConditionsDoesNotExecuteActions(): void
    {
        $actionExecuted = false;

        $mockCondition = $this->createMockCondition('test_condition', false);
        $mockAction = $this->createMockAction('test_action', function () use (&$actionExecuted): void {
            $actionExecuted = true;
        });

        $this->createAbandonedCart($this->customerId, 100.0);

        $this->createAutomation(
            'Test Automation',
            true,
            100,
            [['type' => 'test_condition', 'value' => 200]],
            [['type' => 'test_action']]
        );

        $processor = $this->createProcessor([$mockCondition], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        static::assertFalse($actionExecuted, 'Action should not have been executed when conditions do not match');

        $logCount = $this->automationLogRepository->search(new Criteria(), Context::createDefaultContext())->getTotal();
        static::assertSame(0, $logCount, 'No automation log should be created when conditions do not match');
    }

    public function testMultipleAutomationsWithPriorityOrdering(): void
    {
        $executionOrder = [];

        $mockCondition = $this->createMockCondition('test_condition', true);

        $mockActionHigh = $this->createMockAction('high_priority_action', function () use (&$executionOrder): void {
            $executionOrder[] = 'high';
        });

        $mockActionLow = $this->createMockAction('low_priority_action', function () use (&$executionOrder): void {
            $executionOrder[] = 'low';
        });

        $this->createAbandonedCart($this->customerId, 100.0);

        $this->createAutomation(
            'Low Priority Automation',
            true,
            10,
            [['type' => 'test_condition']],
            [['type' => 'low_priority_action']]
        );

        $this->createAutomation(
            'High Priority Automation',
            true,
            100,
            [['type' => 'test_condition']],
            [['type' => 'high_priority_action']]
        );

        $processor = $this->createProcessor([$mockCondition], [$mockActionHigh, $mockActionLow]);
        $processor->process(Context::createDefaultContext());

        static::assertCount(1, $executionOrder, 'Only one automation should execute (first matching wins)');
        static::assertSame('high', $executionOrder[0], 'Higher priority automation should execute first');
    }

    public function testAutomationWithSalesChannelIdFilter(): void
    {
        $actionExecuted = false;

        $mockCondition = $this->createMockCondition('test_condition', true);
        $mockAction = $this->createMockAction('test_action', function () use (&$actionExecuted): void {
            $actionExecuted = true;
        });

        $this->createAbandonedCart($this->customerId, 100.0);

        // Create a second sales channel for testing using the trait helper
        $differentSalesChannel = $this->createSalesChannel();

        $this->createAutomation(
            'Sales Channel Specific Automation',
            true,
            100,
            [['type' => 'test_condition']],
            [['type' => 'test_action']],
            $differentSalesChannel['id']
        );

        $processor = $this->createProcessor([$mockCondition], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        static::assertFalse($actionExecuted, 'Action should not execute for cart from different sales channel');
    }

    public function testAutomationWithMatchingSalesChannelIdExecutes(): void
    {
        $actionExecuted = false;

        $mockCondition = $this->createMockCondition('test_condition', true);
        $mockAction = $this->createMockAction('test_action', function () use (&$actionExecuted): void {
            $actionExecuted = true;
        });

        $this->createAbandonedCart($this->customerId, 100.0);

        $this->createAutomation(
            'Sales Channel Specific Automation',
            true,
            100,
            [['type' => 'test_condition']],
            [['type' => 'test_action']],
            TestDefaults::SALES_CHANNEL
        );

        $processor = $this->createProcessor([$mockCondition], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        static::assertTrue($actionExecuted, 'Action should execute for cart from matching sales channel');
    }

    public function testInactiveAutomationShouldBeSkipped(): void
    {
        $actionExecuted = false;

        $mockCondition = $this->createMockCondition('test_condition', true);
        $mockAction = $this->createMockAction('test_action', function () use (&$actionExecuted): void {
            $actionExecuted = true;
        });

        $this->createAbandonedCart($this->customerId, 100.0);

        $this->createAutomation(
            'Inactive Automation',
            false,
            100,
            [['type' => 'test_condition']],
            [['type' => 'test_action']]
        );

        $processor = $this->createProcessor([$mockCondition], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        static::assertFalse($actionExecuted, 'Inactive automation should not execute');
    }

    public function testAutomationCountAndLastAutomationAtShouldBeUpdatedAfterExecution(): void
    {
        $mockCondition = $this->createMockCondition('test_condition', true);
        $mockAction = $this->createMockAction('test_action', function (): void {
        });

        $cartId = $this->createAbandonedCart($this->customerId, 100.0);

        $this->createAutomation(
            'Test Automation',
            true,
            100,
            [['type' => 'test_condition']],
            [['type' => 'test_action']]
        );

        $cartBefore = $this->abandonedCartRepository->search(
            new Criteria([$cartId]),
            Context::createDefaultContext()
        )->first();

        static::assertSame(0, $cartBefore->getAutomationCount());
        static::assertNull($cartBefore->getLastAutomationAt());

        $processor = $this->createProcessor([$mockCondition], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        $cartAfter = $this->abandonedCartRepository->search(
            new Criteria([$cartId]),
            Context::createDefaultContext()
        )->first();

        static::assertSame(1, $cartAfter->getAutomationCount(), 'Automation count should be incremented');
        static::assertNotNull($cartAfter->getLastAutomationAt(), 'Last automation timestamp should be set');
        static::assertInstanceOf(\DateTimeInterface::class, $cartAfter->getLastAutomationAt());
    }

    public function testAutomationLogShouldBeCreatedAfterExecution(): void
    {
        $mockCondition = $this->createMockCondition('test_condition', true);
        $mockAction = $this->createMockAction('test_action', function (): void {
        });

        $cartId = $this->createAbandonedCart($this->customerId, 100.0);

        $automationId = $this->createAutomation(
            'Test Automation',
            true,
            100,
            [['type' => 'test_condition']],
            [['type' => 'test_action']]
        );

        $processor = $this->createProcessor([$mockCondition], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        $logs = $this->automationLogRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('automationId', $automationId)),
            Context::createDefaultContext()
        );

        static::assertSame(1, $logs->getTotal(), 'One automation log should be created');

        $log = $logs->first();
        static::assertSame($automationId, $log->getAutomationId());
        static::assertSame($cartId, $log->getAbandonedCartId());
        static::assertSame($this->customerId, $log->getCustomerId());
        static::assertSame('success', $log->getStatus());
        static::assertIsArray($log->getActionResults());
    }

    public function testAutomationLogShouldContainActionErrorInResults(): void
    {
        $mockCondition = $this->createMockCondition('test_condition', true);
        $mockAction = $this->createMockAction('test_action', function (): void {
            throw new \RuntimeException('Action failed');
        });

        $this->createAbandonedCart($this->customerId, 100.0);

        $automationId = $this->createAutomation(
            'Test Automation',
            true,
            100,
            [['type' => 'test_condition']],
            [['type' => 'test_action']]
        );

        $processor = $this->createProcessor([$mockCondition], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        $log = $this->automationLogRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('automationId', $automationId)),
            Context::createDefaultContext()
        )->first();

        static::assertNotNull($log, 'Automation log should be created even on action failure');
        // The automation itself succeeds, but action results contain the error
        static::assertSame('success', $log->getStatus());
        $actionResults = $log->getActionResults();
        static::assertIsArray($actionResults);
        static::assertArrayHasKey('action_0_test_action', $actionResults);
        static::assertSame('error', $actionResults['action_0_test_action']['status']);
        static::assertSame('Action failed', $actionResults['action_0_test_action']['error']);
    }

    public function testNoProcessingWhenNoAbandonedCarts(): void
    {
        $actionExecuted = false;

        $mockCondition = $this->createMockCondition('test_condition', true);
        $mockAction = $this->createMockAction('test_action', function () use (&$actionExecuted): void {
            $actionExecuted = true;
        });

        $this->createAutomation(
            'Test Automation',
            true,
            100,
            [['type' => 'test_condition']],
            [['type' => 'test_action']]
        );

        $processor = $this->createProcessor([$mockCondition], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        static::assertFalse($actionExecuted, 'No actions should execute when there are no abandoned carts');
    }

    public function testNoProcessingWhenNoActiveAutomations(): void
    {
        $actionExecuted = false;

        $mockCondition = $this->createMockCondition('test_condition', true);
        $mockAction = $this->createMockAction('test_action', function () use (&$actionExecuted): void {
            $actionExecuted = true;
        });

        $this->createAbandonedCart($this->customerId, 100.0);

        $processor = $this->createProcessor([$mockCondition], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        static::assertFalse($actionExecuted, 'No actions should execute when there are no active automations');
    }

    public function testMultipleConditionsMustAllMatch(): void
    {
        $actionExecuted = false;

        $mockConditionTrue = $this->createMockCondition('condition_true', true);
        $mockConditionFalse = $this->createMockCondition('condition_false', false);
        $mockAction = $this->createMockAction('test_action', function () use (&$actionExecuted): void {
            $actionExecuted = true;
        });

        $this->createAbandonedCart($this->customerId, 100.0);

        $this->createAutomation(
            'Test Automation',
            true,
            100,
            [
                ['type' => 'condition_true'],
                ['type' => 'condition_false'],
            ],
            [['type' => 'test_action']]
        );

        $processor = $this->createProcessor([$mockConditionTrue, $mockConditionFalse], [$mockAction]);
        $processor->process(Context::createDefaultContext());

        static::assertFalse($actionExecuted, 'Action should not execute when not all conditions match');
    }

    public function testMultipleActionsAreExecutedInSequence(): void
    {
        $executionOrder = [];

        $mockCondition = $this->createMockCondition('test_condition', true);
        $mockAction1 = $this->createMockAction('action_1', function () use (&$executionOrder): void {
            $executionOrder[] = 'action_1';
        });
        $mockAction2 = $this->createMockAction('action_2', function () use (&$executionOrder): void {
            $executionOrder[] = 'action_2';
        });

        $this->createAbandonedCart($this->customerId, 100.0);

        $this->createAutomation(
            'Test Automation',
            true,
            100,
            [['type' => 'test_condition']],
            [
                ['type' => 'action_1'],
                ['type' => 'action_2'],
            ]
        );

        $processor = $this->createProcessor([$mockCondition], [$mockAction1, $mockAction2]);
        $processor->process(Context::createDefaultContext());

        static::assertSame(['action_1', 'action_2'], $executionOrder, 'Actions should be executed in sequence');
    }

    /**
     * @param array<ConditionInterface> $conditions
     * @param array<ActionInterface> $actions
     */
    private function createProcessor(array $conditions, array $actions): AutomationProcessor
    {
        return new AutomationProcessor(
            $this->automationRepository,
            $this->abandonedCartRepository,
            $this->automationLogRepository,
            $conditions,
            $actions,
            new NullLogger()
        );
    }

    private function createMockCondition(string $type, bool $returnValue): ConditionInterface
    {
        return new class($type, $returnValue) implements ConditionInterface {
            public function __construct(
                private readonly string $type,
                private readonly bool $returnValue
            ) {
            }

            public function getType(): string
            {
                return $this->type;
            }

            public function evaluate(AbandonedCartEntity $cart, array $config): bool
            {
                return $this->returnValue;
            }
        };
    }

    private function createMockAction(string $type, callable $callback): ActionInterface
    {
        return new class($type, $callback) implements ActionInterface {
            /** @var callable */
            private $callback;

            public function __construct(
                private readonly string $type,
                callable $callback
            ) {
                $this->callback = $callback;
            }

            public function getType(): string
            {
                return $this->type;
            }

            public function execute(AbandonedCartEntity $cart, array $config, ActionContext $context): void
            {
                ($this->callback)($cart, $config, $context);
            }
        };
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstrasse 1',
                'city' => 'Schoeppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'password',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        static::getContainer()->get('customer.repository')->create([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function createAbandonedCart(string $customerId, float $totalPrice): string
    {
        $cartId = Uuid::randomHex();

        $this->abandonedCartRepository->create([
            [
                'id' => $cartId,
                'customerId' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'totalPrice' => $totalPrice,
                'currencyIsoCode' => 'EUR',
            ],
        ], Context::createDefaultContext());

        return $cartId;
    }

    /**
     * @param array<int, array<string, mixed>> $conditions
     * @param array<int, array<string, mixed>> $actions
     */
    private function createAutomation(
        string $name,
        bool $active,
        int $priority,
        array $conditions,
        array $actions,
        ?string $salesChannelId = null
    ): string {
        $automationId = Uuid::randomHex();

        $this->automationRepository->create([
            [
                'id' => $automationId,
                'name' => $name,
                'active' => $active,
                'priority' => $priority,
                'conditions' => $conditions,
                'actions' => $actions,
                'salesChannelId' => $salesChannelId,
            ],
        ], Context::createDefaultContext());

        return $automationId;
    }
}
