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
                'name' => '事實準確',
                'slug' => 'accurate-reporting',
                'color' => '#22c55e',
                'severity' => 'positive',
                'requires_evidence' => false,
                'description' => 'The report is well-supported and materially accurate.',
            ],
        ];

        foreach ($tags as $tag) {
            Tag::query()->updateOrCreate(['slug' => $tag['slug']], $tag);
        }
    }
}
