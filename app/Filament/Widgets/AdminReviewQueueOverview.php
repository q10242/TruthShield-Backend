<?php

namespace App\Filament\Widgets;

use App\Models\AbuseEvent;
use App\Models\Appeal;
use App\Models\BugReport;
use App\Models\CommunityTask;
use App\Models\EvidenceReport;
use App\Models\NewsChangeReport;
use App\Models\NewsDomainReport;
use App\Models\OfficialResponse;
use App\Models\TrustedSourceSuggestion;
use App\Models\UrlClassificationReport;
use App\Models\VerifiedClaimant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminReviewQueueOverview extends BaseWidget
{
    protected static ?string $heading = '今日待處理工作台';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->queueQuery())
            ->columns([
                Tables\Columns\TextColumn::make('queue_label')->label('類型')->badge(),
                Tables\Columns\TextColumn::make('title')->label('事項')->searchable()->limit(80),
                Tables\Columns\TextColumn::make('count')->label('數量')->sortable(),
                Tables\Columns\TextColumn::make('priority')->label('優先級')->badge(),
                Tables\Columns\TextColumn::make('admin_url')
                    ->label('後台路徑')
                    ->copyable()
                    ->url(fn ($record) => $record->admin_url)
                    ->openUrlInNewTab()
                    ->limit(60),
            ])
            ->paginated(false);
    }

    private function queueQuery(): Builder
    {
        $rows = collect([
            ['queue_label' => '新聞站回報', 'title' => '待審未收錄新聞站', 'count' => NewsDomainReport::query()->where('status', 'pending')->count(), 'priority' => 'high', 'admin_url' => '/admin/news-domain-reports'],
            ['queue_label' => '證據檢舉', 'title' => '待審證據檢舉', 'count' => EvidenceReport::query()->where('status', 'pending')->count(), 'priority' => 'high', 'admin_url' => '/admin/evidence-reports'],
            ['queue_label' => '改稿/刪文', 'title' => '待審新聞變更回報', 'count' => NewsChangeReport::query()->where('status', 'pending')->count(), 'priority' => 'high', 'admin_url' => '/admin/news-change-reports'],
            ['queue_label' => '濫用事件', 'title' => '待處理反操縱事件', 'count' => AbuseEvent::query()->where('reviewed', false)->count(), 'priority' => 'critical', 'admin_url' => '/admin/abuse-events'],
            ['queue_label' => '申訴', 'title' => '待審使用者申訴', 'count' => Appeal::query()->where('status', 'pending')->count(), 'priority' => 'medium', 'admin_url' => '/admin/appeals'],
            ['queue_label' => 'Bug / 安全', 'title' => '待分類 Bug 與安全回報', 'count' => BugReport::query()->whereIn('status', ['new', 'triaged', 'in_progress'])->count(), 'priority' => 'high', 'admin_url' => '/admin/bug-reports'],
            ['queue_label' => 'URL 分類', 'title' => '待審文章/分類頁回報', 'count' => UrlClassificationReport::query()->where('status', 'pending')->count(), 'priority' => 'medium', 'admin_url' => '/admin/url-classification-reports'],
            ['queue_label' => '可信來源', 'title' => '待審可信證據來源建議', 'count' => TrustedSourceSuggestion::query()->where('status', 'pending')->count(), 'priority' => 'medium', 'admin_url' => '/admin/trusted-source-suggestions'],
            ['queue_label' => '社群自治', 'title' => '升級人工處理的社群任務', 'count' => CommunityTask::query()->where('status', 'escalated')->count(), 'priority' => 'high', 'admin_url' => '/admin/community-tasks'],
            ['queue_label' => '身份澄清', 'title' => '待審作者/媒體/當事人身份申請', 'count' => VerifiedClaimant::query()->where('status', 'pending')->count(), 'priority' => 'high', 'admin_url' => '/admin/verified-claimants'],
            ['queue_label' => '官方澄清', 'title' => '待審官方/本人澄清內容', 'count' => OfficialResponse::query()->where('status', 'pending')->count(), 'priority' => 'high', 'admin_url' => '/admin/official-responses'],
        ])->filter(fn ($row) => $row['count'] > 0)->values();

        if ($rows->isEmpty()) {
            $rows = collect([['queue_label' => '完成', 'title' => '目前沒有待處理事項', 'count' => 0, 'priority' => 'normal', 'admin_url' => '/admin']]);
        }

        $table = collect($rows)->map(function ($row, $index) {
            return 'select ' . ($index + 1) . ' as id, ' .
                "'" . Str::replace("'", "''", $row['queue_label']) . "' as queue_label, " .
                "'" . Str::replace("'", "''", $row['title']) . "' as title, " .
                (int) $row['count'] . ' as count, ' .
                "'" . Str::replace("'", "''", $row['priority']) . "' as priority, " .
                "'" . Str::replace("'", "''", $row['admin_url']) . "' as admin_url";
        })->implode(' union all ');

        return AdminReviewQueueRow::query()->from(DB::raw("({$table}) as admin_review_queue_rows"));
    }
}

class AdminReviewQueueRow extends Model
{
    public $timestamps = false;

    protected $guarded = [];
}
