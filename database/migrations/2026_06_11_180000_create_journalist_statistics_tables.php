<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journalists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_outlet_id')->nullable()->constrained('media_outlets')->nullOnDelete();
            $table->string('display_name');
            $table->string('canonical_name')->index();
            $table->text('description')->nullable();
            $table->string('status', 40)->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
            $table->unique(['canonical_name', 'media_outlet_id'], 'journalists_canonical_media_unique');
        });

        Schema::create('journalist_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journalist_id')->constrained('journalists')->cascadeOnDelete();
            $table->string('alias');
            $table->string('domain')->nullable()->index();
            $table->string('confidence', 20)->default('high');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['journalist_id', 'alias', 'domain'], 'journalist_alias_unique');
        });

        Schema::create('journalist_news_url', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journalist_id')->constrained('journalists')->cascadeOnDelete();
            $table->foreignId('news_url_id')->constrained('news_urls')->cascadeOnDelete();
            $table->string('match_source', 40)->index();
            $table->string('matched_text', 320)->nullable();
            $table->string('confidence', 20)->default('low')->index();
            $table->string('review_status', 40)->default('suspected')->index();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('rejected_reason', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['journalist_id', 'news_url_id'], 'journalist_news_unique');
            $table->index(['news_url_id', 'review_status']);
            $table->index(['journalist_id', 'review_status', 'confidence'], 'journalist_match_stats_idx');
        });

        Schema::create('journalist_match_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journalist_id')->nullable()->constrained('journalists')->cascadeOnDelete();
            $table->string('alias')->nullable()->index();
            $table->string('domain')->nullable()->index();
            $table->foreignId('news_url_id')->nullable()->constrained('news_urls')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('news_domains', function (Blueprint $table): void {
            $table->string('author_selector', 500)->nullable()->after('content_selector');
            $table->string('author_regex', 500)->nullable()->after('author_selector');
        });
    }

    public function down(): void
    {
        Schema::table('news_domains', function (Blueprint $table): void {
            $table->dropColumn(['author_selector', 'author_regex']);
        });

        Schema::dropIfExists('journalist_match_exclusions');
        Schema::dropIfExists('journalist_news_url');
        Schema::dropIfExists('journalist_aliases');
        Schema::dropIfExists('journalists');
    }
};
