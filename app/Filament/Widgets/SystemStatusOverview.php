<?php

namespace App\Filament\Widgets;

use App\Models\NewsDomain;
use App\Models\CommunityTask;
use App\Models\NewsDomainReport;
use App\Models\NewsChangeReport;
use App\Models\NewsUrl;
use App\Models\AbuseEvent;
use App\Models\Appeal;
use App\Models\BugReport;
use App\Models\EvidenceReport;
use App\Models\User;
use App\Models\UserDataRequest;
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
                'pending_evidence_reports' => EvidenceReport::query()->where('status', 'pending')->count(),
                'pending_change_reports' => NewsChangeReport::query()->where('status', 'pending')->count(),
                'open_abuse_events' => AbuseEvent::query()->where('reviewed', false)->count(),
                'pending_appeals' => Appeal::query()->where('status', 'pending')->count(),
                'new_bug_reports' => BugReport::query()->whereIn('status', ['new', 'triaged', 'in_progress'])->count(),
                'pending_data_requests' => UserDataRequest::query()->where('status', 'pending')->count(),
                'unavailable_news' => NewsUrl::query()->where('availability_status', 'deleted_or_unavailable')->count(),
                'active_domains' => NewsDomain::query()->where('is_active', true)->count(),
                'community_open_tasks' => CommunityTask::query()->where('status', 'open')->count(),
                'community_escalated_tasks' => CommunityTask::query()->where('status', 'escalated')->count(),
            ],
        );

        return [
            Stat::make('使用者', $stats['users'])
                ->description('已註冊身份'),
            Stat::make('追蹤新聞', $stats['news_urls'])
                ->description('已正規化 URL 指紋'),
            Stat::make('投票', $stats['votes'])
                ->description('加權使用者判斷'),
            Stat::make('今日待辦', $stats['pending_domains'] + $stats['pending_evidence_reports'] + $stats['pending_change_reports'] + $stats['open_abuse_events'] + $stats['pending_appeals'] + $stats['new_bug_reports'])
                ->description("站點 {$stats['pending_domains']} / 證據 {$stats['pending_evidence_reports']} / 改稿 {$stats['pending_change_reports']} / 濫用 {$stats['open_abuse_events']} / 申訴 {$stats['pending_appeals']} / Bug {$stats['new_bug_reports']}")
                ->color('warning'),
            Stat::make('待審新聞站', $stats['pending_domains'])
                ->description($stats['active_domains'] . ' 個啟用網域')
                ->color('info'),
            Stat::make('不可用新聞', $stats['unavailable_news'])
                ->description('刪文或無法存取標記')
                ->color($stats['unavailable_news'] > 0 ? 'danger' : 'success'),
            Stat::make('資料權利請求', $stats['pending_data_requests'])
                ->description('待管理員處理')
                ->color('gray'),
            Stat::make('社群自治任務', $stats['community_open_tasks'])
                ->description("人工升級 {$stats['community_escalated_tasks']} 件")
                ->color($stats['community_escalated_tasks'] > 0 ? 'warning' : 'success'),
        ];
    }
}
