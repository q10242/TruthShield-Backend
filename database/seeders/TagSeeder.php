<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            [
                'name' => '標題殺人',
                'slug' => 'clickbait-title',
                'color' => '#ef4444',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'Headline overstates, distorts, or does not match the body.',
            ],
            [
                'name' => '隱瞞事實',
                'slug' => 'missing-facts',
                'color' => '#f97316',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'Key facts are omitted in a way that changes interpretation.',
            ],
            [
                'name' => '斷章取義',
                'slug' => 'out-of-context',
                'color' => '#f59e0b',
                'severity' => 'medium',
                'requires_evidence' => true,
                'description' => 'Quotes, evidence, or events are presented without necessary context.',
            ],
            [
                'name' => '缺乏平衡報導',
                'slug' => 'lack-of-balance',
                'color' => '#f97316',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'A controversial or public-interest story omits major stakeholder perspectives or response opportunities.',
            ],
            [
                'name' => '單一消息來源',
                'slug' => 'single-source',
                'color' => '#f59e0b',
                'severity' => 'medium',
                'requires_evidence' => true,
                'description' => 'The report relies on only one source or side without meaningful corroboration.',
            ],
            [
                'name' => '未區分事實與評論',
                'slug' => 'fact-opinion-blurring',
                'color' => '#f59e0b',
                'severity' => 'medium',
                'requires_evidence' => true,
                'description' => 'Commentary, speculation, or value judgments are presented as straight news facts.',
            ],
            [
                'name' => '數據誤導',
                'slug' => 'misleading-data',
                'color' => '#ef4444',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'Numbers, charts, samples, baselines, or time windows are framed in a way that misleads readers.',
            ],
            [
                'name' => '圖片/影片脈絡不明',
                'slug' => 'unclear-media-context',
                'color' => '#f59e0b',
                'severity' => 'medium',
                'requires_evidence' => true,
                'description' => 'Images, video, screenshots, or file photos lack clear source, time, location, or illustration labels.',
            ],
            [
                'name' => '內容農場',
                'slug' => 'content-farm',
                'color' => '#dc2626',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'Low-cost rewritten, scraped, translated, or SEO-driven content with weak sourcing or accountability.',
            ],
            [
                'name' => '未揭露業配',
                'slug' => 'undisclosed-sponsored-content',
                'color' => '#dc2626',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'Commercial, political, brand, or sponsored interests appear in the story without clear disclosure.',
            ],
            [
                'name' => '事實準確',
                'slug' => 'accurate-reporting',
                'color' => '#22c55e',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'The report is well-supported and materially accurate.',
            ],
            [
                'name' => '來源清楚',
                'slug' => 'clear-sourcing',
                'color' => '#10b981',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'Sources, documents, interviewees, datasets, or official records are clearly identified.',
            ],
            [
                'name' => '多方查證',
                'slug' => 'multi-source-verification',
                'color' => '#14b8a6',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'The report cross-checks claims across multiple independent sources or viewpoints.',
            ],
            [
                'name' => '背景完整',
                'slug' => 'complete-context',
                'color' => '#84cc16',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'The story explains relevant history, data baselines, institutional context, or limitations.',
            ],
            [
                'name' => '利益揭露完整',
                'slug' => 'transparent-disclosure',
                'color' => '#22c55e',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'Sponsorship, advertising, cooperation, conflicts of interest, or relevant affiliations are clearly disclosed.',
            ],
        ];

        foreach ($tags as $tag) {
            Tag::query()->updateOrCreate(['slug' => $tag['slug']], $tag);
        }
    }
}
