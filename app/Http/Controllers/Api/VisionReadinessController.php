<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AbuseEvent;
use App\Models\Appeal;
use App\Models\EvidenceReport;
use App\Models\ExtensionEvent;
use App\Models\NewsChangeReport;
use App\Models\NewsDomainReport;
use App\Models\NewsUrl;
use App\Models\Tag;
use App\Models\TrustedSourceSuggestion;
use App\Models\UrlClassificationReport;
use App\Models\UserDataRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class VisionReadinessController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json(Cache::store(config('truthshield.status_cache_store'))->remember(
            'vision:readiness:v2',
            now()->addSeconds(60),
            fn () => [
                'summary' => [
                    'name' => 'TruthShield 願景上線準備中心',
                    'local_feature_points' => count($this->featurePoints()),
                    'local_next_points' => count(config('truthshield_readiness.local_next_points', [])),
                    'local_completed_polish_points' => count(config('truthshield_readiness.local_completed_polish_points', [])),
                    'external_launch_dependencies' => count($this->launchDependencies()),
                    'completed_local_points' => collect($this->featurePoints())->where('status', 'implemented')->count(),
                    'generated_at' => now()->toJSON(),
                ],
                'categories' => $this->categories(),
                'feature_points' => $this->featurePoints(),
                'local_next_points' => config('truthshield_readiness.local_next_points', []),
                'local_completed_polish_points' => config('truthshield_readiness.local_completed_polish_points', []),
                'journalism_taxonomy' => $this->journalismTaxonomy(),
                'evidence_rubric' => $this->evidenceRubric(),
                'participation_loops' => $this->participationLoops(),
                'operational_playbooks' => $this->operationalPlaybooks(),
                'production_checklist' => config('truthshield_readiness.production_checklist', []),
                'security_report_flow' => config('truthshield_readiness.security_report_flow', []),
                'live_pressure' => $this->livePressure(),
                'launch_dependencies' => $this->launchDependencies(),
            ],
        ));
    }

    private function categories(): array
    {
        return [
            ['key' => 'identity', 'name' => '身份與信用', 'intent' => '讓真實、長期、有貢獻的使用者權重更高。'],
            ['key' => 'voting', 'name' => '投票與證據閉環', 'intent' => '確保看過新聞、附證據、受信用權重影響。'],
            ['key' => 'anti_abuse', 'name' => '反操縱', 'intent' => '降低網軍、協同投票與低成本灌水的收益。'],
            ['key' => 'journalism', 'name' => '新聞學標籤', 'intent' => '把評價從情緒反應拉回可討論的新聞倫理準則。'],
            ['key' => 'extension', 'name' => '插件體驗', 'intent' => '把入口放在新聞頁，不干擾閱讀但能即時提醒。'],
            ['key' => 'governance', 'name' => '透明治理', 'intent' => '管理員可介入，但所有處置都要可稽核、可申訴。'],
            ['key' => 'operations', 'name' => '營運可靠性', 'intent' => '讓 hover、投票、快取、queue、匯出可被監控。'],
            ['key' => 'community', 'name' => '社群參與', 'intent' => '用徽章、排行榜、回報與資料主權留住查證者。'],
        ];
    }

    private function featurePoints(): array
    {
        return [
            ['id' => 1, 'category' => 'identity', 'title' => 'Dev login 可被 production 關閉', 'status' => 'implemented'],
            ['id' => 2, 'category' => 'identity', 'title' => 'OAuth provider identity table', 'status' => 'implemented'],
            ['id' => 3, 'category' => 'identity', 'title' => 'Facebook/Google/GitHub provider 介面', 'status' => 'implemented'],
            ['id' => 4, 'category' => 'identity', 'title' => 'identity_level 權重訊號', 'status' => 'implemented'],
            ['id' => 5, 'category' => 'identity', 'title' => 'risk_status 權重限制', 'status' => 'implemented'],
            ['id' => 6, 'category' => 'identity', 'title' => '使用者資料匯出', 'status' => 'implemented'],
            ['id' => 7, 'category' => 'identity', 'title' => '資料權利請求', 'status' => 'implemented'],
            ['id' => 8, 'category' => 'identity', 'title' => 'API clients 可建立與撤銷', 'status' => 'implemented'],

            ['id' => 9, 'category' => 'voting', 'title' => '72 小時投票窗口', 'status' => 'implemented'],
            ['id' => 10, 'category' => 'voting', 'title' => '截止後定案快照', 'status' => 'implemented'],
            ['id' => 11, 'category' => 'voting', 'title' => '一人一篇新聞一筆投票/留言', 'status' => 'implemented'],
            ['id' => 12, 'category' => 'voting', 'title' => '截止前可更新投票與證據', 'status' => 'implemented'],
            ['id' => 13, 'category' => 'voting', 'title' => '負面標籤必填證據 URL', 'status' => 'implemented'],
            ['id' => 14, 'category' => 'voting', 'title' => '負面標籤必填簡短證據說明', 'status' => 'implemented'],
            ['id' => 15, 'category' => 'voting', 'title' => '閱讀秒數門檻', 'status' => 'implemented'],
            ['id' => 16, 'category' => 'voting', 'title' => '信用權重寫入固定 weight_score', 'status' => 'implemented'],
            ['id' => 17, 'category' => 'voting', 'title' => '證據有用/沒幫助評分', 'status' => 'implemented'],
            ['id' => 18, 'category' => 'voting', 'title' => '低信用不得評分證據', 'status' => 'implemented'],
            ['id' => 19, 'category' => 'voting', 'title' => '證據評分截止後凍結', 'status' => 'implemented'],
            ['id' => 20, 'category' => 'voting', 'title' => '新聞頁內嵌投票面板', 'status' => 'implemented'],

            ['id' => 21, 'category' => 'anti_abuse', 'title' => '低信任投票 cap', 'status' => 'implemented'],
            ['id' => 22, 'category' => 'anti_abuse', 'title' => 'abuse multiplier', 'status' => 'implemented'],
            ['id' => 23, 'category' => 'anti_abuse', 'title' => '帳號訊號紀錄', 'status' => 'implemented'],
            ['id' => 24, 'category' => 'anti_abuse', 'title' => '帳號關聯圖摘要', 'status' => 'implemented'],
            ['id' => 25, 'category' => 'anti_abuse', 'title' => '協同濫用事件偵測', 'status' => 'implemented'],
            ['id' => 26, 'category' => 'anti_abuse', 'title' => '濫用事件後台審核', 'status' => 'implemented'],
            ['id' => 27, 'category' => 'anti_abuse', 'title' => '管理員可限制/解除使用者權重', 'status' => 'implemented'],

            ['id' => 28, 'category' => 'journalism', 'title' => '負面新聞學標籤組', 'status' => 'implemented'],
            ['id' => 29, 'category' => 'journalism', 'title' => '正面新聞學標籤組', 'status' => 'implemented'],
            ['id' => 30, 'category' => 'journalism', 'title' => '內容農場標籤', 'status' => 'implemented'],
            ['id' => 31, 'category' => 'journalism', 'title' => '未揭露業配標籤', 'status' => 'implemented'],
            ['id' => 32, 'category' => 'journalism', 'title' => '缺乏平衡報導標籤', 'status' => 'implemented'],
            ['id' => 33, 'category' => 'journalism', 'title' => '標籤證據需求準則', 'status' => 'implemented'],
            ['id' => 34, 'category' => 'journalism', 'title' => '證據可信來源/雲端硬碟支援', 'status' => 'implemented'],
            ['id' => 35, 'category' => 'journalism', 'title' => '證據品質排序', 'status' => 'implemented'],

            ['id' => 36, 'category' => 'extension', 'title' => 'tooltip 僅顯示標籤結果', 'status' => 'implemented'],
            ['id' => 37, 'category' => 'extension', 'title' => 'tooltip 狀態快取', 'status' => 'implemented'],
            ['id' => 38, 'category' => 'extension', 'title' => '新聞頁頂部橫幅', 'status' => 'implemented'],
            ['id' => 39, 'category' => 'extension', 'title' => '橫幅關閉後本頁不重載', 'status' => 'implemented'],
            ['id' => 40, 'category' => 'extension', 'title' => '右鍵選單開啟投票/回報', 'status' => 'implemented'],
            ['id' => 41, 'category' => 'extension', 'title' => '插件 popup 操作入口', 'status' => 'implemented'],
            ['id' => 42, 'category' => 'extension', 'title' => 'extension coverage dashboard', 'status' => 'implemented'],

            ['id' => 43, 'category' => 'governance', 'title' => '證據檢舉', 'status' => 'implemented'],
            ['id' => 44, 'category' => 'governance', 'title' => '證據 hide/restore', 'status' => 'implemented'],
            ['id' => 45, 'category' => 'governance', 'title' => '申訴流程', 'status' => 'implemented'],
            ['id' => 46, 'category' => 'governance', 'title' => '公開審核紀錄', 'status' => 'implemented'],
            ['id' => 47, 'category' => 'governance', 'title' => '後台待辦工作台', 'status' => 'implemented'],
            ['id' => 48, 'category' => 'governance', 'title' => '未收錄新聞站回報', 'status' => 'implemented'],

            ['id' => 49, 'category' => 'operations', 'title' => 'system health endpoint', 'status' => 'implemented'],
            ['id' => 50, 'category' => 'operations', 'title' => '透明儀表板', 'status' => 'implemented'],
            ['id' => 51, 'category' => 'operations', 'title' => 'CSV 營運匯出', 'status' => 'implemented'],
            ['id' => 52, 'category' => 'operations', 'title' => 'OpenAPI / API docs', 'status' => 'implemented'],

            ['id' => 53, 'category' => 'community', 'title' => '徽章與成就牆', 'status' => 'implemented'],
            ['id' => 54, 'category' => 'community', 'title' => '捐款頁與營運支持', 'status' => 'implemented'],
            ['id' => 55, 'category' => 'community', 'title' => '願景上線準備中心', 'status' => 'implemented'],
        ];
    }

    private function journalismTaxonomy(): array
    {
        return [
            'negative' => Tag::query()
                ->whereIn('severity', ['high', 'medium', 'low'])
                ->orderByRaw("case severity when 'high' then 1 when 'medium' then 2 else 3 end")
                ->orderBy('name')
                ->get(['name', 'slug', 'severity', 'color', 'requires_evidence', 'description']),
            'positive' => Tag::query()
                ->where('severity', 'positive')
                ->orderBy('name')
                ->get(['name', 'slug', 'severity', 'color', 'requires_evidence', 'description']),
        ];
    }

    private function evidenceRubric(): array
    {
        return [
            ['score' => '+2', 'name' => '第一手或原始資料', 'description' => '官方文件、完整逐字稿、原始數據、現場截圖或可驗證的 archive。'],
            ['score' => '+1', 'name' => '可信第三方佐證', 'description' => '不同媒體、查核組織、學術/政府資料或可追溯來源。'],
            ['score' => '0', 'name' => '只能補充脈絡', 'description' => '相關但不能直接證明標籤，仍可供讀者參考。'],
            ['score' => '-1', 'name' => '來源不明或無法讀取', 'description' => '連結失效、雲端權限未公開、截圖缺少上下文。'],
            ['score' => '-2', 'name' => '疑似偽造或惡意誤導', 'description' => '內容被竄改、冒用來源、與主張無關卻誤導讀者。'],
        ];
    }

    private function participationLoops(): array
    {
        return [
            ['role' => '一般讀者', 'loop' => '看橫幅與 tooltip，進入新聞頁閱讀後決定是否投票。'],
            ['role' => '查證者', 'loop' => '提交證據、補充說明、評分其他證據，累積徽章與信任。'],
            ['role' => '社群維護者', 'loop' => '回報未收錄新聞站、分類頁、可信證據來源。'],
            ['role' => '管理員', 'loop' => '審核檢舉、申訴、濫用事件、domain 與 trusted source。'],
            ['role' => '研究者', 'loop' => '透過 API client、CSV 匯出與透明頁檢查平台資料。'],
        ];
    }

    private function operationalPlaybooks(): array
    {
        return [
            ['trigger' => '大量同向投票', 'action' => '查看 abuse events、account graph、低閱讀秒數與重複 evidence URL。'],
            ['trigger' => '新聞被刪除或修改', 'action' => '查看 snapshots、change reports、archive URL 與社群證據。'],
            ['trigger' => '插件不顯示', 'action' => '查看 extension coverage、selector checks、domain reports 與 popup 設定。'],
            ['trigger' => '證據爭議', 'action' => '檢查 evidence reports、有用/沒幫助權重、來源可信標記與申訴。'],
            ['trigger' => '上線前檢查', 'action' => '跑 production env check、測試、build、extension package、備份腳本與 health endpoint。'],
        ];
    }

    private function livePressure(): array
    {
        return [
            'pending_governance' => EvidenceReport::query()->where('status', 'pending')->count()
                + NewsChangeReport::query()->where('status', 'pending')->count()
                + Appeal::query()->where('status', 'pending')->count()
                + NewsDomainReport::query()->where('status', 'pending')->count()
                + UrlClassificationReport::query()->where('status', 'pending')->count()
                + TrustedSourceSuggestion::query()->where('status', 'pending')->count()
                + UserDataRequest::query()->where('status', 'pending')->count(),
            'open_abuse_events' => AbuseEvent::query()->where('reviewed', false)->count(),
            'unfinalized_expired_news' => NewsUrl::query()
                ->whereNotNull('voting_closes_at')
                ->where('voting_closes_at', '<=', now())
                ->whereNull('finalized_at')
                ->count(),
            'extension_failures_24h' => ExtensionEvent::query()
                ->where('success', false)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'governance_pressure_score' => $this->governancePressureScore(),
        ];
    }

    private function governancePressureScore(): int
    {
        $pressure = EvidenceReport::query()->where('status', 'pending')->count()
            + NewsChangeReport::query()->where('status', 'pending')->count()
            + Appeal::query()->where('status', 'pending')->count()
            + AbuseEvent::query()->where('reviewed', false)->count() * 2
            + NewsDomainReport::query()->where('status', 'pending')->count();

        return min(100, $pressure * 10);
    }

    private function launchDependencies(): array
    {
        return config('truthshield_readiness.external_launch_dependencies', []);
    }
}
