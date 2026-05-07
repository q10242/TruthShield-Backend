<?php

namespace Database\Seeders;

use App\Models\MediaOutlet;
use App\Models\Badge;
use App\Models\NewsDomain;
use App\Models\SystemSetting;
use App\Models\User;
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
            $outlet = MediaOutlet::query()->firstOrCreate(
                ['slug' => Str::slug($domain)],
                ['name' => $domain, 'type' => 'news', 'region' => 'TW', 'is_active' => true],
            );

            NewsDomain::query()->updateOrCreate(
                ['domain' => $domain],
                ['media_outlet_id' => $outlet->id, 'is_active' => true],
            );
        }

        User::firstOrCreate(
            ['email' => 'tester@truthshield.local'],
            [
                'name' => 'TruthShield Tester',
                'email' => 'tester@truthshield.local',
                'password' => Hash::make('password123'),
                'auth_provider' => 'dev',
                'trust_score' => 1.0,
                'email_verified_at' => now(),
            ],
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@truthshield.local'],
            [
                'name' => 'TruthShield Admin',
                'password' => Hash::make('admin123456'),
                'is_admin' => true,
                'trust_score' => 3.0,
                'auth_provider' => 'dev',
                'email_verified_at' => now(),
            ],
        );
    }
}
