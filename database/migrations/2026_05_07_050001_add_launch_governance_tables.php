<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oauth_login_states', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40);
            $table->string('state_hash', 128)->unique();
            $table->string('redirect_url', 2048)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['provider', 'expires_at']);
        });

        Schema::create('trusted_evidence_sources', function (Blueprint $table) {
            $table->id();
            $table->string('host', 255)->unique();
            $table->string('source_type', 40)->default('archive');
            $table->float('trust_bonus')->default(10);
            $table->boolean('is_active')->default(true);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['is_active', 'source_type']);
        });

        Schema::create('extension_selector_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('domain', 255);
            $table->string('check_type', 80);
            $table->boolean('success')->default(false);
            $table->string('selector', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['domain', 'check_type', 'checked_at']);
            $table->index(['success', 'checked_at']);
        });

        Schema::create('rate_limit_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('scope', 80);
            $table->unsignedInteger('max_attempts');
            $table->unsignedInteger('decay_seconds');
            $table->float('low_trust_multiplier')->default(0.5);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('abuse_events', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('reviewed')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_note', 500)->nullable()->after('reviewed_at');
            $table->string('action_taken', 80)->nullable()->after('review_note');
        });

        Schema::table('evidence_reports', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->string('review_note', 500)->nullable()->after('reviewed_at');
        });

        Schema::table('evidences', function (Blueprint $table) {
            $table->boolean('hidden')->default(false)->after('quality_score');
            $table->string('moderation_status', 40)->default('visible')->after('hidden');
            $table->timestamp('reviewed_at')->nullable()->after('moderation_status');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['hidden', 'moderation_status', 'reviewed_at']);
        });

        Schema::table('evidence_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_note']);
        });

        Schema::table('abuse_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_note', 'action_taken']);
        });

        Schema::dropIfExists('rate_limit_policies');
        Schema::dropIfExists('extension_selector_checks');
        Schema::dropIfExists('trusted_evidence_sources');
        Schema::dropIfExists('oauth_login_states');
    }
};
