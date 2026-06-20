<?php

namespace App\Services;

use App\Models\Post;
use App\Models\RequestLog;
use App\Models\Website;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Build the dashboard payload.
     *
     * @return array{
     *     totals: array{
     *         websites: int,
     *         request_logs_today: int,
     *         posts: int,
     *         request_logs_this_month: int
     *     },
     *     request_logs_daily: array<int, array{
     *         date: string,
     *         label: string,
     *         total: int
     *     }>,
     *     request_logs_top_routes: array<int, array{
     *         route: string,
     *         total: int,
     *         percentage: float
     *     }>
     * }
     */
    public function getData(): array
    {
        $today = CarbonImmutable::today();
        $dailyLogs = $this->requestLogsDaily($today);
        $topRoutes = $this->requestLogsTopRoutes($today);

        return [
            'totals' => [
                'websites' => Website::query()->count(),
                'request_logs_today' => RequestLog::query()
                    ->whereDate('created_at', $today)
                    ->count(),
                'posts' => Post::query()->count(),
                'request_logs_this_month' => RequestLog::query()
                    ->whereBetween('created_at', [
                        $today->startOfMonth(),
                        $today->endOfMonth(),
                    ])
                    ->count(),
            ],
            'request_logs_daily' => $dailyLogs->values()->all(),
            'request_logs_top_routes' => $topRoutes->values()->all(),
        ];
    }

    /**
     * @return Collection<int, array{date: string, label: string, total: int}>
     */
    private function requestLogsDaily(CarbonImmutable $today): Collection
    {
        $startDate = $today->subDays(29);

        /** @var Collection<string, int> $dailyTotals */
        $dailyTotals = RequestLog::query()
            ->whereBetween('created_at', [
                $startDate->startOfDay(),
                $today->endOfDay(),
            ])
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day')
            ->map(fn (mixed $total): int => (int) $total);

        return collect(range(0, 29))
            ->map(function (int $offset) use ($startDate, $dailyTotals): array {
                $date = $startDate->addDays($offset);
                $dateKey = $date->toDateString();

                return [
                    'date' => $dateKey,
                    'label' => $date->format('d M'),
                    'total' => $dailyTotals->get($dateKey, 0),
                ];
            });
    }

    /**
     * @return Collection<int, array{route: string, total: int, percentage: float}>
     */
    private function requestLogsTopRoutes(CarbonImmutable $today): Collection
    {
        $routes = RequestLog::query()
            ->whereBetween('created_at', [
                $today->subYear()->startOfDay(),
                $today->endOfDay(),
            ])
            ->selectRaw('route, count(*) as total')
            ->groupBy('route')
            ->orderByDesc('total')
            ->orderBy('route')
            ->limit(5)
            ->get()
            ->map(fn (RequestLog $requestLog): array => [
                'route' => $requestLog->route,
                'total' => (int) $requestLog->getAttribute('total'),
            ]);

        $grandTotal = $routes->sum('total');

        return $routes->map(fn (array $route): array => [
            'route' => $route['route'],
            'total' => $route['total'],
            'percentage' => $grandTotal === 0
                ? 0.0
                : round(($route['total'] / $grandTotal) * 100, 2),
        ]);
    }
}
