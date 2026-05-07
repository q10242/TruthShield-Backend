<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->string('public_identity_label', 120)->nullable()->after('display_name');
            $table->boolean('is_real_name_public')->default(false)->after('public_identity_label');
            $table->string('profile_bio', 500)->nullable()->after('is_real_name_public');
        });

        Schema::create('verified_claimants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('claim_type', 40);
            $table->string('domain', 255)->nullable();
            $table->foreignId('news_url_id')->nullable()->constrained()->nullOnDelete();
            $table->string('organization_name', 255)->nullable();
            $table->string('proof_url', 2048)->nullable();
            $table->text('statement')->nullable();
            $table->string('status', 40)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['claim_type', 'domain', 'status']);
            $table->index(['news_url_id', 'status']);
        });

        Schema::create('official_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_url_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verified_claimant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('response_type', 40)->default('subject_clarification');
            $table->text('response_text');
            $table->string('evidence_url', 2048)->nullable();
            $table->string('status', 40)->default('pending');
            $table->float('helpful_weight')->default(0);
            $table->float('unhelpful_weight')->default(0);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['news_url_id', 'status', 'published_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('official_response_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_response_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('helpful');
            $table->float('weight_score')->default(1);
            $table->timestamps();

            $table->unique(['official_response_id', 'user_id']);
            $table->index(['official_response_id', 'helpful']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('official_response_reactions');
        Schema::dropIfExists('official_responses');
        Schema::dropIfExists('verified_claimants');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'public_identity_label', 'is_real_name_public', 'profile_bio']);
        });
    }
};
