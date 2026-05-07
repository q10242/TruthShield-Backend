<?php

namespace App\Filament\Widgets;

use App\Models\NewsDomain;
use App\Models\NewsDomainReport;
use App\Models\NewsUrl;
use App\Models\User;
use App\Models\Vote;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class SystemStatusOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = Cache::store(config('truthshield.status_cache_store'))->remember(
            'admin:dashboard:system-status:v1',
            now()->addSeconds(30),
            fn () => [
                'users' => User::query()->count(),
                'news_urls' => NewsUrl::query()->count(),
                'votes' => Vote::query()->count(),
                'pending_domains' => NewsDomainReport::query()->where('status', 'pending')->count(),
                'active_domains' => NewsDomain::query()->where('is_active', true)->count(),
            ],
        );

        return [
            Stat::make('使用者', $stats['users'])
                ->description('已註冊身份'),
            Stat::make('追蹤新聞', $stats['news_urls'])
                ->description('已正規化 URL 指紋'),
            Stat::make('投票', $stats['votes'])
                ->description('加權使用者判斷'),
            Stat::make('待審新聞站', $stats['pending_domains'])
                ->description($stats['active_domains'] . ' 個啟用網域'),
        ];
    }
}
