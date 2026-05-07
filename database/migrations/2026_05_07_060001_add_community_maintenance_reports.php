<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_domains', function (Blueprint $table) {
            $table->string('article_url_pattern', 500)->nullable()->after('blocked_path_pattern');
            $table->string('list_url_pattern', 500)->nullable()->after('article_url_pattern');
        });

        Schema::create('url_classification_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain', 255);
            $table->string('url', 4096);
            $table->string('path_signature', 500);
            $table->string('classification', 40);
            $table->string('suggested_pattern', 500)->nullable();
            $table->string('page_title')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 40)->default('pending');
            $table->unsignedInteger('report_count')->default(1);
            $table->float('weighted_score')->default(0);
            $table->timestamp('last_reported_at')->nullable();
            $table->timestamps();

            $table->unique(['domain', 'path_signature', 'classification', 'status'], 'url_classification_rollup_unique');
            $table->index(['status', 'weighted_score']);
            $table->index(['domain', 'status']);
        });

        Schema::create('trusted_source_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('host', 255);
            $table->string('source_type', 40)->default('cloud_drive');
            $table->string('example_url', 2048)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 40)->default('pending');
            $table->unsignedInteger('report_count')->default(1);
            $table->float('weighted_score')->default(0);
            $table->timestamp('last_reported_at')->nullable();
            $table->timestamps();

            $table->unique(['host', 'source_type', 'status'], 'trusted_source_suggestion_rollup_unique');
            $table->index(['status', 'weighted_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_source_suggestions');
        Schema::dropIfExists('url_classification_reports');

        Schema::table('news_domains', function (Blueprint $table) {
            $table->dropColumn(['article_url_pattern', 'list_url_pattern']);
        });
    }
};
