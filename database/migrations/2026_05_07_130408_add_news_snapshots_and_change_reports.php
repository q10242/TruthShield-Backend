<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_urls', function (Blueprint $table) {
            $table->text('canonical_url')->nullable()->after('normalized_url');
            $table->string('description_snapshot', 500)->nullable()->after('title_snapshot');
            $table->string('image_snapshot_url', 2048)->nullable()->after('description_snapshot');
            $table->string('content_hash', 64)->nullable()->after('image_snapshot_url');
            $table->string('availability_status', 40)->default('available')->after('content_hash');
            $table->timestamp('last_snapshot_at')->nullable()->after('availability_status');
            $table->string('archive_url', 2048)->nullable()->after('last_snapshot_at');
        });

        Schema::create('news_url_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_url_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('canonical_url')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->string('snapshot_type', 40)->default('observed');
            $table->string('availability_status', 40)->default('available');
            $table->string('archive_url', 2048)->nullable();
            $table->json('change_summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['news_url_id', 'captured_at']);
            $table->index(['availability_status', 'captured_at']);
            $table->index(['snapshot_type', 'captured_at']);
        });

        Schema::create('news_change_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_url_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type', 40);
            $table->text('url');
            $table->string('page_title')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 40)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['report_type', 'created_at']);
            $table->index(['news_url_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_change_reports');
        Schema::dropIfExists('news_url_snapshots');

        Schema::table('news_urls', function (Blueprint $table) {
            $table->dropColumn([
                'canonical_url',
                'description_snapshot',
                'image_snapshot_url',
                'content_hash',
                'availability_status',
                'last_snapshot_at',
                'archive_url',
            ]);
        });
    }
};
