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
use App\Services\AchievementService;
use App\Support\ExtensionSelectorFixtures;
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
        $badges = collect(app(AchievementService::class)->definitions())
            ->map(fn (array $definition): array => [
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'description' => $definition['description'],
                'color' => $definition['color'],
            ])
            ->merge([
                ['name' => '逆風觀察者', 'slug' => 'contrarian-scout', 'description' => '在定案前提出少數但有價值的判斷。', 'color' => '#fbbf24'],
                ['name' => '濫用觀察員', 'slug' => 'abuse-observer', 'description' => '協助辨識異常投票與協同行為。', 'color' => '#fb923c'],
                ['name' => '可信審查者', 'slug' => 'trusted-reviewer', 'description' => '長期提供高品質查證與證據評價。', 'color' => '#60a5fa'],
            ])
            ->unique('slug')
            ->values();

        foreach ($badges as $badge) {
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
        $selectorFixtures = ExtensionSelectorFixtures::byDomain();

        foreach ($this->newsDomains() as $domain) {
            $domain = array_replace($selectorFixtures[$domain['domain']] ?? [], $domain);

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
            $outlet = isset($channel['outlet_name'])
                ? MediaOutlet::query()->updateOrCreate(
                    ['slug' => $channel['outlet_slug']],
                    [
                        'name' => $channel['outlet_name'],
                        'type' => $channel['outlet_type'] ?? 'news',
                        'region' => $channel['region'] ?? 'GLOBAL',
                        'is_active' => true,
                        'notes' => $channel['outlet_notes'] ?? 'YouTube-only baseline media outlet.',
                    ],
                )
                : MediaOutlet::query()->where('slug', $channel['outlet_slug'])->first();
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
        $blockedLists = '^/(?:$|search|tag|tags|tagging|category|categories|cat|cate|topic|topics|author|authors|member|login|register|privacy|about|rss|(?:list|lists|latest|realtime|realtimenews|breaking|breaknews|hot|popular|archive|archives|video|videos|photo|photos|live)/?$)';

        $domains = [
            ['domain' => 'cna.com.tw', 'outlet_name' => '中央社', 'outlet_slug' => 'cna', 'article_url_pattern' => '^/news/.+\\.aspx$', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.cna.com.tw', 'outlet_name' => '中央社', 'outlet_slug' => 'cna', 'article_url_pattern' => '^/news/.+\\.aspx$', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'news.pts.org.tw', 'outlet_name' => '公視新聞網', 'outlet_slug' => 'pts-news', 'article_url_pattern' => '^/article/\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.cw.com.tw', 'outlet_name' => '天下雜誌', 'outlet_slug' => 'commonwealth', 'article_url_pattern' => '^/article/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.businessweekly.com.tw', 'outlet_name' => '商業周刊', 'outlet_slug' => 'businessweekly', 'article_url_pattern' => '^/(?:business|international|management|careers|style|health|archive)/blog/\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'udn.com', 'outlet_name' => '聯合新聞網', 'outlet_slug' => 'udn', 'article_url_pattern' => '^/news/story/\\d+/\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'money.udn.com', 'outlet_name' => '經濟日報', 'outlet_slug' => 'economic-daily-news', 'article_url_pattern' => '^/money/story/\\d+/\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.chinatimes.com', 'outlet_name' => '中時新聞網', 'outlet_slug' => 'chinatimes', 'article_url_pattern' => '^/(?:realtimenews|newspapers)/\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'ctinews.com', 'outlet_name' => '中天新聞網', 'outlet_slug' => 'cti-news', 'article_url_pattern' => '^/news/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.ctinews.com', 'outlet_name' => '中天新聞網', 'outlet_slug' => 'cti-news', 'article_url_pattern' => '^/news/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'news.cts.com.tw', 'outlet_name' => '華視新聞網', 'outlet_slug' => 'cts-news', 'article_url_pattern' => '\\.html$', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.ettoday.net', 'outlet_name' => 'ETtoday新聞雲', 'outlet_slug' => 'ettoday', 'article_url_pattern' => '^/news/\\d+/.+\\.htm$', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.setn.com', 'outlet_name' => '三立新聞網', 'outlet_slug' => 'setn', 'article_url_pattern' => '^/News\\.aspx$', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'news.ltn.com.tw', 'outlet_name' => '自由時報', 'outlet_slug' => 'ltn', 'article_url_pattern' => '^/news/.+/(?:breakingnews/)?\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'art.ltn.com.tw', 'outlet_name' => '自由藝文網', 'outlet_slug' => 'ltn-art', 'article_url_pattern' => '^/article/(?:breakingnews/)?\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'def.ltn.com.tw', 'outlet_name' => '自由軍武頻道', 'outlet_slug' => 'ltn-defense', 'article_url_pattern' => '^/article/(?:breakingnews/)?\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'tw.news.yahoo.com', 'outlet_name' => 'Yahoo奇摩新聞', 'outlet_slug' => 'yahoo-news-tw', 'article_url_pattern' => '\\.html$', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.storm.mg', 'outlet_name' => '風傳媒', 'outlet_slug' => 'storm-media', 'article_url_pattern' => '^/article/\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.thenewslens.com', 'outlet_name' => '關鍵評論網', 'outlet_slug' => 'the-news-lens', 'article_url_pattern' => '^/(?:article|feature)/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.mirrormedia.mg', 'outlet_name' => '鏡週刊', 'outlet_slug' => 'mirror-media', 'article_url_pattern' => '^/(?:story|external)/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.rti.org.tw', 'outlet_name' => '中央廣播電臺', 'outlet_slug' => 'rti', 'article_url_pattern' => '^(?:/news/view/id/\\d+|/news\\?pid=\\d+)', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.ftvnews.com.tw', 'outlet_name' => '民視新聞網', 'outlet_slug' => 'ftv-news', 'article_url_pattern' => '^/news/detail/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'news.tvbs.com.tw', 'outlet_name' => 'TVBS新聞網', 'outlet_slug' => 'tvbs-news', 'article_url_pattern' => '^/[^/]+/\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.nownews.com', 'outlet_name' => 'NOWnews今日新聞', 'outlet_slug' => 'nownews', 'article_url_pattern' => '^/news/\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.taisounds.com', 'outlet_name' => '太報', 'outlet_slug' => 'taisounds', 'article_url_pattern' => '^/news/content/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.upmedia.mg', 'outlet_name' => '上報', 'outlet_slug' => 'upmedia', 'article_url_pattern' => '^/news_info\\.php', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'tw.nextapple.com', 'outlet_name' => '壹蘋新聞網', 'outlet_slug' => 'nextapple-tw', 'article_url_pattern' => '^/(?:realtime|local|politics|life|entertainment|international|finance|sports)/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.businesstoday.com.tw', 'outlet_name' => '今周刊', 'outlet_slug' => 'business-today', 'article_url_pattern' => '^/article/category/\\d+/post/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'finance.ettoday.net', 'outlet_name' => 'ETtoday財經雲', 'outlet_slug' => 'ettoday-finance', 'article_url_pattern' => '^/news/\\d+/.+\\.htm$', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.peoplenews.tw', 'outlet_name' => '民報', 'outlet_slug' => 'people-news', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'news.ebc.net.tw', 'outlet_name' => '東森新聞', 'outlet_slug' => 'ebc-news', 'article_url_pattern' => '^/news/[^/]+/\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'cava.tw', 'outlet_name' => '造咖', 'outlet_slug' => 'cava', 'outlet_notes' => '東森電視事業旗下內容站。', 'article_url_pattern' => '^/(?:topic|lifestyle|money|fashion|beauty|fitness|entertainment|coverstory|survey)(?:/[^/]+)?/\\d+', 'blocked_path_pattern' => '^/(?:$|search|tag|tags|tagging|category|categories|cat|cate|topics|author|authors|member|login|register|privacy|about|rss|(?:list|lists|latest|realtime|realtimenews|breaking|breaknews|hot|popular|archive|archives|video|videos|photo|photos|live)/?$)'],
            ['domain' => 'www.nexttv.com.tw', 'outlet_name' => '壹電視', 'outlet_slug' => 'next-tv', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.taiwannews.com.tw', 'outlet_name' => 'Taiwan News', 'outlet_slug' => 'taiwan-news', 'article_url_pattern' => '^/news/\\d+', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'english.cw.com.tw', 'outlet_name' => 'CommonWealth Magazine English', 'outlet_slug' => 'commonwealth-english', 'article_url_pattern' => '^/article/article\\.action', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.taiwanplus.com', 'outlet_name' => 'TaiwanPlus', 'outlet_slug' => 'taiwan-plus', 'article_url_pattern' => '^/news/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.taipeitimes.com', 'outlet_name' => 'Taipei Times', 'outlet_slug' => 'taipei-times', 'article_url_pattern' => '^/News/.+/archives/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.mnews.tw', 'outlet_name' => '鏡新聞', 'outlet_slug' => 'mnews', 'article_url_pattern' => '^/story/', 'blocked_path_pattern' => $blockedLists],
            ['domain' => 'www.cmmedia.com.tw', 'outlet_name' => '信傳媒', 'outlet_slug' => 'cmmedia', 'article_url_pattern' => '^/home/articles/', 'blocked_path_pattern' => $blockedLists],
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
                'handle' => null,
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
            [
                'outlet_slug' => 'cti-news',
                'outlet_name' => '中天新聞',
                'region' => 'TW',
                'handle' => null,
                'channel_id' => 'UCpu3bemTQwAU8PqM4kJdoEQ',
                'title' => '中天新聞',
                'channel_url' => 'https://www.youtube.com/channel/UCpu3bemTQwAU8PqM4kJdoEQ',
                'notes' => 'CTI News YouTube channel seeded by stable channel ID because the public handle uses mixed Chinese/Latin characters.',
            ],
            [
                'outlet_slug' => 'ebc-news',
                'outlet_name' => '東森新聞',
                'region' => 'TW',
                'handle' => 'newsebc',
                'channel_id' => 'UCR3asjvr_WAaxwJYEDV_Bfw',
                'title' => '東森新聞 CH51',
                'channel_url' => 'https://www.youtube.com/@newsebc',
                'notes' => 'Official EBC News CH51 YouTube channel.',
            ],
            [
                'outlet_slug' => 'cnn',
                'outlet_name' => 'CNN',
                'region' => 'US',
                'handle' => 'CNN',
                'title' => 'CNN',
                'channel_url' => 'https://www.youtube.com/@CNN',
                'notes' => 'International mainstream news channel.',
            ],
            [
                'outlet_slug' => 'bbc-news',
                'outlet_name' => 'BBC News',
                'region' => 'GB',
                'handle' => 'BBCNews',
                'title' => 'BBC News',
                'channel_url' => 'https://www.youtube.com/@BBCNews',
                'notes' => 'International public-service news channel.',
            ],
            [
                'outlet_slug' => 'reuters',
                'outlet_name' => 'Reuters',
                'region' => 'GB',
                'handle' => 'Reuters',
                'title' => 'Reuters',
                'channel_url' => 'https://www.youtube.com/@Reuters',
                'notes' => 'International wire-service news channel.',
            ],
            [
                'outlet_slug' => 'associated-press',
                'outlet_name' => 'Associated Press',
                'region' => 'US',
                'handle' => 'AssociatedPress',
                'title' => 'Associated Press',
                'channel_url' => 'https://www.youtube.com/@AssociatedPress',
                'notes' => 'International wire-service news channel.',
            ],
            [
                'outlet_slug' => 'al-jazeera-english',
                'outlet_name' => 'Al Jazeera English',
                'region' => 'QA',
                'handle' => 'aljazeeraenglish',
                'title' => 'Al Jazeera English',
                'channel_url' => 'https://www.youtube.com/@aljazeeraenglish',
                'notes' => 'International news channel.',
            ],
            [
                'outlet_slug' => 'dw-news',
                'outlet_name' => 'DW News',
                'region' => 'DE',
                'handle' => 'dwnews',
                'title' => 'DW News',
                'channel_url' => 'https://www.youtube.com/@dwnews',
                'notes' => 'International public-service news channel.',
            ],
            [
                'outlet_slug' => 'france-24-english',
                'outlet_name' => 'France 24 English',
                'region' => 'FR',
                'handle' => 'France24_en',
                'title' => 'France 24 English',
                'channel_url' => 'https://www.youtube.com/@France24_en',
                'notes' => 'International public-service news channel.',
            ],
            [
                'outlet_slug' => 'sky-news',
                'outlet_name' => 'Sky News',
                'region' => 'GB',
                'handle' => 'SkyNews',
                'title' => 'Sky News',
                'channel_url' => 'https://www.youtube.com/@SkyNews',
                'notes' => 'International mainstream news channel.',
            ],
            [
                'outlet_slug' => 'pbs-newshour',
                'outlet_name' => 'PBS NewsHour',
                'region' => 'US',
                'handle' => 'PBSNewsHour',
                'title' => 'PBS NewsHour',
                'channel_url' => 'https://www.youtube.com/@PBSNewsHour',
                'notes' => 'US public-media news channel.',
            ],
            [
                'outlet_slug' => 'abc-news',
                'outlet_name' => 'ABC News',
                'region' => 'US',
                'handle' => 'ABCNews',
                'title' => 'ABC News',
                'channel_url' => 'https://www.youtube.com/@ABCNews',
                'notes' => 'US mainstream news channel.',
            ],
            [
                'outlet_slug' => 'cbs-news',
                'outlet_name' => 'CBS News',
                'region' => 'US',
                'handle' => 'CBSNews',
                'title' => 'CBS News',
                'channel_url' => 'https://www.youtube.com/@CBSNews',
                'notes' => 'US mainstream news channel.',
            ],
            [
                'outlet_slug' => 'nbc-news',
                'outlet_name' => 'NBC News',
                'region' => 'US',
                'handle' => 'NBCNews',
                'title' => 'NBC News',
                'channel_url' => 'https://www.youtube.com/@NBCNews',
                'notes' => 'US mainstream news channel.',
            ],
            [
                'outlet_slug' => 'nhk-world-japan',
                'outlet_name' => 'NHK WORLD-JAPAN',
                'region' => 'JP',
                'handle' => 'NHKWORLDJAPAN',
                'title' => 'NHK WORLD-JAPAN',
                'channel_url' => 'https://www.youtube.com/@NHKWORLDJAPAN',
                'notes' => 'Japanese public-service international news channel.',
            ],
            [
                'outlet_slug' => 'channel-news-asia',
                'outlet_name' => 'CNA / Channel NewsAsia',
                'region' => 'SG',
                'handle' => 'channelnewsasia',
                'title' => 'CNA',
                'channel_url' => 'https://www.youtube.com/@channelnewsasia',
                'notes' => 'Singapore-based mainstream news channel.',
            ],
            [
                'outlet_slug' => 'south-china-morning-post',
                'outlet_name' => 'South China Morning Post',
                'region' => 'HK',
                'handle' => 'SouthChinaMorningPost',
                'title' => 'South China Morning Post',
                'channel_url' => 'https://www.youtube.com/@SouthChinaMorningPost',
                'notes' => 'Hong Kong-based mainstream news channel.',
            ],
        ];
    }
}
