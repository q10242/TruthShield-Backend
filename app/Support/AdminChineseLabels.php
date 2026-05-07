<?php

namespace App\Support;

use Illuminate\Support\Str;

class AdminChineseLabels
{
    private const RESOURCE_DESCRIPTIONS = [
        'AbuseClusterResource' => '管理被系統分群出的協同行為事件。這裡會影響網軍偵測、風險研判與後續帳號限權處理。',
        'AbuseEventResource' => '管理單筆濫用或可疑行為事件。審核結果可能影響帳號風險狀態、權重倍率與公開治理紀錄。建議流程：先看事件類型與關聯帳號，再比對投票時間與證據重複度，最後才限權。',
        'AccountEdgeResource' => '查看帳號之間的風險關聯摘要。這些資料用於反操縱分析，不應被當成單獨封鎖依據。',
        'AccountSignalResource' => '查看使用者行為訊號，例如投票、閱讀、證據評分與裝置摘要。這會支撐風險模型與帳號關聯圖。',
        'AlgorithmVersionResource' => '管理演算法版本與啟用狀態。定案結果會保存版本，任何變更都會影響透明說明與可稽核性。',
        'ApiClientResource' => '管理研究或資料整合用 API client。啟用或撤銷會影響外部系統可存取的資料範圍。',
        'AppealResource' => '管理使用者申訴。審核結果會影響證據可見性、帳號限制、信用調整與通知。',
        'AuditLogResource' => '查看後台與重要 API 操作紀錄。這是追蹤管理行為與事後稽核的主要資料。',
        'BadgeResource' => '管理徽章與成就。這會影響使用者 Profile 展示與參與誘因，不應頻繁改動條件。',
        'CommunityTaskResource' => '管理社群自治任務。任務狀態會影響使用者協助維護新聞站、證據品質與可信來源的入口。',
        'DonationResource' => '管理捐款訂單與付款狀態。資料會用於公開資金透明頁與營運匯出。',
        'EvidenceReactionResource' => '管理使用者對證據的有用／沒幫助評分。這會影響證據排序、品質分與未來信用結算。',
        'EvidenceReportResource' => '管理證據檢舉。處理結果可能隱藏或恢復證據，並會通知作者與留下治理紀錄。建議流程：先確認檢舉理由與證據原文，再選擇隱藏、恢復或要求補件。',
        'EvidenceResource' => '管理從投票拆出的證據資料。隱藏、品質分與快照狀態會影響證據庫與新聞頁排序。',
        'ExtensionEventResource' => '查看插件遙測事件。這用於判斷 tooltip、橫幅、右鍵入口與不同新聞站的相容性。',
        'ExtensionSelectorCheckResource' => '管理插件 selector 檢查結果。失敗資料會指引新聞站規則修正與覆蓋率改善。',
        'MediaOutletResource' => '管理媒體實體與網域彙整。這會影響媒體排行榜與新聞歸屬。',
        'ModerationEventResource' => '查看公開治理紀錄摘要。這些紀錄會出現在透明頁，請避免放入私人資料。',
        'NewsChangeReportResource' => '管理新聞被刪除、修改或無法存取的回報。審核結果會影響新聞快照與前台警示。',
        'NewsDomainReportResource' => '管理使用者回報的未收錄新聞站。審核後可轉為插件監控網域或社群任務。',
        'NewsDomainResource' => '管理新聞站網域與頁面規則。這直接影響插件是否顯示 tooltip、橫幅與投票入口。',
        'NewsUrlResource' => '管理已收錄新聞 URL。投票截止、定案快照與 URL 指紋會影響所有結果頁與 API 回應。',
        'NewsUrlSnapshotResource' => '管理新聞中繼資料快照與變更紀錄。這用於處理新聞修改、刪除與存證情境。',
        'OauthLoginStateResource' => '管理 OAuth 登入 state。這是登入安全資料，通常只用於除錯與異常排查。',
        'OfficialResponseResource' => '管理官方、本人或媒體澄清。發布後會在新聞頁高可見度顯示，但不直接改變投票結果。建議流程：確認申請者身份、澄清內容與補充連結，再發布或退回。',
        'OperationalEventResource' => '查看營運事件與系統異常。這會支撐 health/readiness 判斷與上線前風險排查。',
        'RateLimitPolicyResource' => '管理公開限流政策摘要。這會影響高流量 hover、投票、檢舉與登入保護策略。',
        'ReadSessionResource' => '查看新聞閱讀紀錄摘要。這用於確認使用者是否閱讀後投票，避免未讀灌票。',
        'SystemSettingResource' => '管理系統設定與自治門檻。錯誤設定可能影響演算法、社群自動化與公開頁統計。',
        'TagResource' => '管理新聞標籤。標籤會直接影響投票選項、tooltip 顯示與排行榜統計。',
        'TrustScoreHistoryResource' => '查看信用分歷史。這是解釋使用者權重變化、申訴與結算結果的重要依據。',
        'TrustedEvidenceSourceResource' => '管理可信證據來源，例如雲端硬碟、圖床、封存服務與查核機構。這會影響證據安全性與品質加權。',
        'TrustedSourceSuggestionResource' => '管理使用者提議的可信來源。通過後會擴充可接受的證據來源與信任加成。',
        'UrlClassificationReportResource' => '管理使用者回報的 URL 類型，例如新聞頁、分類頁或首頁。這會影響插件是否注入橫幅。',
        'UserDataRequestResource' => '管理使用者資料權利請求。這涉及隱私、刪除、更正與資料匯出處理。',
        'UserIdentityResource' => '管理使用者綁定的第三方身份。這會影響身份等級、登入追蹤與未來 OAuth 替換。',
        'UserNotificationResource' => '管理站內通知。這會影響使用者是否得知審核、申訴、證據或系統狀態。',
        'UserResource' => '管理使用者、公開暱稱、身份等級、風險狀態與後台權限。變更會直接影響投票權重與管理能力。建議流程：信用或限權調整必須有明確原因，避免把爭議意見當成濫用。',
        'VerifiedClaimantResource' => '管理作者、媒體、當事人或機構代表身份申請。通過後使用者可提交官方澄清。建議流程：確認證明連結、網域或組織關聯，通過時填寫可公開身份標籤。',
        'VoteResource' => '管理使用者對新聞的投票與證據留言。這是加權結果、證據庫與信用結算的核心資料。',
    ];

    /**
     * Chinese labels shared by Filament forms and tables.
     *
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'action' => '操作',
        'action_url' => '操作連結',
        'activated_at' => '啟用時間',
        'abuse_multiplier' => '濫用風險倍率',
        'algorithm_version' => '演算法版本',
        'article_selector' => '文章選擇器',
        'auth_provider' => '登入來源',
        'blocked_path_pattern' => '封鎖路徑規則',
        'body' => '內容',
        'check_type' => '檢查類型',
        'checked_at' => '檢查時間',
        'color' => '顏色',
        'content_selector' => '內文選擇器',
        'created_at' => '建立時間',
        'decay_seconds' => '衰退秒數',
        'delta' => '分數變動',
        'description' => '描述',
        'details' => '細節',
        'display_name' => '顯示名稱',
        'domain' => '網域',
        'edge_type' => '關聯類型',
        'email' => '電子郵件',
        'email_verified_at' => '信箱驗證時間',
        'event_type' => '事件類型',
        'evidence_host' => '證據網域',
        'evidence_note' => '證據說明',
        'evidence_safety' => '證據安全性',
        'evidence_type' => '證據類型',
        'evidence_url' => '證據連結',
        'expires_at' => '到期時間',
        'fb_id' => 'Facebook ID',
        'final_evidence_payload' => '定案證據快照',
        'final_status_payload' => '定案狀態快照',
        'finalized_at' => '定案時間',
        'hash' => 'URL 指紋',
        'helpful' => '有幫助',
        'host' => '來源網域',
        'identity_level' => '身份等級',
        'identity_multiplier' => '身份倍率',
        'ip_address' => 'IP 位址',
        'is_active' => '啟用',
        'is_admin' => '後台權限',
        'key' => '設定鍵',
        'last_reported_at' => '最後回報時間',
        'last_used_at' => '最後使用時間',
        'low_trust_multiplier' => '低信任倍率',
        'max_attempts' => '最大次數',
        'metadata' => '中繼資料',
        'name' => '名稱',
        'new_score' => '新信任分數',
        'news_url_id' => '新聞',
        'normalized_url' => '正規化 URL',
        'note' => '備註',
        'notes' => '備註',
        'original_url' => '原始 URL',
        'page_title' => '頁面標題',
        'password' => '密碼',
        'previous_score' => '原信任分數',
        'priority' => '優先序',
        'provider' => '登入提供者',
        'provider_user_id' => '提供者使用者 ID',
        'public_reason' => '公開原因',
        'quality_score' => '證據品質分',
        'read_at' => '已讀時間',
        'reason' => '原因',
        'report_count' => '回報數',
        'review_note' => '審核備註',
        'reviewed' => '已審核',
        'risk_status' => '風險狀態',
        'scope' => '範圍',
        'score' => '分數',
        'selector' => '選擇器',
        'severity' => '嚴重度',
        'signal_hash' => '訊號雜湊',
        'signal_type' => '訊號類型',
        'slug' => '代稱',
        'snapshot_status' => '快照狀態',
        'source' => '來源',
        'source_type' => '來源類型',
        'source_user_id' => '來源使用者',
        'status' => '狀態',
        'subject_id' => '目標 ID',
        'subject_type' => '目標類型',
        'summary' => '摘要',
        'tag_id' => '標籤',
        'target_user_id' => '目標使用者',
        'title' => '標題',
        'title_selector' => '標題選擇器',
        'title_snapshot' => '標題快照',
        'trust_bonus' => '信任加成',
        'trust_score' => '信任分數',
        'type' => '類型',
        'updated_at' => '更新時間',
        'url' => '網址',
        'used_at' => '使用時間',
        'user_id' => '使用者',
        'version' => '版本',
        'vote_id' => '投票',
        'votes_count' => '投票數',
        'voting_closes_at' => '投票截止時間',
        'weight_score' => '加權分數',
    ];

    public static function field(string $name): string
    {
        $key = Str::of($name)->afterLast('.')->snake()->toString();

        return self::FIELD_LABELS[$key] ?? Str::of($key)->replace('_', ' ')->title()->toString();
    }

    public static function resourceDescription(?string $resourceClass): ?string
    {
        if (! $resourceClass) {
            return null;
        }

        return self::RESOURCE_DESCRIPTIONS[class_basename($resourceClass)] ?? null;
    }
}
