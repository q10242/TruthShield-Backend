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
                ->description('不依賴外部帳號、可在本地繼續實作')
                ->color('warning'),
            Stat::make('外部上線依賴', 10)
                ->description('OAuth、HTTPS、Web Store、正式金流、壓測與法務')
                ->color('gray'),
        ];
    }
}
