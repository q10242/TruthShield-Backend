<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('primary_news_url_id')->nullable()->constrained('news_urls')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->string('status', 40)->default('active')->index();
            $table->boolean('is_disputed')->default(false)->index();
            $table->unsignedInteger('controversy_score')->default(0)->index();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_at', 'status']);
        });

        Schema::create('news_event_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_event_id')->constrained('news_events')->cascadeOnDelete();
            $table->foreignId('news_url_id')->nullable()->constrained('news_urls')->nullOnDelete();
            $table->foreignId('evidence_id')->nullable()->constrained('evidences')->nullOnDelete();
            $table->foreignId('official_response_id')->nullable()->constrained('official_responses')->nullOnDelete();
            $table->foreignId('news_url_snapshot_id')->nullable()->constrained('news_url_snapshots')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('item_type', 40)->index();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->text('source_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['news_event_id', 'news_url_id'], 'event_items_unique_news');
            $table->index(['news_event_id', 'item_type']);
        });

        Schema::create('news_event_timeline_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_event_id')->constrained('news_events')->cascadeOnDelete();
            $table->foreignId('news_url_id')->nullable()->constrained('news_urls')->nullOnDelete();
            $table->foreignId('evidence_id')->nullable()->constrained('evidences')->nullOnDelete();
            $table->foreignId('official_response_id')->nullable()->constrained('official_responses')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entry_type', 60)->default('manual')->index();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->text('source_url')->nullable();
            $table->string('source_type', 40)->default('news')->index();
            $table->boolean('is_disputed')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['news_event_id', 'occurred_at']);
        });

        Schema::create('event_entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_event_id')->constrained('news_events')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity_type', 40)->index();
            $table->string('name');
            $table->json('aliases')->nullable();
            $table->text('description')->nullable();
            $table->text('source_url')->nullable();
            $table->boolean('is_disputed')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['news_event_id', 'entity_type', 'name'], 'event_entities_unique_name');
        });

        Schema::create('event_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_event_id')->constrained('news_events')->cascadeOnDelete();
            $table->foreignId('from_entity_id')->constrained('event_entities')->cascadeOnDelete();
            $table->foreignId('to_entity_id')->constrained('event_entities')->cascadeOnDelete();
            $table->foreignId('news_url_id')->nullable()->constrained('news_urls')->nullOnDelete();
            $table->foreignId('evidence_id')->nullable()->constrained('evidences')->nullOnDelete();
            $table->foreignId('official_response_id')->nullable()->constrained('official_responses')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('relationship_type', 80)->index();
            $table->text('description')->nullable();
            $table->text('source_url')->nullable();
            $table->string('source_type', 40)->default('news')->index();
            $table->boolean('is_high_risk')->default(false)->index();
            $table->boolean('is_disputed')->default(false)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['news_event_id', 'relationship_type']);
        });

        Schema::create('event_edit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_event_id')->nullable()->constrained('news_events')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('subject_type', 80)->index();
            $table->unsignedBigInteger('subject_id')->nullable()->index();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->boolean('is_public')->default(true)->index();
            $table->timestamps();

            $table->index(['news_event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_edit_logs');
        Schema::dropIfExists('event_relationships');
        Schema::dropIfExists('event_entities');
        Schema::dropIfExists('news_event_timeline_entries');
        Schema::dropIfExists('news_event_items');
        Schema::dropIfExists('news_events');
    }
};
