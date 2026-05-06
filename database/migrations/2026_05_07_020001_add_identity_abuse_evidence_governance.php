<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('identity_level', 40)->default('dev')->after('auth_provider');
            $table->string('risk_status', 40)->default('normal')->after('identity_level');
            $table->float('identity_multiplier')->default(0.8)->after('risk_status');
            $table->float('abuse_multiplier')->default(1.0)->after('identity_multiplier');
        });

        Schema::create('user_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('provider_user_id');
            $table->string('email')->nullable();
            $table->string('display_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_user_id']);
            $table->index(['user_id', 'provider']);
        });

        Schema::table('news_urls', function (Blueprint $table) {
            $table->string('algorithm_version', 40)->default('truthshield-v1')->after('finalized_at');
        });

        Schema::create('evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('news_url_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('host', 255)->nullable();
            $table->string('type', 40)->nullable();
            $table->string('safety', 40)->default('unknown');
            $table->string('snapshot_status', 40)->default('pending');
            $table->string('archive_url', 2048)->nullable();
            $table->string('preview_url', 2048)->nullable();
            $table->float('quality_score')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('vote_id');
            $table->index(['news_url_id', 'quality_score']);
            $table->index(['host', 'safety']);
        });

        Schema::create('appeals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->string('reason', 120);
            $table->text('statement');
            $table->string('status', 40)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('subject_type', 120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('public_reason', 255);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('extension_events', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255);
            $table->string('event_type', 80);
            $table->string('extension_version', 40)->nullable();
            $table->boolean('success')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['domain', 'event_type', 'created_at']);
            $table->index(['success', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_events');
        Schema::dropIfExists('moderation_events');
        Schema::dropIfExists('appeals');
        Schema::dropIfExists('evidences');

        Schema::table('news_urls', function (Blueprint $table) {
            $table->dropColumn('algorithm_version');
        });

        Schema::dropIfExists('user_identities');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['identity_level', 'risk_status', 'identity_multiplier', 'abuse_multiplier']);
        });
    }
};
