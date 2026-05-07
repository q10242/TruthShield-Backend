<?php

namespace Database\Seeders;

use App\Models\MediaOutlet;
use App\Models\Badge;
use App\Models\NewsDomain;
use App\Models\NewsUrl;
use App\Models\OfficialResponse;
use App\Models\OfficialResponseReaction;
use App\Models\SystemSetting;
use App\Models\Tag;
use App\Models\User;
use App\Models\VerifiedClaimant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(TagSeeder::class);

        foreach ([
            ['name' => '早期查證者', 'slug' => 'early-verifier', 'description' => '完成第一批新聞查證貢獻。', 'color' => '#67e8f9'],
            ['name' => '證據策展者', 'slug' => 'evidence-curator', 'description' => '提交的證據多次被標為有用。', 'color' => '#86efac'],
            ['name' => '逆風觀察者', 'slug' => 'contrarian-scout', 'description' => '在定案前提出少數但有價值的判斷。', 'color' => '#fbbf24'],
            ['name' => '第一面盾', 'slug' => 'first-shield', 'description' => '完成第一筆新聞投票。', 'color' => '#67e8f9'],
            ['name' => '穩定查證者', 'slug' => 'steady-reviewer', 'description' => '完成 10 筆新聞查證。', 'color' => '#38bdf8'],
            ['name' => '證據提供者', 'slug' => 'evidence-supplier', 'description' => '提交第一筆外部證據。', 'color' => '#86efac'],
            ['name' => '證據審閱員', 'slug' => 'evidence-rater', 'description' => '完成 5 次證據有用/沒幫助評分。', 'color' => '#c4b5fd'],
            ['name' => '閱讀紀律', 'slug' => 'reading-discipline', 'description' => '累積 5 篇新聞閱讀紀錄。', 'color' => '#facc15'],
            ['name' => '信用成長', 'slug' => 'trust-growth', 'description' => '累積 3 筆信用分歷史。', 'color' => '#fb7185'],
            ['name' => '護盾支持者', 'slug' => 'shield-supporter', 'description' => '完成第一筆專案捐款支持。', 'color' => '#f0abfc'],
            ['name' => '快照守門員', 'slug' => 'snapshot-guardian', 'description' => '回報第一筆新聞改稿、刪文或存證需求。', 'color' => '#f97316'],
            ['name' => '媒體觀察員', 'slug' => 'media-watchkeeper', 'description' => '參與過至少 5 筆具快照紀錄的新聞。', 'color' => '#a78bfa'],
        ] as $badge) {
            Badge::query()->updateOrCreate(['slug' => $badge['slug']], $badge);
        }

        SystemSetting::query()->updateOrCreate(
            ['key' => 'algorithm_summary'],
            [
                'value' => [
                    'voting_window_hours' => 72,
                    'vote_weight' => 'user.trust_score capped by anti-abuse rules',
                    'evidence_rating_min_trust_score' => config('truthshield.evidence_reaction_min_trust_score'),
                    'finalization' => 'weighted consensus snapshot after voting window closes',
                ],
                'description' => 'Public TruthShield algorithm summary.',
            ],
        );

        foreach (config('truthshield.news_domains') as $domain) {
            $isYoutube = in_array($domain, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'], true);
            $outlet = MediaOutlet::query()->firstOrCreate(
                ['slug' => $isYoutube ? 'youtube' : Str::slug($domain)],
                ['name' => $isYoutube ? 'YouTube' : $domain, 'type' => $isYoutube ? 'video_platform' : 'news', 'region' => $isYoutube ? 'global' : 'TW', 'is_active' => true],
            );

            NewsDomain::query()->updateOrCreate(
                ['domain' => $domain],
                [
                    'media_outlet_id' => $outlet->id,
                    'is_active' => true,
                    'article_url_pattern' => $isYoutube ? '^/(watch|shorts/|live/)' : null,
                    'list_url_pattern' => $isYoutube ? '^/(feed|channel|@|results|playlist|shorts/?$)' : null,
                    'priority' => $isYoutube ? 20 : 100,
                ],
            );
        }

        $tester = User::firstOrCreate(
            ['email' => 'tester@truthshield.local'],
            [
                'name' => 'TruthShield Tester',
                'display_name' => '測試查證者',
                'email' => 'tester@truthshield.local',
                'password' => Hash::make('password123'),
                'auth_provider' => 'dev',
                'trust_score' => 1.0,
                'email_verified_at' => now(),
            ],
        );

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@truthshield.local'],
            [
                'name' => 'TruthShield Admin',
                'display_name' => 'TruthShield 管理員',
                'public_identity_label' => '平台管理員',
                'password' => Hash::make('admin123456'),
                'is_admin' => true,
                'trust_score' => 3.0,
                'auth_provider' => 'dev',
                'email_verified_at' => now(),
            ],
        );

        $outlet = MediaOutlet::query()->first();
        $normalizedUrl = 'https://example-news.test/politics/truthshield-sample';
        $newsUrl = NewsUrl::query()->updateOrCreate(
            ['hash' => hash('sha256', $normalizedUrl)],
            [
                'media_outlet_id' => $outlet?->id,
                'original_url' => $normalizedUrl . '?utm_source=seed',
                'normalized_url' => $normalizedUrl,
                'title_snapshot' => 'TruthShield 測試新聞：地方政策爭議整理',
                'description_snapshot' => '用於本地測試投票、證據、官方澄清與透明頁統計的樣本新聞。',
                'availability_status' => 'available',
                'voting_closes_at' => now()->addHours(72),
            ],
        );

        $claimant = VerifiedClaimant::query()->updateOrCreate(
            ['user_id' => $admin->id, 'news_url_id' => $newsUrl->id, 'claim_type' => 'media'],
            [
                'domain' => 'example-news.test',
                'organization_name' => 'Example News 編輯部',
                'proof_url' => 'https://drive.google.com/file/d/truthshield-seed-proof/view',
                'statement' => '本筆資料供本地後台測試官方澄清審核流程。',
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'verified_at' => now(),
                'reviewed_at' => now(),
                'review_note' => 'Seed sample claimant approved for local testing.',
            ],
        );

        $response = OfficialResponse::query()->updateOrCreate(
            ['news_url_id' => $newsUrl->id, 'verified_claimant_id' => $claimant->id, 'response_type' => 'media_statement'],
            [
                'user_id' => $admin->id,
                'response_text' => '這是一則本地測試用官方澄清，展示澄清內容會與投票結果分開呈現。',
                'evidence_url' => 'https://drive.google.com/file/d/truthshield-seed-response/view',
                'status' => 'published',
                'helpful_weight' => 1.0,
                'unhelpful_weight' => 0.0,
                'reviewed_by' => $admin->id,
                'published_at' => now(),
                'reviewed_at' => now(),
                'review_note' => 'Seed sample official response.',
            ],
        );

        OfficialResponseReaction::query()->updateOrCreate(
            ['official_response_id' => $response->id, 'user_id' => $tester->id],
            ['helpful' => true, 'weight_score' => $tester->trust_score],
        );

        $tag = Tag::query()->where('slug', 'lack-of-balance')->first();
        if ($tag && ! $tester->votes()->where('news_url_id', $newsUrl->id)->exists()) {
            $tester->votes()->create([
                'news_url_id' => $newsUrl->id,
                'tag_id' => $tag->id,
                'secondary_tag_ids' => Tag::query()
                    ->whereIn('slug', ['single-source', 'missing-facts'])
                    ->pluck('id')
                    ->values()
                    ->all(),
                'evidence_url' => 'https://imgur.com/truthshield-seed-evidence',
                'evidence_note' => '樣本投票：新聞只呈現單一說法，缺少另一方回應。',
                'evidence_type' => 'image',
                'evidence_host' => 'imgur.com',
                'evidence_safety' => 'trusted',
                'weight_score' => $tester->trust_score,
            ]);
        }
    }
}
