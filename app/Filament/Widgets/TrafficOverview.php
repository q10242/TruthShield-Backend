<?php

namespace App\Filament\Widgets;

use App\Services\TrafficAnalyticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TrafficOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $traffic = app(TrafficAnalyticsService::class)->publicSummary();

        return [
            Stat::make('今日 API 請求', $traffic['today_api_requests'] ?? 0)
                ->description('抽樣還原後估算值')
                ->color('info'),
            Stat::make('Status 查詢', $traffic['today_status_queries'] ?? 0)
                ->description('Tooltip / 橫幅主要流量')
                ->color('info'),
            Stat::make('Cache hit rate', ($traffic['cache_hit_rate'] ?? 0).'%')
                ->description('越高代表 Redis 越有效')
                ->color(($traffic['cache_hit_rate'] ?? 0) >= 70 ? 'success' : 'warning'),
            Stat::make('插件活躍裝置', $traffic['today_active_extension_clients'] ?? 0)
                ->description('每日匿名 session，非跨日追蹤')
                ->color('success'),
            Stat::make('橫幅 / Tooltip', ($traffic['today_banner_views'] ?? 0).' / '.($traffic['today_tooltip_views'] ?? 0))
                ->description('插件實際觸達')
                ->color('gray'),
            Stat::make('投票 / 證據', ($traffic['today_votes'] ?? 0).' / '.($traffic['today_evidence_submissions'] ?? 0))
                ->description('核心參與行為')
                ->color('success'),
        ];
    }
}
