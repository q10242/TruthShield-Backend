<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_urls', function (Blueprint $table) {
            $table->timestamp('voting_closes_at')->nullable()->after('title_snapshot');
            $table->timestamp('finalized_at')->nullable()->after('voting_closes_at');
            $table->json('final_status_payload')->nullable()->after('finalized_at');
            $table->json('final_evidence_payload')->nullable()->after('final_status_payload');
        });

        DB::table('news_urls')
            ->whereNull('voting_closes_at')
            ->orderBy('id')
            ->get(['id', 'created_at'])
            ->each(function ($newsUrl): void {
                DB::table('news_urls')
                    ->where('id', $newsUrl->id)
                    ->update([
                        'voting_closes_at' => \Illuminate\Support\Carbon::parse($newsUrl->created_at)->addHours(72),
                    ]);
            });

        DB::statement('delete from votes where id not in (select max(id) from votes group by user_id, news_url_id)');

        Schema::table('votes', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'news_url_id', 'tag_id']);
            $table->unique(['user_id', 'news_url_id']);
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'news_url_id']);
            $table->unique(['user_id', 'news_url_id', 'tag_id']);
        });

        Schema::table('news_urls', function (Blueprint $table) {
            $table->dropColumn([
                'voting_closes_at',
                'finalized_at',
                'final_status_payload',
                'final_evidence_payload',
            ]);
        });
    }
};
