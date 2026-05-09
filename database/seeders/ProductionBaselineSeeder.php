<?php

namespace Database\Seeders;

use App\Models\AlgorithmVersion;
use App\Models\Badge;
use App\Models\MediaOutlet;
use App\Models\NewsDomain;
use App\Models\RateLimitPolicy;
use App\Models\SystemSetting;
use App\Models\TrustedEvidenceSource;
use App\Models\YoutubeChannel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductionBaselineSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TagSeeder::class);

        $this->seedBadges();
        $this->seedSystemSettings();
        $this->seedAlgorithmVersion();
        $this->seedRateLimits();
        $this->seedTrustedEvidenceSources();
        $this->seedNewsDomains();
        $this->seedYoutubeChannels();
        if (app()->environment('production')) {
            $this->deactivateLocalOnlyDomains();
        }
    }

    private function seedBadges(): void
    {
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
            ['name' => '新聞站維護者', 'slug' => 'domain-maintainer', 'description' => '協助確認新聞站收錄與分類規則。', 'color' => '#2dd4bf'],
            ['name' => '濫用觀察員', 'slug' => 'abuse-observer', 'description' => '協助辨識異常投票與協同行為。', 'color' => '#fb923c'],
            ['name' => '可信審查者', 'slug' => 'trusted-reviewer', 'description' => '長期提供高品質查證與證據評價。', 'color' => '#60a5fa'],
        ] as $badge) {
            Badge::query()->updateOrCreate(['slug' => $badge['slug']], $badge);
        }
    }

    private function seedSystemSettings(): void
    {
        SystemSetting::query()->updateOrCreate(
            ['key' => 'algorithm_summary'],
            [
                'value' => [
                    'voting_window_hours' => 72,
                    'vote_weight' => 'trust_score * identity_multiplier * abuse_multiplier',
                    'evidence_rating_min_trust_score' => config('truthshield.evidence_reaction_min_trust_score'),
                    'finalization' => 'weighted consensus snapshot after the voting window closes',
                    'one_vote_per_user_per_news' => true,
                    'public_rules_page' => '/platform-rules',
                    'label_guide_page' => '/label-guide',
                ],
                'description' => 'Public TruthShield algorithm summary.',
            ],
        );

        SystemSetting::query()->updateOrCreate(
            ['key' => 'community_automation_thresholds'],
            [
                'value' => [
                    'minimum_trusted_users' => 3,
                    'minimum_weighted_score' => 3.0,
                    'anonymous_reports_can_auto_approve' => false,
                    'high_risk_items_require_admin_review' => true,
                    'evidence_downrank_threshold' => -2.0,
                ],
                'description' => 'Default conservative thresholds for community self-management.',
            ],
        );
    }

    private function seedAlgorithmVersion(): void
    {
        AlgorithmVersion::query()->updateOrCreate(
            ['version' => config('truthshield.algorithm_version')],
            [
                'status' => 'active',
                'summary' => 'Initial public TruthShield weighted consensus algorithm.',
                'rules' => [
                    'voting_window_hours' => 72,
                    'weight_formula' => 'trust_score * identity_multiplier * abuse_multiplier',
                    'finalization' => 'Results and evidence ordering are frozen after finalization.',
                    'anti_abuse' => ['burst voting', 'duplicate evidence', 'low reading time', 'new-account velocity'],
                ],
                'activated_at' => now(),
            ],
        );
    }

    private function seedRateLimits(): void
    {
        foreach ([
            ['name' => 'hover', 'scope' => 'public_status', 'max_attempts' => 600, 'decay_seconds' => 60, 'low_trust_multiplier' => 1],
            ['name' => 'vote', 'scope' => 'authenticated_write', 'max_attempts' => 30, 'decay_seconds' => 60, 'low_trust_multiplier' => 0.5],
            ['name' => 'reaction', 'scope' => 'authenticated_write', 'max_attempts' => 60, 'decay_seconds' => 60, 'low_trust_multiplier' => 0.5],
            ['name' => 'report', 'scope' => 'authenticated_moderation', 'max_attempts' => 10, 'decay_seconds' => 60, 'low_trust_multiplier' => 0.25],
            ['name' => 'bug_report', 'scope' => 'public_report', 'max_attempts' => 5, 'decay_seconds' => 300, 'low_trust_multiplier' => 0.25],
            ['name' => 'oauth_begin', 'scope' => 'auth', 'max_attempts' => 20, 'decay_seconds' => 60, 'low_trust_multiplier' => 1],
        ] as $policy) {
            RateLimitPolicy::query()->updateOrCreate(['name' => $policy['name']], $policy + ['is_active' => true]);
        }
    }

    private function seedTrustedEvidenceSources(): void
    {
        $sources = [
            ['host' => 'imgur.com', 'source_type' => 'image_host', 'trust_bonus' => 8, 'notes' => 'External image host.'],
            ['host' => 'www.imgur.com', 'source_type' => 'image_host', 'trust_bonus' => 8, 'notes' => 'External image host.'],
            ['host' => 'i.imgur.com', 'source_type' => 'image_host', 'trust_bonus' => 8, 'notes' => 'Direct Imgur image host.'],
            ['host' => 'drive.google.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 6, 'notes' => 'Google Drive evidence link.'],
            ['host' => 'docs.google.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 6, 'notes' => 'Google Docs/Sheets evidence link.'],
            ['host' => 'photos.google.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 6, 'notes' => 'Google Photos evidence link.'],
            ['host' => 'www.dropbox.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 6, 'notes' => 'Dropbox evidence link.'],
            ['host' => 'dl.dropboxusercontent.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 6, 'notes' => 'Dropbox direct content link.'],
            ['host' => 'onedrive.live.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 6, 'notes' => 'OneDrive evidence link.'],
            ['host' => '1drv.ms', 'source_type' => 'cloud_drive', 'trust_bonus' => 6, 'notes' => 'OneDrive short evidence link.'],
            ['host' => 'sharepoint.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 6, 'notes' => 'SharePoint evidence link.'],
            ['host' => 'icloud.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 5, 'notes' => 'iCloud evidence link.'],
            ['host' => 'www.icloud.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 5, 'notes' => 'iCloud evidence link.'],
            ['host' => 'box.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 5, 'notes' => 'Box evidence link.'],
            ['host' => 'app.box.com', 'source_type' => 'cloud_drive', 'trust_bonus' => 5, 'notes' => 'Box evidence link.'],
            ['host' => 'archive.ph', 'source_type' => 'archive', 'trust_bonus' => 10, 'notes' => 'Archive snapshot link.'],
            ['host' => 'web.archive.org', 'source_type' => 'archive', 'trust_bonus' => 10, 'notes' => 'Internet Archive snapshot link.'],
            ['host' => 'youtube.com', 'source_type' => 'video', 'trust_bonus' => 5, 'notes' => 'YouTube video evidence.'],
            ['host' => 'www.youtube.com', 'source_type' => 'video', 'trust_bonus' => 5, 'notes' => 'YouTube video evidence.'],
            ['host' => 'm.youtube.com', 'source_type' => 'video', 'trust_bonus' => 5, 'notes' => 'YouTube mobile video evidence.'],
            ['host' => 'youtu.be', 'source_type' => 'video', 'trust_bonus' => 5, 'notes' => 'YouTube short video evidence.'],
        ];

        foreach ($sources as $source) {
            TrustedEvidenceSource::query()->updateOrCreate(
                ['host' => $source['host']],
                $source + ['is_active' => true],
            );
        }
    }

    private function seedNewsDomains(): void
    {
        foreach ($this->newsDomains() as $domain) {
            $outlet = MediaOutlet::query()->updateOrCreate(
                ['slug' => $domain['outlet_slug']],
                [
                    'name' => $domain['outlet_name'],
                    'type' => $domain['outlet_type'] ?? 'news',
                    'region' => $domain['region'] ?? 'TW',
                    'is_active' => true,
                    'notes' => $domain['outlet_notes'] ?? null,
                ],
            );

            NewsDomain::query()->updateOrCreate(
                ['domain' => $domain['domain']],
                [
                    'media_outlet_id' => $outlet->id,
                    'name' => $domain['name'] ?? $domain['outlet_name'],
                    'is_active' => true,
                    'notes' => $domain['notes'] ?? 'Production baseline monitored domain.',
                    'article_selector' => $domain['article_selector'] ?? null,
                    'title_selector' => $domain['title_selector'] ?? null,
                    'content_selector' => $domain['content_selector'] ?? null,
                    'blocked_path_pattern' => $domain['blocked_path_pattern'] ?? null,
                    'article_url_pattern' => $domain['article_url_pattern'] ?? null,
                    'list_url_pattern' => $domain['list_url_pattern'] ?? null,
                    'priority' => $domain['priority'] ?? 100,
                ],
            );
        }
    }

    private function deactivateLocalOnlyDomains(): void
    {
        NewsDomain::query()
            ->whereIn('domain', ['127.0.0.1', 'localhost'])
            ->update([
                'is_active' => false,
                'priority' => 0,
                'notes' => 'Local development only; disabled by production baseline seed.',
            ]);
    }

    private function seedYoutubeChannels(): void
    {
        foreach ($this->youtubeChannels() as $channel) {
            $outlet = MediaOutlet::query()->where('slug', $channel['outlet_slug'])->first();
            $criteria = $channel['handle']
                ? ['handle' => $channel['handle']]
                : ['channel_url' => $channel['channel_url']];

            YoutubeChannel::query()->updateOrCreate($criteria, [
                'media_outlet_id' => $outlet?->id,
                'channel_id' => $channel['channel_id'] ?? null,
                'handle' => $channel['handle'],
                'title' => $channel['title'],
                'channel_url' => $channel['channel_url'],
                'channel_type' => $channel['channel_type'] ?? 'news',
                'status' => 'active',
                'is_active' => true,
                'notes' => $channel['notes'] ?? 'Production baseline official YouTube news channel.',
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function newsDomains(): array
    {
        $blockedLists = '^/(?:$|search|tag|tags|category|categories|topic|topics|author|authors|video/?$|photo/?$|member|login|register|privacy|about)';

        $domains = [
            ['domain' => 'cna.com.tw', 'outlet_name' => '中央社', 'outlet_slug' => 'cna', 'article_url_pattern' => '/news/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.cna.com.tw', 'outlet_name' => '中央社', 'outlet_slug' => 'cna', 'article_url_pattern' => '/news/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'news.pts.org.tw', 'outlet_name' => '公視新聞網', 'outlet_slug' => 'pts-news', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.cw.com.tw', 'outlet_name' => '天下雜誌', 'outlet_slug' => 'commonwealth', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.businessweekly.com.tw', 'outlet_name' => '商業周刊', 'outlet_slug' => 'businessweekly', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'udn.com', 'outlet_name' => '聯合新聞網', 'outlet_slug' => 'udn', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.chinatimes.com', 'outlet_name' => '中時新聞網', 'outlet_slug' => 'chinatimes', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.ettoday.net', 'outlet_name' => 'ETtoday新聞雲', 'outlet_slug' => 'ettoday', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.setn.com', 'outlet_name' => '三立新聞網', 'outlet_slug' => 'setn', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'news.ltn.com.tw', 'outlet_name' => '自由時報', 'outlet_slug' => 'ltn', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'tw.news.yahoo.com', 'outlet_name' => 'Yahoo奇摩新聞', 'outlet_slug' => 'yahoo-news-tw', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.storm.mg', 'outlet_name' => '風傳媒', 'outlet_slug' => 'storm-media', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.thenewslens.com', 'outlet_name' => '關鍵評論網', 'outlet_slug' => 'the-news-lens', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.mirrormedia.mg', 'outlet_name' => '鏡週刊', 'outlet_slug' => 'mirror-media', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.rti.org.tw', 'outlet_name' => '中央廣播電臺', 'outlet_slug' => 'rti', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.ftvnews.com.tw', 'outlet_name' => '民視新聞網', 'outlet_slug' => 'ftv-news', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'news.tvbs.com.tw', 'outlet_name' => 'TVBS新聞網', 'outlet_slug' => 'tvbs-news', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.nownews.com', 'outlet_name' => 'NOWnews今日新聞', 'outlet_slug' => 'nownews', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.taisounds.com', 'outlet_name' => '太報', 'outlet_slug' => 'taisounds', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.upmedia.mg', 'outlet_name' => '上報', 'outlet_slug' => 'upmedia', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'tw.nextapple.com', 'outlet_name' => '壹蘋新聞網', 'outlet_slug' => 'nextapple-tw', 'blocked_path_pattern' => $blockedLists],
        ];

        foreach (['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be'] as $youtubeDomain) {
            $domains[] = [
                'domain' => $youtubeDomain,
                'outlet_name' => 'YouTube',
                'outlet_slug' => 'youtube',
                'outlet_type' => 'video_platform',
                'region' => 'global',
                'article_url_pattern' => '^/(watch|shorts/|live/)',
                'list_url_pattern' => '^/(feed|channel|@|results|playlist|shorts/?$)',
                'blocked_path_pattern' => '^/(?:$|feed|results|playlist|account|signin|premium|gaming|music)',
                'priority' => 20,
                'notes' => 'Video platform support; actual news channels are refined through community reports.',
            ];
        }

        return array_map(function (array $domain): array {
            $domain['outlet_slug'] = $domain['outlet_slug'] ?? Str::slug($domain['outlet_name']);

            return $domain;
        }, $domains);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function youtubeChannels(): array
    {
        return [
            [
                'outlet_slug' => 'cna',
                'handle' => 'cnataiwan',
                'title' => '中央社 CNA',
                'channel_url' => 'https://www.youtube.com/user/cnataiwan',
                'notes' => 'Official Central News Agency YouTube channel.',
            ],
            [
                'outlet_slug' => 'pts-news',
                'handle' => 'PNNPTS',
                'title' => '公視新聞網',
                'channel_url' => 'https://www.youtube.com/@PNNPTS',
                'notes' => 'Official PTS News YouTube channel.',
            ],
            [
                'outlet_slug' => 'tvbs-news',
                'handle' => 'TVBSNEWS01',
                'title' => 'TVBS NEWS',
                'channel_url' => 'https://www.youtube.com/@TVBSNEWS01',
                'notes' => 'Official TVBS News YouTube channel.',
            ],
            [
                'outlet_slug' => 'setn',
                'handle' => 'setnews',
                'title' => '三立LIVE新聞',
                'channel_url' => 'https://www.youtube.com/@setnews',
                'notes' => 'Official SET News YouTube channel.',
            ],
            [
                'outlet_slug' => 'ftv-news',
                'handle' => 'FTVCP',
                'title' => '民視新聞網 Formosa TV News network',
                'channel_url' => 'https://www.youtube.com/@FTVCP',
                'notes' => 'Official FTV News YouTube channel.',
            ],
            [
                'outlet_slug' => 'ettoday',
                'handle' => 'ETtoday',
                'title' => 'ETtoday新聞雲',
                'channel_url' => 'https://www.youtube.com/@ETtoday',
                'notes' => 'Official ETtoday YouTube channel.',
            ],
            [
                'outlet_slug' => 'ltn',
                'handle' => 'LTNNews',
                'title' => '自由時報電子報',
                'channel_url' => 'https://www.youtube.com/@LTNNews',
                'notes' => 'Official Liberty Times YouTube channel.',
            ],
            [
                'outlet_slug' => 'chinatimes',
                'handle' => 'ChinaTimes',
                'title' => '中時新聞網',
                'channel_url' => 'https://www.youtube.com/@ChinaTimes',
                'notes' => 'Official China Times YouTube channel.',
            ],
            [
                'outlet_slug' => 'udn',
                'handle' => 'udnvideo',
                'title' => '聯合影音',
                'channel_url' => 'https://www.youtube.com/@udnvideo',
                'notes' => 'Official UDN video YouTube channel.',
            ],
            [
                'outlet_slug' => 'rti',
                'handle' => 'RTIofficial',
                'title' => '中央廣播電臺 RTI',
                'channel_url' => 'https://www.youtube.com/@RTIofficial',
                'channel_type' => 'public_affairs',
                'notes' => 'Official Radio Taiwan International YouTube channel.',
            ],
        ];
    }
}
