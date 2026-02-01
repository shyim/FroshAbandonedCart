<?php declare(strict_types=1);

namespace Frosh\AbandonedCart\Controller\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AbandonedCartStatisticsController extends AbstractController
{
    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $productRepository,
    ) {
    }

    #[Route(path: '/api/_action/frosh-abandoned-cart/statistics', name: 'api.action.frosh-abandoned-cart.statistics', methods: ['GET'])]
    public function getStatistics(Request $request, Context $context): JsonResponse
    {
        $since = $request->query->get('since', (new \DateTimeImmutable('-30 days'))->format('Y-m-d'));
        $timezone = $request->query->get('timezone', 'UTC');
        $limit = $request->query->getInt('topProductsLimit', 10);

        $sinceDate = new \DateTimeImmutable($since, new \DateTimeZone($timezone));
        $today = new \DateTimeImmutable('today', new \DateTimeZone($timezone));
        $tomorrow = $today->modify('+1 day');

        $totals = $this->fetchTotals();
        $todayStats = $this->fetchTodayStats($today, $tomorrow, $timezone);
        $dailyStats = $this->fetchDailyStats($sinceDate, $timezone);
        $topProducts = $this->fetchTopProducts($limit, $context);

        return new JsonResponse([
            'totalCount' => (int) $totals['total_count'],
            'totalValue' => (float) $totals['total_value'],
            'todayCount' => (int) $todayStats['today_count'],
            'todayValue' => (float) $todayStats['today_value'],
            'dailyStats' => $dailyStats,
            'topProducts' => $topProducts,
        ]);
    }

    /**
     * @return array{total_count: int|string, total_value: float|string}
     */
    private function fetchTotals(): array
    {
        $result = $this->connection->fetchAssociative(
            'SELECT COUNT(*) as total_count, COALESCE(SUM(total_price), 0) as total_value FROM frosh_abandoned_cart'
        );

        return $result ?: ['total_count' => 0, 'total_value' => 0];
    }

    /**
     * @return array{today_count: int|string, today_value: float|string}
     */
    private function fetchTodayStats(\DateTimeImmutable $today, \DateTimeImmutable $tomorrow, string $timezone): array
    {
        $todayUtc = $today->setTimezone(new \DateTimeZone('UTC'));
        $tomorrowUtc = $tomorrow->setTimezone(new \DateTimeZone('UTC'));

        $result = $this->connection->fetchAssociative(
            'SELECT COUNT(*) as today_count, COALESCE(SUM(total_price), 0) as today_value
             FROM frosh_abandoned_cart
             WHERE created_at >= :today AND created_at < :tomorrow',
            [
                'today' => $todayUtc->format('Y-m-d H:i:s'),
                'tomorrow' => $tomorrowUtc->format('Y-m-d H:i:s'),
            ]
        );

        return $result ?: ['today_count' => 0, 'today_value' => 0];
    }

    /**
     * @return array<array{date: string, count: int, value: float}>
     */
    private function fetchDailyStats(\DateTimeImmutable $since, string $timezone): array
    {
        $sinceUtc = $since->setTimezone(new \DateTimeZone('UTC'));

        $results = $this->connection->fetchAllAssociative(
            'SELECT
                DATE(created_at) as date,
                COUNT(*) as count,
                COALESCE(SUM(total_price), 0) as value
             FROM frosh_abandoned_cart
             WHERE created_at >= :since
             GROUP BY DATE(created_at)
             ORDER BY date ASC',
            [
                'since' => $sinceUtc->format('Y-m-d H:i:s'),
            ]
        );

        return array_map(static fn (array $row) => [
            'date' => $row['date'],
            'count' => (int) $row['count'],
            'value' => (float) $row['value'],
        ], $results);
    }

    /**
     * @return array<array{productId: string|null, productNumber: string|null, label: string, count: int, totalQuantity: int, totalValue: float}>
     */
    private function fetchTopProducts(int $limit, Context $context): array
    {
        $results = $this->connection->fetchAllAssociative(
            'SELECT
                LOWER(HEX(li.product_id)) as product_id,
                li.label,
                COUNT(DISTINCT li.abandoned_cart_id) as cart_count,
                SUM(li.quantity) as total_quantity,
                SUM(li.total_price) as total_value
             FROM frosh_abandoned_cart_line_item li
             GROUP BY li.product_id, li.label
             ORDER BY cart_count DESC, total_value DESC
             LIMIT :limit',
            ['limit' => $limit],
            ['limit' => \Doctrine\DBAL\ParameterType::INTEGER]
        );

        $productIds = array_filter(array_column($results, 'product_id'));
        $products = [];

        if ($productIds !== []) {
            $criteria = new Criteria($productIds);
            $productEntities = $this->productRepository->search($criteria, $context);

            foreach ($productEntities as $product) {
                $products[$product->getId()] = $product;
            }
        }

        return array_map(static function (array $row) use ($products) {
            $productId = $row['product_id'];
            $product = $productId !== null ? ($products[$productId] ?? null) : null;

            return [
                'productId' => $productId,
                'productNumber' => $product?->getProductNumber(),
                'label' => $product?->getTranslation('name') ?? $row['label'],
                'count' => (int) $row['cart_count'],
                'totalQuantity' => (int) $row['total_quantity'],
                'totalValue' => (float) $row['total_value'],
            ];
        }, $results);
    }
}
