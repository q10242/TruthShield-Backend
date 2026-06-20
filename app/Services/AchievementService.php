<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\CommunitySignal;
use App\Models\Donation;
use App\Models\EvidenceReaction;
use App\Models\NewsChangeReport;
use App\Models\NewsUrlSnapshot;
use App\Models\User;

class AchievementService
{
    public function sync(User $user): array
    {
        $stats = $this->statsFor($user);
        $unlocked = [];

        foreach ($this->definitions() as $definition) {
            $badge = Badge::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'color' => $definition['color'],
                ],
            );

            if ($stats[$definition['metric']] >= $definition['target']) {
                $alreadyUnlocked = $user->badges()
                    ->where('badges.id', $badge->id)
                    ->exists();

                $user->badges()->syncWithoutDetaching([
                    $badge->id => ['reason' => $definition['reason']],
                ]);

                if (! $alreadyUnlocked) {
                    $unlocked[] = $definition['slug'];
                }
            }
        }

        return [
            'stats' => $stats,
            'unlocked' => $unlocked,
        ];
    }

    public function titleFor(User $user, array $stats): array
    {
        if ((float) $user->trust_score >= 3.0 || $stats['badges'] >= 12) {
            return [
                'name' => '真相護盾手',
                'description' => '高信用查證者，具備穩定的投票與證據貢獻。',
            ];
        }

        if ($stats['helpful_evidence_received'] >= 10) {
            return [
                'name' => '證據策展者',
                'description' => '提交的證據多次被社群評為有用。',
            ];
        }

        if ($stats['votes'] >= 10) {
            return [
                'name' => '穩定查證者',
                'description' => '持續閱讀新聞並留下加權判斷。',
            ];
        }

        if ($stats['evidence_votes'] >= 1) {
            return [
                'name' => '證據提供者',
                'description' => '已開始用外部證據支撐自己的判斷。',
            ];
        }

        if ($stats['votes'] >= 1) {
            return [
                'name' => '新進查證者',
                'description' => '已完成第一筆新聞評分。',
            ];
        }

        return [
            'name' => '觀察者',
            'description' => '可以先看結果與證據，閱讀後再參與投票。',
        ];
    }

    public function achievementsFor(User $user): array
    {
        $stats = $this->statsFor($user);
        $badges = $user->badges()->pluck('slug')->all();

        return collect($this->definitions())
            ->map(fn (array $definition): array => [
                'slug' => $definition['slug'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'color' => $definition['color'],
                'metric' => $definition['metric'],
                'target' => $definition['target'],
                'current' => min($stats[$definition['metric']] ?? 0, $definition['target']),
                'percentage' => $definition['target'] > 0
                    ? min(100, (int) floor((($stats[$definition['metric']] ?? 0) / $definition['target']) * 100))
                    : 100,
                'unlocked' => in_array($definition['slug'], $badges, true),
            ])
            ->values()
            ->all();
    }

    public function communityRolesFor(User $user, array $stats): array
    {
        $roles = [];
        $trustScore = (float) $user->trust_score;

        if ($trustScore >= 1.5 && $stats['helpful_evidence_received'] >= 5) {
            $roles[] = [
                'key' => 'evidence_curator',
                'name' => '證據整理者',
                'description' => '提交的證據多次被社群評為有用，適合協助整理爭議新聞證據。',
            ];
        }

        if ($trustScore >= 1.2 && $stats['community_signals'] >= 5) {
            $roles[] = [
                'key' => 'domain_steward',
                'name' => '新聞站維護者',
                'description' => '持續協助回報新聞站、URL 分類與可信來源。',
            ];
        }

        if ($trustScore >= 2.0 && ($stats['evidence_reactions'] + $stats['community_signals']) >= 10) {
            $roles[] = [
                'key' => 'abuse_watcher',
                'name' => '濫用觀察員',
                'description' => '可協助辨識低品質證據、異常回報與協同行為。',
            ];
        }

        if ($trustScore >= 3.0 || $stats['badges'] >= 12) {
            $roles[] = [
                'key' => 'trusted_reviewer',
                'name' => '可信審查者',
                'description' => '高信用使用者，社群自治門檻會更重視你的訊號。',
            ];
        }

        return $roles;
    }

    public function statsFor(User $user): array
    {
        return [
            'votes' => $user->votes()->count(),
            'evidence_votes' => $user->votes()->whereNotNull('evidence_url')->count(),
            'evidence_reactions' => $user->evidenceReactions()->count(),
            'helpful_reactions' => $user->evidenceReactions()->where('helpful', true)->count(),
            'helpful_evidence_received' => EvidenceReaction::query()
                ->where('helpful', true)
                ->whereHas('vote', fn ($query) => $query->where('user_id', $user->id))
                ->count(),
            'community_signals' => CommunitySignal::query()->where('user_id', $user->id)->count(),
            'accepted_community_signals' => CommunitySignal::query()
                ->where('user_id', $user->id)
                ->whereHas('user')
                ->whereIn('signal_type', ['domain_report', 'url_classification', 'trusted_source'])
                ->where('weight_score', '>=', config('truthshield_community.trust_floor', 1.0))
                ->count(),
            'read_sessions' => $user->readSessions()->count(),
            'trust_history_entries' => $user->trustScoreHistories()->count(),
            'paid_donations' => Donation::query()->where('user_id', $user->id)->where('status', Donation::STATUS_PAID)->count(),
            'snapshot_reports' => NewsChangeReport::query()->where('user_id', $user->id)->count(),
            'snapshots_guarded' => NewsUrlSnapshot::query()
                ->whereHas('newsUrl.votes', fn ($query) => $query->where('user_id', $user->id))
                ->count(),
            'badges' => $user->badges()->count(),
            'comments_created' => Comment::query()
                ->where('user_id', $user->id)
                ->whereNull('parent_id')
                ->whereNull('hidden_at')
                ->count(),
            'comments_helpful_received' => CommentReaction::query()
                ->where('helpful', true)
                ->whereHas('comment', fn ($q) => $q->where('user_id', $user->id)->whereNull('hidden_at'))
                ->count(),
        ];
    }

    public function definitions(): array
    {
        return collect([
            [
                'metric' => 'votes',
                'color' => '#67e8f9',
                'tiers' => [
                    ['slug' => 'first-shield', 'name' => '第一面盾', 'target' => 1, 'description' => '完成第一筆新聞投票。'],
                    ['slug' => 'early-verifier', 'name' => '早期查證者', 'target' => 3, 'description' => '完成 3 筆新聞查證。'],
                    ['slug' => 'steady-reviewer', 'name' => '穩定查證者', 'target' => 10, 'description' => '完成 10 筆新聞查證。'],
                    ['slug' => 'senior-reviewer', 'name' => '資深查證者', 'target' => 25, 'description' => '完成 25 筆新聞查證。'],
                    ['slug' => 'shield-veteran', 'name' => '護盾老手', 'target' => 50, 'description' => '完成 50 筆新聞查證。'],
                    ['slug' => 'truth-sentinel', 'name' => '真相哨兵', 'target' => 100, 'description' => '完成 100 筆新聞查證。'],
                    ['slug' => 'truth-ranger', 'name' => '真相巡守', 'target' => 250, 'description' => '完成 250 筆新聞查證。'],
                    ['slug' => 'shield-captain', 'name' => '護盾隊長', 'target' => 500, 'description' => '完成 500 筆新聞查證。'],
                    ['slug' => 'public-record-guardian', 'name' => '公共紀錄守護者', 'target' => 1000, 'description' => '完成 1,000 筆新聞查證。'],
                    ['slug' => 'civic-truth-keeper', 'name' => '公民真相守門人', 'target' => 2500, 'description' => '完成 2,500 筆新聞查證。'],
                    ['slug' => 'truth-archive-champion', 'name' => '真相檔案冠軍', 'target' => 5000, 'description' => '完成 5,000 筆新聞查證。'],
                    ['slug' => 'ten-thousand-shields', 'name' => '萬盾查證者', 'target' => 10000, 'description' => '完成 10,000 筆新聞查證。'],
                ],
            ],
            [
                'metric' => 'evidence_votes',
                'color' => '#86efac',
                'tiers' => [
                    ['slug' => 'evidence-supplier', 'name' => '證據提供者', 'target' => 1, 'description' => '提交第一筆外部證據。'],
                    ['slug' => 'evidence-builder', 'name' => '證據建築師', 'target' => 5, 'description' => '提交 5 筆外部證據。'],
                    ['slug' => 'evidence-archivist', 'name' => '證據典藏員', 'target' => 15, 'description' => '提交 15 筆外部證據。'],
                    ['slug' => 'evidence-vaultkeeper', 'name' => '證據庫守門員', 'target' => 30, 'description' => '提交 30 筆外部證據。'],
                    ['slug' => 'source-hunter', 'name' => '來源獵手', 'target' => 75, 'description' => '提交 75 筆外部證據。'],
                    ['slug' => 'source-cartographer', 'name' => '來源地圖師', 'target' => 150, 'description' => '提交 150 筆外部證據。'],
                    ['slug' => 'evidence-forge-master', 'name' => '證據鍛造師', 'target' => 300, 'description' => '提交 300 筆外部證據。'],
                    ['slug' => 'evidence-library-founder', 'name' => '證據圖書館建立者', 'target' => 750, 'description' => '提交 750 筆外部證據。'],
                    ['slug' => 'evidence-networker', 'name' => '證據網絡者', 'target' => 1500, 'description' => '提交 1,500 筆外部證據。'],
                    ['slug' => 'source-constellation', 'name' => '來源星圖師', 'target' => 3000, 'description' => '提交 3,000 筆外部證據。'],
                    ['slug' => 'evidence-citadel', 'name' => '證據城塞', 'target' => 6000, 'description' => '提交 6,000 筆外部證據。'],
                    ['slug' => 'ten-thousand-sources', 'name' => '萬源證據者', 'target' => 10000, 'description' => '提交 10,000 筆外部證據。'],
                ],
            ],
            [
                'metric' => 'helpful_evidence_received',
                'color' => '#22c55e',
                'tiers' => [
                    ['slug' => 'evidence-curator', 'name' => '證據策展者', 'target' => 5, 'description' => '提交的證據累積 5 次有用評分。'],
                    ['slug' => 'trusted-evidence-curator', 'name' => '可信證據策展者', 'target' => 15, 'description' => '提交的證據累積 15 次有用評分。'],
                    ['slug' => 'evidence-mentor', 'name' => '證據導師', 'target' => 30, 'description' => '提交的證據累積 30 次有用評分。'],
                    ['slug' => 'master-curator', 'name' => '首席證據策展者', 'target' => 60, 'description' => '提交的證據累積 60 次有用評分。'],
                    ['slug' => 'community-trusted-curator', 'name' => '社群信任策展者', 'target' => 150, 'description' => '提交的證據累積 150 次有用評分。'],
                    ['slug' => 'evidence-standard-bearer', 'name' => '證據標準旗手', 'target' => 300, 'description' => '提交的證據累積 300 次有用評分。'],
                    ['slug' => 'evidence-authority', 'name' => '證據權威', 'target' => 750, 'description' => '提交的證據累積 750 次有用評分。'],
                    ['slug' => 'public-evidence-anchor', 'name' => '公共證據錨點', 'target' => 1500, 'description' => '提交的證據累積 1,500 次有用評分。'],
                    ['slug' => 'civic-evidence-pillar', 'name' => '公民證據支柱', 'target' => 3000, 'description' => '提交的證據累積 3,000 次有用評分。'],
                    ['slug' => 'evidence-lighthouse', 'name' => '證據燈塔', 'target' => 6000, 'description' => '提交的證據累積 6,000 次有用評分。'],
                    ['slug' => 'ten-thousand-helpful-evidence', 'name' => '萬評證據策展者', 'target' => 10000, 'description' => '提交的證據累積 10,000 次有用評分。'],
                ],
            ],
            [
                'metric' => 'evidence_reactions',
                'color' => '#c4b5fd',
                'tiers' => [
                    ['slug' => 'evidence-rater', 'name' => '證據審閱員', 'target' => 5, 'description' => '完成 5 次證據有用/沒幫助評分。'],
                    ['slug' => 'evidence-auditor', 'name' => '證據稽核員', 'target' => 20, 'description' => '完成 20 次證據評分。'],
                    ['slug' => 'evidence-referee', 'name' => '證據裁判', 'target' => 50, 'description' => '完成 50 次證據評分。'],
                    ['slug' => 'evidence-judge', 'name' => '證據判讀官', 'target' => 100, 'description' => '完成 100 次證據評分。'],
                    ['slug' => 'evidence-panelist', 'name' => '證據評議員', 'target' => 250, 'description' => '完成 250 次證據評分。'],
                    ['slug' => 'evidence-tribunal', 'name' => '證據審議官', 'target' => 500, 'description' => '完成 500 次證據評分。'],
                    ['slug' => 'evidence-quality-master', 'name' => '證據品質大師', 'target' => 1000, 'description' => '完成 1,000 次證據評分。'],
                    ['slug' => 'evidence-signal-commander', 'name' => '證據訊號指揮官', 'target' => 2500, 'description' => '完成 2,500 次證據評分。'],
                    ['slug' => 'evidence-judgment-legend', 'name' => '證據判讀傳奇', 'target' => 5000, 'description' => '完成 5,000 次證據評分。'],
                    ['slug' => 'ten-thousand-evidence-ratings', 'name' => '萬評證據判讀官', 'target' => 10000, 'description' => '完成 10,000 次證據評分。'],
                ],
            ],
            [
                'metric' => 'helpful_reactions',
                'color' => '#a7f3d0',
                'tiers' => [
                    ['slug' => 'useful-evidence-finder', 'name' => '有用證據雷達', 'target' => 3, 'description' => '標記 3 次有用證據。'],
                    ['slug' => 'quality-reviewer', 'name' => '品質審閱者', 'target' => 10, 'description' => '標記 10 次有用證據。'],
                    ['slug' => 'quality-signal-keeper', 'name' => '品質訊號守門員', 'target' => 30, 'description' => '標記 30 次有用證據。'],
                    ['slug' => 'usefulness-curator', 'name' => '有用性策展者', 'target' => 75, 'description' => '標記 75 次有用證據。'],
                    ['slug' => 'quality-sentinel', 'name' => '品質哨兵', 'target' => 150, 'description' => '標記 150 次有用證據。'],
                    ['slug' => 'helpful-signal-architect', 'name' => '有用訊號架構師', 'target' => 300, 'description' => '標記 300 次有用證據。'],
                    ['slug' => 'quality-network-anchor', 'name' => '品質網絡錨點', 'target' => 750, 'description' => '標記 750 次有用證據。'],
                    ['slug' => 'evidence-quality-lighthouse', 'name' => '證據品質燈塔', 'target' => 1500, 'description' => '標記 1,500 次有用證據。'],
                    ['slug' => 'public-quality-pillar', 'name' => '公共品質支柱', 'target' => 3000, 'description' => '標記 3,000 次有用證據。'],
                    ['slug' => 'quality-constellation', 'name' => '品質星圖', 'target' => 6000, 'description' => '標記 6,000 次有用證據。'],
                    ['slug' => 'ten-thousand-helpful-signals', 'name' => '萬次有用訊號', 'target' => 10000, 'description' => '標記 10,000 次有用證據。'],
                ],
            ],
            [
                'metric' => 'read_sessions',
                'color' => '#facc15',
                'tiers' => [
                    ['slug' => 'reading-discipline', 'name' => '閱讀紀律', 'target' => 5, 'description' => '累積 5 篇新聞閱讀紀錄。'],
                    ['slug' => 'deep-reader', 'name' => '深度閱讀者', 'target' => 25, 'description' => '累積 25 篇新聞閱讀紀錄。'],
                    ['slug' => 'persistent-reader', 'name' => '持續閱讀者', 'target' => 75, 'description' => '累積 75 篇新聞閱讀紀錄。'],
                    ['slug' => 'news-marathoner', 'name' => '新聞馬拉松者', 'target' => 150, 'description' => '累積 150 篇新聞閱讀紀錄。'],
                    ['slug' => 'daily-news-regular', 'name' => '每日新聞常客', 'target' => 300, 'description' => '累積 300 篇新聞閱讀紀錄。'],
                    ['slug' => 'news-chronicle-reader', 'name' => '新聞編年讀者', 'target' => 750, 'description' => '累積 750 篇新聞閱讀紀錄。'],
                    ['slug' => 'thousand-article-reader', 'name' => '千篇閱讀者', 'target' => 1500, 'description' => '累積 1,500 篇新聞閱讀紀錄。'],
                    ['slug' => 'public-briefing-keeper', 'name' => '公共簡報守門人', 'target' => 3000, 'description' => '累積 3,000 篇新聞閱讀紀錄。'],
                    ['slug' => 'civic-reading-archive', 'name' => '公民閱讀檔案', 'target' => 6000, 'description' => '累積 6,000 篇新聞閱讀紀錄。'],
                    ['slug' => 'ten-thousand-articles', 'name' => '萬篇閱讀者', 'target' => 10000, 'description' => '累積 10,000 篇新聞閱讀紀錄。'],
                ],
            ],
            [
                'metric' => 'community_signals',
                'color' => '#2dd4bf',
                'tiers' => [
                    ['slug' => 'community-scout', 'name' => '社群偵查員', 'target' => 1, 'description' => '提交第一筆新聞站、URL 或可信來源訊號。'],
                    ['slug' => 'domain-maintainer', 'name' => '新聞站維護者', 'target' => 5, 'description' => '提交 5 筆新聞站、URL 或可信來源訊號。'],
                    ['slug' => 'signal-coordinator', 'name' => '訊號協調者', 'target' => 15, 'description' => '提交 15 筆社群維護訊號。'],
                    ['slug' => 'community-steward', 'name' => '社群維運者', 'target' => 40, 'description' => '提交 40 筆社群維護訊號。'],
                    ['slug' => 'community-operator', 'name' => '社群營運者', 'target' => 100, 'description' => '提交 100 筆社群維護訊號。'],
                    ['slug' => 'platform-gardener', 'name' => '平台園丁', 'target' => 250, 'description' => '提交 250 筆社群維護訊號。'],
                    ['slug' => 'signal-networker', 'name' => '訊號網絡者', 'target' => 500, 'description' => '提交 500 筆社群維護訊號。'],
                    ['slug' => 'community-infrastructure-builder', 'name' => '社群基礎建設者', 'target' => 1000, 'description' => '提交 1,000 筆社群維護訊號。'],
                    ['slug' => 'civic-platform-steward', 'name' => '公民平台維運長', 'target' => 2500, 'description' => '提交 2,500 筆社群維護訊號。'],
                    ['slug' => 'signal-archive-champion', 'name' => '訊號檔案冠軍', 'target' => 5000, 'description' => '提交 5,000 筆社群維護訊號。'],
                    ['slug' => 'ten-thousand-community-signals', 'name' => '萬筆社群訊號', 'target' => 10000, 'description' => '提交 10,000 筆社群維護訊號。'],
                ],
            ],
            [
                'metric' => 'accepted_community_signals',
                'color' => '#14b8a6',
                'tiers' => [
                    ['slug' => 'accepted-signal', 'name' => '有效訊號提供者', 'target' => 1, 'description' => '提交第一筆被採用的社群維護訊號。'],
                    ['slug' => 'policy-maintainer', 'name' => '規則維護者', 'target' => 5, 'description' => '提交 5 筆被採用的社群維護訊號。'],
                    ['slug' => 'infrastructure-guardian', 'name' => '平台基礎守護者', 'target' => 15, 'description' => '提交 15 筆被採用的社群維護訊號。'],
                    ['slug' => 'accepted-signal-specialist', 'name' => '有效訊號專家', 'target' => 40, 'description' => '提交 40 筆被採用的社群維護訊號。'],
                    ['slug' => 'accepted-policy-builder', 'name' => '採用規則建設者', 'target' => 100, 'description' => '提交 100 筆被採用的社群維護訊號。'],
                    ['slug' => 'accepted-signal-architect', 'name' => '採用訊號架構師', 'target' => 250, 'description' => '提交 250 筆被採用的社群維護訊號。'],
                    ['slug' => 'platform-reliability-steward', 'name' => '平台可靠性維護者', 'target' => 500, 'description' => '提交 500 筆被採用的社群維護訊號。'],
                    ['slug' => 'accepted-signal-master', 'name' => '採用訊號大師', 'target' => 1000, 'description' => '提交 1,000 筆被採用的社群維護訊號。'],
                    ['slug' => 'accepted-signal-champion', 'name' => '採用訊號冠軍', 'target' => 2500, 'description' => '提交 2,500 筆被採用的社群維護訊號。'],
                    ['slug' => 'accepted-signal-legend', 'name' => '採用訊號傳奇', 'target' => 5000, 'description' => '提交 5,000 筆被採用的社群維護訊號。'],
                    ['slug' => 'ten-thousand-accepted-signals', 'name' => '萬筆採用訊號', 'target' => 10000, 'description' => '提交 10,000 筆被採用的社群維護訊號。'],
                ],
            ],
            [
                'metric' => 'trust_history_entries',
                'color' => '#fb7185',
                'tiers' => [
                    ['slug' => 'trust-growth', 'name' => '信用成長', 'target' => 3, 'description' => '累積 3 筆信用分歷史。'],
                    ['slug' => 'trust-climber', 'name' => '信用攀升者', 'target' => 10, 'description' => '累積 10 筆信用分歷史。'],
                    ['slug' => 'trust-keeper', 'name' => '信用守護者', 'target' => 25, 'description' => '累積 25 筆信用分歷史。'],
                    ['slug' => 'trust-pillar', 'name' => '信用支柱', 'target' => 50, 'description' => '累積 50 筆信用分歷史。'],
                    ['slug' => 'trust-trailblazer', 'name' => '信用開拓者', 'target' => 100, 'description' => '累積 100 筆信用分歷史。'],
                    ['slug' => 'trust-architect', 'name' => '信用架構師', 'target' => 250, 'description' => '累積 250 筆信用分歷史。'],
                    ['slug' => 'trust-veteran', 'name' => '信用老手', 'target' => 500, 'description' => '累積 500 筆信用分歷史。'],
                    ['slug' => 'trust-ledger-master', 'name' => '信用帳本大師', 'target' => 1000, 'description' => '累積 1,000 筆信用分歷史。'],
                    ['slug' => 'trust-history-guardian', 'name' => '信用歷史守護者', 'target' => 2500, 'description' => '累積 2,500 筆信用分歷史。'],
                    ['slug' => 'trust-archive-champion', 'name' => '信用檔案冠軍', 'target' => 5000, 'description' => '累積 5,000 筆信用分歷史。'],
                    ['slug' => 'ten-thousand-trust-ledgers', 'name' => '萬筆信用紀錄', 'target' => 10000, 'description' => '累積 10,000 筆信用分歷史。'],
                ],
            ],
            [
                'metric' => 'snapshot_reports',
                'color' => '#f97316',
                'tiers' => [
                    ['slug' => 'snapshot-guardian', 'name' => '快照守門員', 'target' => 1, 'description' => '回報第一筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'snapshot-sentinel', 'name' => '快照哨兵', 'target' => 5, 'description' => '回報 5 筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'archive-defender', 'name' => '存證防線', 'target' => 15, 'description' => '回報 15 筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'history-keeper', 'name' => '紀錄保存者', 'target' => 30, 'description' => '回報 30 筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'snapshot-archivist', 'name' => '快照典藏員', 'target' => 75, 'description' => '回報 75 筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'revision-watch-captain', 'name' => '改稿觀察隊長', 'target' => 150, 'description' => '回報 150 筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'public-memory-keeper', 'name' => '公共記憶守門人', 'target' => 300, 'description' => '回報 300 筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'archive-networker', 'name' => '存證網絡者', 'target' => 750, 'description' => '回報 750 筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'snapshot-citadel', 'name' => '快照城塞', 'target' => 1500, 'description' => '回報 1,500 筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'public-record-archivist', 'name' => '公共紀錄典藏官', 'target' => 3000, 'description' => '回報 3,000 筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'memory-archive-champion', 'name' => '記憶檔案冠軍', 'target' => 6000, 'description' => '回報 6,000 筆新聞改稿、刪文或存證需求。'],
                    ['slug' => 'ten-thousand-snapshot-reports', 'name' => '萬筆快照回報', 'target' => 10000, 'description' => '回報 10,000 筆新聞改稿、刪文或存證需求。'],
                ],
            ],
            [
                'metric' => 'snapshots_guarded',
                'color' => '#a78bfa',
                'tiers' => [
                    ['slug' => 'media-watchkeeper', 'name' => '媒體觀察員', 'target' => 5, 'description' => '參與過至少 5 筆具快照紀錄的新聞。'],
                    ['slug' => 'media-sentinel', 'name' => '媒體哨兵', 'target' => 20, 'description' => '參與過至少 20 筆具快照紀錄的新聞。'],
                    ['slug' => 'archive-patrol', 'name' => '存證巡邏員', 'target' => 50, 'description' => '參與過至少 50 筆具快照紀錄的新聞。'],
                    ['slug' => 'media-archive-guardian', 'name' => '媒體存證守護者', 'target' => 100, 'description' => '參與過至少 100 筆具快照紀錄的新聞。'],
                    ['slug' => 'snapshot-watch-commander', 'name' => '快照觀察指揮官', 'target' => 250, 'description' => '參與過至少 250 筆具快照紀錄的新聞。'],
                    ['slug' => 'media-memory-steward', 'name' => '媒體記憶維護者', 'target' => 500, 'description' => '參與過至少 500 筆具快照紀錄的新聞。'],
                    ['slug' => 'archive-guardian-master', 'name' => '存證守護大師', 'target' => 1000, 'description' => '參與過至少 1,000 筆具快照紀錄的新聞。'],
                    ['slug' => 'public-archive-pillar', 'name' => '公共存證支柱', 'target' => 2500, 'description' => '參與過至少 2,500 筆具快照紀錄的新聞。'],
                    ['slug' => 'media-archive-champion', 'name' => '媒體存證冠軍', 'target' => 5000, 'description' => '參與過至少 5,000 筆具快照紀錄的新聞。'],
                    ['slug' => 'ten-thousand-guarded-snapshots', 'name' => '萬篇快照守護者', 'target' => 10000, 'description' => '參與過至少 10,000 筆具快照紀錄的新聞。'],
                ],
            ],
            [
                'metric' => 'paid_donations',
                'color' => '#f0abfc',
                'tiers' => [
                    ['slug' => 'shield-supporter', 'name' => '護盾支持者', 'target' => 1, 'description' => '完成第一筆專案捐款支持。'],
                    ['slug' => 'recurring-supporter', 'name' => '持續支持者', 'target' => 3, 'description' => '完成 3 筆專案捐款支持。'],
                    ['slug' => 'project-patron', 'name' => '專案後盾', 'target' => 10, 'description' => '完成 10 筆專案捐款支持。'],
                    ['slug' => 'community-patron', 'name' => '社群後盾', 'target' => 25, 'description' => '完成 25 筆專案捐款支持。'],
                    ['slug' => 'sustaining-patron', 'name' => '長期支援者', 'target' => 50, 'description' => '完成 50 筆專案捐款支持。'],
                    ['slug' => 'truthshield-benefactor', 'name' => 'TruthShield 贊助人', 'target' => 100, 'description' => '完成 100 筆專案捐款支持。'],
                    ['slug' => 'public-interest-backer', 'name' => '公共利益後援者', 'target' => 250, 'description' => '完成 250 筆專案捐款支持。'],
                    ['slug' => 'civic-infra-patron', 'name' => '公民基礎建設贊助者', 'target' => 500, 'description' => '完成 500 筆專案捐款支持。'],
                    ['slug' => 'truthshield-endowment', 'name' => 'TruthShield 基金級支持者', 'target' => 1000, 'description' => '完成 1,000 筆專案捐款支持。'],
                    ['slug' => 'public-trust-endowment', 'name' => '公共信任基金級支持者', 'target' => 2500, 'description' => '完成 2,500 筆專案捐款支持。'],
                    ['slug' => 'civic-media-patron', 'name' => '公民媒體萬里後盾', 'target' => 5000, 'description' => '完成 5,000 筆專案捐款支持。'],
                    ['slug' => 'ten-thousand-donations', 'name' => '萬次護盾支持者', 'target' => 10000, 'description' => '完成 10,000 筆專案捐款支持。'],
                ],
            ],
            [
                'metric' => 'comments_created',
                'color' => '#c084fc',
                'tiers' => [
                    ['slug' => 'first-comment', 'name' => '初次發言', 'target' => 1, 'description' => '在留言板留下第一則想法。'],
                    ['slug' => 'active-reader', 'name' => '活躍讀者', 'target' => 5, 'description' => '留下 5 則留言。'],
                    ['slug' => 'discussion-contributor', 'name' => '討論貢獻者', 'target' => 15, 'description' => '留下 15 則留言，成為討論的一部分。'],
                    ['slug' => 'regular-commenter', 'name' => '討論常客', 'target' => 30, 'description' => '留下 30 則留言。'],
                    ['slug' => 'comment-enthusiast', 'name' => '留言達人', 'target' => 75, 'description' => '留下 75 則留言。'],
                    ['slug' => 'senior-commenter', 'name' => '資深留言者', 'target' => 150, 'description' => '留下 150 則留言。'],
                    ['slug' => 'comment-guardian', 'name' => '留言守護者', 'target' => 300, 'description' => '留下 300 則留言，持續參與社群對話。'],
                    ['slug' => 'comment-master', 'name' => '留言大師', 'target' => 750, 'description' => '留下 750 則留言，成為社群對話中堅力量。'],
                    ['slug' => 'thousand-commentator', 'name' => '千言留言者', 'target' => 1000, 'description' => '留下 1,000 則留言。'],
                    ['slug' => 'ten-thousand-comments', 'name' => '萬則留言者', 'target' => 10000, 'description' => '留下 10,000 則留言。'],
                ],
            ],
            [
                'metric' => 'comments_helpful_received',
                'color' => '#fb923c',
                'tiers' => [
                    ['slug' => 'first-helpful-comment', 'name' => '初獲好評', 'target' => 1, 'description' => '留言獲得第一次「有用」評價。'],
                    ['slug' => 'worth-reading', 'name' => '值得一看', 'target' => 5, 'description' => '留言共獲得 5 次「有用」評價。'],
                    ['slug' => 'community-contributor', 'name' => '社群貢獻者', 'target' => 15, 'description' => '留言共獲得 15 次「有用」評價。'],
                    ['slug' => 'quality-commenter', 'name' => '留言品質保證', 'target' => 30, 'description' => '留言共獲得 30 次「有用」評價。'],
                    ['slug' => 'discussion-inspirer', 'name' => '討論啟發者', 'target' => 75, 'description' => '留言共獲得 75 次「有用」評價。'],
                    ['slug' => 'community-thinker', 'name' => '社群思想家', 'target' => 150, 'description' => '留言共獲得 150 次「有用」評價。'],
                    ['slug' => 'helpful-voice', 'name' => '有益之聲', 'target' => 300, 'description' => '留言共獲得 300 次「有用」評價。'],
                    ['slug' => 'thousand-helpful-comments', 'name' => '千次好評留言者', 'target' => 1000, 'description' => '留言共獲得 1,000 次「有用」評價。'],
                ],
            ],
        ])
            ->flatMap(fn (array $group) => collect($group['tiers'])
                ->map(fn (array $tier): array => [
                    'name' => $tier['name'],
                    'slug' => $tier['slug'],
                    'description' => $tier['description'],
                    'color' => $group['color'],
                    'metric' => $group['metric'],
                    'target' => $tier['target'],
                    'reason' => $tier['description'],
                ]))
            ->values()
            ->all();
    }
}
