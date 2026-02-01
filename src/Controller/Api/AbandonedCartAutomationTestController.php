<?php

declare(strict_types=1);

namespace Frosh\AbandonedCart\Controller\Api;

use Doctrine\DBAL\Connection;
use Frosh\AbandonedCart\Automation\Condition\ConditionInterface;
use Frosh\AbandonedCart\Entity\AbandonedCartCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AbandonedCartAutomationTestController extends AbstractController
{
    /**
     * @var array<string, ConditionInterface>
     */
    private array $conditionHandlers = [];

    /**
     * @param EntityRepository<AbandonedCartCollection> $abandonedCartRepository
     * @param iterable<ConditionInterface> $conditions
     */
    public function __construct(
        private readonly EntityRepository $abandonedCartRepository,
        private readonly Connection $connection,
        iterable $conditions,
    ) {
        foreach ($conditions as $condition) {
            $this->conditionHandlers[$condition->getType()] = $condition;
        }
    }

    #[Route(path: '/api/_action/frosh-abandoned-cart/automation/test', name: 'api.action.frosh-abandoned-cart.automation.test', methods: ['POST'])]
    public function testAutomation(Request $request, Context $context): JsonResponse
    {
        $conditions = $request->request->all('conditions');
        $salesChannelId = $request->request->get('salesChannelId');
        $page = max(1, (int) $request->request->get('page', 1));
        $limit = min(100, max(1, (int) $request->request->get('limit', 25)));
        $offset = ($page - 1) * $limit;

        // Build query with SQL conditions
        $query = $this->connection->createQueryBuilder();
        $query->select('LOWER(HEX(cart.id)) as id')
            ->from('frosh_abandoned_cart', 'cart')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($salesChannelId) {
            $query->andWhere('cart.sales_channel_id = :sales_channel_id');
            $query->setParameter('sales_channel_id', Uuid::fromHexToBytes($salesChannelId));
        }

        // Apply conditions
        foreach ($conditions as $conditionConfig) {
            $type = $conditionConfig['type'] ?? null;

            if ($type === null || !isset($this->conditionHandlers[$type])) {
                continue;
            }

            $this->conditionHandlers[$type]->apply($query, $conditionConfig, $context);
        }

        $matchingCartIds = $query->executeQuery()->fetchFirstColumn();

        // Build count query with same conditions (without pagination)
        $countQuery = $this->connection->createQueryBuilder();
        $countQuery->select('COUNT(*)')
            ->from('frosh_abandoned_cart', 'cart');

        if ($salesChannelId) {
            $countQuery->andWhere('cart.sales_channel_id = :sales_channel_id');
            $countQuery->setParameter('sales_channel_id', Uuid::fromHexToBytes($salesChannelId));
        }

        foreach ($conditions as $conditionConfig) {
            $type = $conditionConfig['type'] ?? null;

            if ($type === null || !isset($this->conditionHandlers[$type])) {
                continue;
            }

            $this->conditionHandlers[$type]->apply($countQuery, $conditionConfig, $context);
        }

        $matchingCount = (int) $countQuery->executeQuery()->fetchOne();

        // Load full cart data for matching carts
        $matchingCarts = [];
        if (!empty($matchingCartIds)) {
            $criteria = new Criteria($matchingCartIds);
            $criteria->addAssociation('customer');
            $criteria->addAssociation('salesChannel');
            $criteria->addAssociation('lineItems');

            $carts = $this->abandonedCartRepository->search($criteria, $context)->getEntities();

            foreach ($carts as $cart) {
                $matchingCarts[] = [
                    'id' => $cart->getId(),
                    'customerEmail' => $cart->getCustomer()?->getEmail(),
                    'customerName' => $cart->getCustomer() ? $cart->getCustomer()->getFirstName() . ' ' . $cart->getCustomer()->getLastName() : 'Unknown',
                    'totalPrice' => $cart->getTotalPrice(),
                    'currencyIsoCode' => $cart->getCurrencyIsoCode(),
                    'createdAt' => $cart->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'automationCount' => $cart->getAutomationCount(),
                    'lineItemCount' => $cart->getLineItems()?->count() ?? 0,
                ];
            }
        }

        return new JsonResponse([
            'matchingCount' => $matchingCount,
            'matchingCarts' => $matchingCarts,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => (int) ceil($matchingCount / $limit),
        ]);
    }
}
