<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('algorithm_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('status', 40)->default('active');
            $table->text('summary');
            $table->json('rules')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('trust_settlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_url_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->string('algorithm_version', 40);
            $table->float('delta');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['news_url_id', 'user_id', 'vote_id', 'algorithm_version'], 'trust_settlement_unique');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('abuse_clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_url_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 80);
            $table->string('severity', 40)->default('medium');
            $table->unsignedInteger('user_count')->default(0);
            $table->unsignedInteger('event_count')->default(0);
            $table->json('metadata')->nullable();
            $table->boolean('reviewed')->default(false);
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['reviewed', 'severity']);
        });

        Schema::create('evidence_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->string('status', 40)->default('pending');
            $table->string('archive_url', 2048)->nullable();
            $table->string('preview_url', 2048)->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::table('news_domains', function (Blueprint $table) {
            $table->string('title_selector', 500)->nullable()->after('article_selector');
            $table->string('content_selector', 500)->nullable()->after('title_selector');
            $table->string('blocked_path_pattern', 500)->nullable()->after('content_selector');
        });
    }

    public function down(): void
    {
        Schema::table('news_domains', function (Blueprint $table) {
            $table->dropColumn(['title_selector', 'content_selector', 'blocked_path_pattern']);
        });

        Schema::dropIfExists('evidence_snapshots');
        Schema::dropIfExists('abuse_clusters');
        Schema::dropIfExists('trust_settlements');
        Schema::dropIfExists('algorithm_versions');
    }
};
