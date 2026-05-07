<?php

namespace App\Filament\Widgets;

use App\Models\NewsDomain;
use App\Models\NewsDomainReport;
use App\Models\NewsUrl;
use App\Models\User;
use App\Models\Vote;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SystemStatusOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('使用者', User::query()->count())
                ->description('已註冊身份'),
            Stat::make('追蹤新聞', NewsUrl::query()->count())
                ->description('已正規化 URL 指紋'),
            Stat::make('投票', Vote::query()->count())
                ->description('加權使用者判斷'),
            Stat::make('待審新聞站', NewsDomainReport::query()->where('status', 'pending')->count())
                ->description(NewsDomain::query()->where('is_active', true)->count() . ' 個啟用網域'),
        ];
    }
}
