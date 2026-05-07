<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class VisionReadinessOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('願景已完成能力', '55')
                ->description('本地 MVP 到願景級產品化已完成清單')
                ->color('success'),
            Stat::make('本地待推缺口', count(config('truthshield_readiness.local_next_points', [])))
                ->description('目前本地可推缺口已收斂，主要剩外部上線依賴')
                ->color(count(config('truthshield_readiness.local_next_points', [])) > 0 ? 'warning' : 'success'),
            Stat::make('外部上線依賴', count(config('truthshield_readiness.external_launch_dependencies', [])))
                ->description('OAuth、HTTPS、Web Store、正式金流、壓測與法務')
                ->color('gray'),
        ];
    }
}
