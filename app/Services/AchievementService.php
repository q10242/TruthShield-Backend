<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\EvidenceReaction;
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
        if ((float) $user->trust_score >= 3.0 || $stats['badges'] >= 6) {
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
            'read_sessions' => $user->readSessions()->count(),
            'trust_history_entries' => $user->trustScoreHistories()->count(),
            'badges' => $user->badges()->count(),
        ];
    }

    public function definitions(): array
    {
        return [
            [
                'name' => '第一面盾',
                'slug' => 'first-shield',
                'description' => '完成第一筆新聞投票。',
                'color' => '#67e8f9',
                'metric' => 'votes',
                'target' => 1,
                'reason' => '完成第一筆新聞投票。',
            ],
            [
                'name' => '早期查證者',
                'slug' => 'early-verifier',
                'description' => '完成 3 筆新聞查證。',
                'color' => '#67e8f9',
                'metric' => 'votes',
                'target' => 3,
                'reason' => '完成 3 筆新聞查證。',
            ],
            [
                'name' => '穩定查證者',
                'slug' => 'steady-reviewer',
                'description' => '完成 10 筆新聞查證。',
                'color' => '#38bdf8',
                'metric' => 'votes',
                'target' => 10,
                'reason' => '完成 10 筆新聞查證。',
            ],
            [
                'name' => '證據提供者',
                'slug' => 'evidence-supplier',
                'description' => '提交第一筆外部證據。',
                'color' => '#86efac',
                'metric' => 'evidence_votes',
                'target' => 1,
                'reason' => '提交第一筆外部證據。',
            ],
            [
                'name' => '證據策展者',
                'slug' => 'evidence-curator',
                'description' => '提交的證據累積 5 次有用評分。',
                'color' => '#86efac',
                'metric' => 'helpful_evidence_received',
                'target' => 5,
                'reason' => '提交的證據累積 5 次有用評分。',
            ],
            [
                'name' => '證據審閱員',
                'slug' => 'evidence-rater',
                'description' => '完成 5 次證據有用/沒幫助評分。',
                'color' => '#c4b5fd',
                'metric' => 'evidence_reactions',
                'target' => 5,
                'reason' => '完成 5 次證據評分。',
            ],
            [
                'name' => '閱讀紀律',
                'slug' => 'reading-discipline',
                'description' => '累積 5 篇新聞閱讀紀錄。',
                'color' => '#facc15',
                'metric' => 'read_sessions',
                'target' => 5,
                'reason' => '累積 5 篇新聞閱讀紀錄。',
            ],
            [
                'name' => '信用成長',
                'slug' => 'trust-growth',
                'description' => '累積 3 筆信用分歷史。',
                'color' => '#fb7185',
                'metric' => 'trust_history_entries',
                'target' => 3,
                'reason' => '累積 3 筆信用分歷史。',
            ],
        ];
    }
}
