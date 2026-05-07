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
                'translations' => [
                    'zh-TW' => ['name' => '標題殺人', 'description' => '標題誇大、扭曲，或與內文重點不一致。'],
                    'en' => ['name' => 'Clickbait headline', 'description' => 'Headline overstates, distorts, or does not match the body.'],
                ],
            ],
            [
                'name' => '隱瞞事實',
                'slug' => 'missing-facts',
                'color' => '#f97316',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'Key facts are omitted in a way that changes interpretation.',
                'translations' => [
                    'zh-TW' => ['name' => '隱瞞事實', 'description' => '省略關鍵事實，導致讀者對事件產生不同理解。'],
                    'en' => ['name' => 'Missing key facts', 'description' => 'Key facts are omitted in a way that changes interpretation.'],
                ],
            ],
            [
                'name' => '斷章取義',
                'slug' => 'out-of-context',
                'color' => '#f59e0b',
                'severity' => 'medium',
                'requires_evidence' => true,
                'description' => 'Quotes, evidence, or events are presented without necessary context.',
                'translations' => [
                    'zh-TW' => ['name' => '斷章取義', 'description' => '引用、證據或事件缺少必要脈絡。'],
                    'en' => ['name' => 'Out of context', 'description' => 'Quotes, evidence, or events are presented without necessary context.'],
                ],
            ],
            [
                'name' => '缺乏平衡報導',
                'slug' => 'lack-of-balance',
                'color' => '#f97316',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'A controversial or public-interest story omits major stakeholder perspectives or response opportunities.',
                'translations' => [
                    'zh-TW' => ['name' => '缺乏平衡報導', 'description' => '爭議或公共利益新聞缺少主要利害關係人的觀點或回應機會。'],
                    'en' => ['name' => 'Lack of balance', 'description' => 'A controversial or public-interest story omits major stakeholder perspectives or response opportunities.'],
                ],
            ],
            [
                'name' => '單一消息來源',
                'slug' => 'single-source',
                'color' => '#f59e0b',
                'severity' => 'medium',
                'requires_evidence' => true,
                'description' => 'The report relies on only one source or side without meaningful corroboration.',
                'translations' => [
                    'zh-TW' => ['name' => '單一消息來源', 'description' => '報導只依賴單一來源或單方說法，缺少有效交叉查證。'],
                    'en' => ['name' => 'Single source', 'description' => 'The report relies on only one source or side without meaningful corroboration.'],
                ],
            ],
            [
                'name' => '未區分事實與評論',
                'slug' => 'fact-opinion-blurring',
                'color' => '#f59e0b',
                'severity' => 'medium',
                'requires_evidence' => true,
                'description' => 'Commentary, speculation, or value judgments are presented as straight news facts.',
                'translations' => [
                    'zh-TW' => ['name' => '未區分事實與評論', 'description' => '將評論、推測或價值判斷包裝成新聞事實。'],
                    'en' => ['name' => 'Fact/opinion blurring', 'description' => 'Commentary, speculation, or value judgments are presented as straight news facts.'],
                ],
            ],
            [
                'name' => '數據誤導',
                'slug' => 'misleading-data',
                'color' => '#ef4444',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'Numbers, charts, samples, baselines, or time windows are framed in a way that misleads readers.',
                'translations' => [
                    'zh-TW' => ['name' => '數據誤導', 'description' => '數字、圖表、樣本、比較基準或時間區間的呈現方式容易誤導讀者。'],
                    'en' => ['name' => 'Misleading data', 'description' => 'Numbers, charts, samples, baselines, or time windows are framed in a way that misleads readers.'],
                ],
            ],
            [
                'name' => '圖片/影片脈絡不明',
                'slug' => 'unclear-media-context',
                'color' => '#f59e0b',
                'severity' => 'medium',
                'requires_evidence' => true,
                'description' => 'Images, video, screenshots, or file photos lack clear source, time, location, or illustration labels.',
                'translations' => [
                    'zh-TW' => ['name' => '圖片/影片脈絡不明', 'description' => '圖片、影片、截圖或資料照缺少清楚來源、時間、地點或示意標示。'],
                    'en' => ['name' => 'Unclear media context', 'description' => 'Images, video, screenshots, or file photos lack clear source, time, location, or illustration labels.'],
                ],
            ],
            [
                'name' => '內容農場',
                'slug' => 'content-farm',
                'color' => '#dc2626',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'Low-cost rewritten, scraped, translated, or SEO-driven content with weak sourcing or accountability.',
                'translations' => [
                    'zh-TW' => ['name' => '內容農場', 'description' => '低成本改寫、搬運、翻譯或 SEO 導向內容，來源與責任歸屬薄弱。'],
                    'en' => ['name' => 'Content farm', 'description' => 'Low-cost rewritten, scraped, translated, or SEO-driven content with weak sourcing or accountability.'],
                ],
            ],
            [
                'name' => '未揭露業配',
                'slug' => 'undisclosed-sponsored-content',
                'color' => '#dc2626',
                'severity' => 'high',
                'requires_evidence' => true,
                'description' => 'Commercial, political, brand, or sponsored interests appear in the story without clear disclosure.',
                'translations' => [
                    'zh-TW' => ['name' => '未揭露業配', 'description' => '商業、政治、品牌或贊助利益出現在報導中，卻沒有清楚揭露。'],
                    'en' => ['name' => 'Undisclosed sponsored content', 'description' => 'Commercial, political, brand, or sponsored interests appear in the story without clear disclosure.'],
                ],
            ],
            [
                'name' => '事實準確',
                'slug' => 'accurate-reporting',
                'color' => '#22c55e',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'The report is well-supported and materially accurate.',
                'translations' => [
                    'zh-TW' => ['name' => '事實準確', 'description' => '報導有充分依據，且主要事實正確。'],
                    'en' => ['name' => 'Accurate reporting', 'description' => 'The report is well-supported and materially accurate.'],
                ],
            ],
            [
                'name' => '來源清楚',
                'slug' => 'clear-sourcing',
                'color' => '#10b981',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'Sources, documents, interviewees, datasets, or official records are clearly identified.',
                'translations' => [
                    'zh-TW' => ['name' => '來源清楚', 'description' => '清楚標示來源、文件、受訪者、資料集或官方紀錄。'],
                    'en' => ['name' => 'Clear sourcing', 'description' => 'Sources, documents, interviewees, datasets, or official records are clearly identified.'],
                ],
            ],
            [
                'name' => '多方查證',
                'slug' => 'multi-source-verification',
                'color' => '#14b8a6',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'The report cross-checks claims across multiple independent sources or viewpoints.',
                'translations' => [
                    'zh-TW' => ['name' => '多方查證', 'description' => '透過多個獨立來源或觀點交叉查證主張。'],
                    'en' => ['name' => 'Multi-source verification', 'description' => 'The report cross-checks claims across multiple independent sources or viewpoints.'],
                ],
            ],
            [
                'name' => '背景完整',
                'slug' => 'complete-context',
                'color' => '#84cc16',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'The story explains relevant history, data baselines, institutional context, or limitations.',
                'translations' => [
                    'zh-TW' => ['name' => '背景完整', 'description' => '說明相關歷史、數據基準、制度脈絡或限制。'],
                    'en' => ['name' => 'Complete context', 'description' => 'The story explains relevant history, data baselines, institutional context, or limitations.'],
                ],
            ],
            [
                'name' => '利益揭露完整',
                'slug' => 'transparent-disclosure',
                'color' => '#22c55e',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'Sponsorship, advertising, cooperation, conflicts of interest, or relevant affiliations are clearly disclosed.',
                'translations' => [
                    'zh-TW' => ['name' => '利益揭露完整', 'description' => '清楚揭露贊助、廣告、合作、利益衝突或相關關係。'],
                    'en' => ['name' => 'Transparent disclosure', 'description' => 'Sponsorship, advertising, cooperation, conflicts of interest, or relevant affiliations are clearly disclosed.'],
                ],
            ],
        ];

        foreach ($tags as $tag) {
            Tag::query()->updateOrCreate(['slug' => $tag['slug']], $tag);
        }
    }
}
