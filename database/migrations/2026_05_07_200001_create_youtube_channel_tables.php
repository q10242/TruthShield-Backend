<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('youtube_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel_id')->nullable()->unique();
            $table->string('handle')->nullable()->unique();
            $table->string('title')->nullable();
            $table->string('channel_url', 2048)->nullable();
            $table->string('channel_type', 40)->default('news')->index();
            $table->string('status', 40)->default('pending')->index();
            $table->boolean('is_active')->default(false)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('youtube_channel_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('youtube_channel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel_id')->nullable()->index();
            $table->string('handle')->nullable()->index();
            $table->string('channel_url', 2048);
            $table->string('channel_title')->nullable();
            $table->string('channel_type', 40)->default('news')->index();
            $table->string('status', 40)->default('pending')->index();
            $table->unsignedInteger('report_count')->default(1);
            $table->decimal('weighted_score', 8, 4)->default(0);
            $table->text('note')->nullable();
            $table->timestamp('last_reported_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'weighted_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('youtube_channel_reports');
        Schema::dropIfExists('youtube_channels');
    }
};
