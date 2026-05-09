<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traffic_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 80);
            $table->string('source', 40)->default('api');
            $table->string('feature', 80)->nullable();
            $table->string('path', 255)->nullable();
            $table->string('method', 12)->nullable();
            $table->string('domain', 255)->nullable();
            $table->string('url_hash', 128)->nullable();
            $table->string('session_hash', 128)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('success')->default(true);
            $table->string('cache_status', 24)->nullable();
            $table->string('locale', 16)->nullable();
            $table->decimal('sample_rate', 5, 4)->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index(['feature', 'created_at']);
            $table->index(['domain', 'created_at']);
            $table->index(['url_hash', 'created_at']);
            $table->index(['session_hash', 'created_at']);
        });

        Schema::create('traffic_hourly_summaries', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('bucket_at');
            $table->string('event_type', 80);
            $table->string('source', 40);
            $table->string('feature', 80)->nullable();
            $table->string('domain', 255)->nullable();
            $table->unsignedBigInteger('events_count')->default(0);
            $table->unsignedBigInteger('estimated_count')->default(0);
            $table->unsignedBigInteger('success_count')->default(0);
            $table->unsignedBigInteger('error_count')->default(0);
            $table->unsignedBigInteger('unique_sessions')->default(0);
            $table->unsignedInteger('avg_duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['bucket_at', 'event_type', 'source', 'feature', 'domain'], 'traffic_hour_unique');
            $table->index(['bucket_at', 'source']);
            $table->index(['event_type', 'bucket_at']);
        });

        Schema::create('traffic_daily_summaries', function (Blueprint $table): void {
            $table->id();
            $table->date('bucket_date');
            $table->string('event_type', 80);
            $table->string('source', 40);
            $table->string('feature', 80)->nullable();
            $table->string('domain', 255)->nullable();
            $table->unsignedBigInteger('events_count')->default(0);
            $table->unsignedBigInteger('estimated_count')->default(0);
            $table->unsignedBigInteger('success_count')->default(0);
            $table->unsignedBigInteger('error_count')->default(0);
            $table->unsignedBigInteger('unique_sessions')->default(0);
            $table->unsignedInteger('avg_duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['bucket_date', 'event_type', 'source', 'feature', 'domain'], 'traffic_day_unique');
            $table->index(['bucket_date', 'source']);
            $table->index(['event_type', 'bucket_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_daily_summaries');
        Schema::dropIfExists('traffic_hourly_summaries');
        Schema::dropIfExists('traffic_events');
    }
};
