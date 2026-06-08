<?php

namespace App\Support;

class EventTaxonomy
{
    public const CATEGORIES = [
        'social_case' => ['zh' => '社會案件', 'en' => 'Social case'],
        'politics' => ['zh' => '政治事件', 'en' => 'Politics'],
        'public_policy' => ['zh' => '公共政策', 'en' => 'Public policy'],
        'legal_case' => ['zh' => '司法案件', 'en' => 'Legal case'],
        'disaster_accident' => ['zh' => '天災事故', 'en' => 'Disaster or accident'],
        'international' => ['zh' => '國際事件', 'en' => 'International'],
        'finance' => ['zh' => '財經事件', 'en' => 'Finance'],
        'technology' => ['zh' => '科技事件', 'en' => 'Technology'],
        'public_health' => ['zh' => '公衛醫療', 'en' => 'Public health'],
        'culture' => ['zh' => '娛樂文化', 'en' => 'Culture'],
        'other' => ['zh' => '其他', 'en' => 'Other'],
    ];

    public const TAGS = [
        'children' => ['zh' => '兒少', 'en' => 'Children'],
        'traffic' => ['zh' => '交通', 'en' => 'Traffic'],
        'fraud' => ['zh' => '詐騙', 'en' => 'Fraud'],
        'food_safety' => ['zh' => '食安', 'en' => 'Food safety'],
        'election' => ['zh' => '選舉', 'en' => 'Election'],
        'law_reform' => ['zh' => '修法', 'en' => 'Law reform'],
        'rescue' => ['zh' => '災害救援', 'en' => 'Rescue'],
        'celebrity' => ['zh' => '名人爭議', 'en' => 'Celebrity controversy'],
        'media_ethics' => ['zh' => '媒體倫理', 'en' => 'Media ethics'],
        'platform_governance' => ['zh' => '平台治理', 'en' => 'Platform governance'],
        'labor' => ['zh' => '勞動', 'en' => 'Labor'],
        'education' => ['zh' => '教育', 'en' => 'Education'],
        'housing' => ['zh' => '居住', 'en' => 'Housing'],
        'environment' => ['zh' => '環境', 'en' => 'Environment'],
        'corruption' => ['zh' => '貪腐廉政', 'en' => 'Corruption and integrity'],
        'procurement' => ['zh' => '採購標案', 'en' => 'Procurement'],
        'public_construction' => ['zh' => '公共工程', 'en' => 'Public construction'],
        'local_politics' => ['zh' => '地方政治', 'en' => 'Local politics'],
        'energy' => ['zh' => '能源', 'en' => 'Energy'],
        'nuclear_safety' => ['zh' => '核能安全', 'en' => 'Nuclear safety'],
        'national_security' => ['zh' => '國家安全', 'en' => 'National security'],
        'cross_strait' => ['zh' => '兩岸', 'en' => 'Cross-strait'],
    ];

    public const PROGRESS_STATUSES = [
        'collecting' => ['zh' => '蒐集中', 'en' => 'Collecting'],
        'verifying' => ['zh' => '求證中', 'en' => 'Verifying'],
        'tracking' => ['zh' => '持續追蹤', 'en' => 'Tracking'],
        'resolved' => ['zh' => '已定案', 'en' => 'Resolved'],
        'archived' => ['zh' => '已封存', 'en' => 'Archived'],
        'disputed' => ['zh' => '有爭議', 'en' => 'Disputed'],
    ];

    public static function categoryKeys(): array
    {
        return array_keys(self::CATEGORIES);
    }

    public static function tagKeys(): array
    {
        return array_keys(self::TAGS);
    }

    public static function progressStatusKeys(): array
    {
        return array_keys(self::PROGRESS_STATUSES);
    }

    public static function categoryOptions(string $locale = 'zh'): array
    {
        return self::options(self::CATEGORIES, $locale);
    }

    public static function tagOptions(string $locale = 'zh'): array
    {
        return self::options(self::TAGS, $locale);
    }

    public static function progressStatusOptions(string $locale = 'zh'): array
    {
        return self::options(self::PROGRESS_STATUSES, $locale);
    }

    public static function categoryLabel(?string $value, string $locale = 'zh'): ?string
    {
        return self::label(self::CATEGORIES, $value, $locale);
    }

    public static function tagLabel(?string $value, string $locale = 'zh'): ?string
    {
        return self::label(self::TAGS, $value, $locale);
    }

    public static function progressStatusLabel(?string $value, string $locale = 'zh'): ?string
    {
        return self::label(self::PROGRESS_STATUSES, $value, $locale);
    }

    public static function filamentCategoryOptions(): array
    {
        return collect(self::CATEGORIES)->mapWithKeys(fn (array $labels, string $key) => [$key => $labels['zh']])->all();
    }

    public static function filamentTagOptions(): array
    {
        return collect(self::TAGS)->mapWithKeys(fn (array $labels, string $key) => [$key => $labels['zh']])->all();
    }

    public static function filamentProgressStatusOptions(): array
    {
        return collect(self::PROGRESS_STATUSES)->mapWithKeys(fn (array $labels, string $key) => [$key => $labels['zh']])->all();
    }

    private static function options(array $source, string $locale): array
    {
        $lang = str_starts_with(strtolower($locale), 'en') ? 'en' : 'zh';

        return collect($source)
            ->map(fn (array $labels, string $key) => [
                'value' => $key,
                'label' => $labels[$lang],
            ])
            ->values()
            ->all();
    }

    private static function label(array $source, ?string $value, string $locale): ?string
    {
        if (! $value || ! isset($source[$value])) {
            return null;
        }

        $lang = str_starts_with(strtolower($locale), 'en') ? 'en' : 'zh';

        return $source[$value][$lang];
    }
}
