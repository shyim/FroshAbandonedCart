<?php declare(strict_types=1);

namespace Frosh\AbandonedCart\Controller\Api;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AbandonedCartStatisticsController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Route(path: '/api/_action/frosh-abandoned-cart/statistics', name: 'api.action.frosh-abandoned-cart.statistics', methods: ['GET'])]
    public function getStatistics(Request $request, Context $context): JsonResponse
    {
        $since = $request->query->get('since', (new \DateTimeImmutable('-30 days'))->format('Y-m-d'));
        $timezone = $request->query->get('timezone', 'UTC');

        $sinceDate = new \DateTimeImmutable($since, new \DateTimeZone($timezone));
        $today = new \DateTimeImmutable('today', new \DateTimeZone($timezone));
        $tomorrow = $today->modify('+1 day');

        $totals = $this->fetchTotals();
        $todayStats = $this->fetchTodayStats($today, $tomorrow, $timezone);
        $dailyStats = $this->fetchDailyStats($sinceDate, $timezone);

        return new JsonResponse([
            'totalCount' => (int) $totals['total_count'],
            'totalValue' => (float) $totals['total_value'],
            'todayCount' => (int) $todayStats['today_count'],
            'todayValue' => (float) $todayStats['today_value'],
            'dailyStats' => $dailyStats,
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
}
