<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_clusters', function (Blueprint $table): void {
            $table->id();
            $table->string('canonical_hash', 64)->nullable()->unique();
            $table->string('content_hash', 64)->nullable()->index();
            $table->string('source_host')->nullable()->index();
            $table->string('title_key', 180)->nullable()->index();
            $table->string('canonical_title')->nullable();
            $table->unsignedInteger('url_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('last_matched_at')->nullable();
            $table->timestamps();

            $table->index(['source_host', 'title_key']);
        });

        Schema::table('news_urls', function (Blueprint $table): void {
            $table->foreignId('news_cluster_id')
                ->nullable()
                ->after('media_outlet_id')
                ->constrained('news_clusters')
                ->nullOnDelete();
        });

        Schema::table('evidence_reactions', function (Blueprint $table): void {
            $table->unsignedTinyInteger('credibility')->nullable()->after('helpful');
            $table->unsignedTinyInteger('relevance')->nullable()->after('credibility');
            $table->string('direction', 40)->default('contextual')->after('relevance');
            $table->index(['vote_id', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::table('evidence_reactions', function (Blueprint $table): void {
            $table->dropIndex(['vote_id', 'direction']);
            $table->dropColumn(['credibility', 'relevance', 'direction']);
        });

        Schema::table('news_urls', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('news_cluster_id');
        });

        Schema::dropIfExists('news_clusters');
    }
};
