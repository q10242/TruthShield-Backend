<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_outlets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 40)->nullable();
            $table->string('region', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('news_domains', function (Blueprint $table) {
            $table->foreignId('media_outlet_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('news_urls', function (Blueprint $table) {
            $table->foreignId('media_outlet_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('title_snapshot');
        });

        Schema::create('trust_score_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('news_url_id')->nullable()->constrained()->nullOnDelete();
            $table->float('previous_score');
            $table->float('delta');
            $table->float('new_score');
            $table->string('reason', 80);
            $table->string('details', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('reason');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('abuse_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('news_url_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 80);
            $table->string('severity', 24)->default('low');
            $table->json('metadata')->nullable();
            $table->boolean('reviewed')->default(false);
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['reviewed', 'severity']);
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->boolean('hidden')->default(false)->after('weight_score');
            $table->string('moderation_status', 24)->default('visible')->after('hidden');
        });

        Schema::create('evidence_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason', 80);
            $table->string('note', 500)->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->unique(['vote_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_reports');

        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn(['hidden', 'moderation_status']);
        });

        Schema::dropIfExists('abuse_events');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('trust_score_histories');

        Schema::table('news_urls', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_outlet_id');
            $table->dropColumn('published_at');
        });

        Schema::table('news_domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('media_outlet_id');
        });

        Schema::dropIfExists('media_outlets');
    }
};
