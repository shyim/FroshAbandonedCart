<?php declare(strict_types=1);

namespace Frosh\AbandonedCart\Controller\Api;

use Frosh\AbandonedCart\Automation\Condition\ConditionInterface;
use Frosh\AbandonedCart\Entity\AbandonedCartCollection;
use Frosh\AbandonedCart\Entity\AbandonedCartEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AbandonedCartAutomationTestController extends AbstractController
{
    /**
     * @param EntityRepository<AbandonedCartCollection> $abandonedCartRepository
     * @param iterable<ConditionInterface> $conditions
     */
    public function __construct(
        private readonly EntityRepository $abandonedCartRepository,
        private readonly iterable $conditions,
    ) {
    }

    #[Route(path: '/api/_action/frosh-abandoned-cart/automation/test', name: 'api.action.frosh-abandoned-cart.automation.test', methods: ['POST'])]
    public function testAutomation(Request $request, Context $context): JsonResponse
    {
        $conditions = $request->request->all('conditions');
        $salesChannelId = $request->request->get('salesChannelId');

        // Build condition handlers map
        $conditionHandlers = [];
        foreach ($this->conditions as $condition) {
            $conditionHandlers[$condition->getType()] = $condition;
        }

        // Load abandoned carts with associations
        $criteria = new Criteria();
        $criteria->addAssociation('customer');
        $criteria->addAssociation('salesChannel');
        $criteria->addAssociation('lineItems');
        $criteria->setLimit(100);

        if ($salesChannelId) {
            $criteria->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));
        }

        $carts = $this->abandonedCartRepository->search($criteria, $context)->getEntities();

        $matchingCarts = [];
        $nonMatchingCarts = [];

        foreach ($carts as $cart) {
            $matches = $this->evaluateConditions($cart, $conditions, $conditionHandlers);

            $cartData = [
                'id' => $cart->getId(),
                'customerEmail' => $cart->getCustomer()?->getEmail(),
                'customerName' => $cart->getCustomer() ? $cart->getCustomer()->getFirstName() . ' ' . $cart->getCustomer()->getLastName() : 'Unknown',
                'totalPrice' => $cart->getTotalPrice(),
                'currencyIsoCode' => $cart->getCurrencyIsoCode(),
                'createdAt' => $cart->getCreatedAt()?->format('Y-m-d H:i:s'),
                'automationCount' => $cart->getAutomationCount(),
                'lineItemCount' => $cart->getLineItems()?->count() ?? 0,
            ];

            if ($matches) {
                $matchingCarts[] = $cartData;
            } else {
                $nonMatchingCarts[] = $cartData;
            }
        }

        return new JsonResponse([
            'matchingCount' => \count($matchingCarts),
            'totalCount' => $carts->count(),
            'matchingCarts' => $matchingCarts,
            'nonMatchingCarts' => \array_slice($nonMatchingCarts, 0, 10),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $conditionConfigs
     * @param array<string, ConditionInterface> $conditionHandlers
     */
    private function evaluateConditions(AbandonedCartEntity $cart, array $conditionConfigs, array $conditionHandlers): bool
    {
        if (empty($conditionConfigs)) {
            return true;
        }

        foreach ($conditionConfigs as $conditionConfig) {
            $type = $conditionConfig['type'] ?? null;

            if ($type === null || !isset($conditionHandlers[$type])) {
                return false;
            }

            if (!$conditionHandlers[$type]->evaluate($cart, $conditionConfig)) {
                return false;
            }
        }

        return true;
    }
}
