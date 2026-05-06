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
            Stat::make('Users', User::query()->count())
                ->description('registered identities'),
            Stat::make('Tracked URLs', NewsUrl::query()->count())
                ->description('normalized news fingerprints'),
            Stat::make('Votes', Vote::query()->count())
                ->description('weighted user judgments'),
            Stat::make('Pending Domains', NewsDomainReport::query()->where('status', 'pending')->count())
                ->description(NewsDomain::query()->where('is_active', true)->count() . ' active domains'),
        ];
    }
}
