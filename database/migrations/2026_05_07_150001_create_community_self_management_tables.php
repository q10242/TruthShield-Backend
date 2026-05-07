<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('signal_type', 80);
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_key', 500);
            $table->string('value', 120)->nullable();
            $table->float('weight_score')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['signal_type', 'subject_key', 'user_id'], 'community_signal_user_unique');
            $table->index(['signal_type', 'subject_key']);
            $table->index(['signal_type', 'weight_score']);
            $table->index(['user_id', 'signal_type']);
        });

        Schema::create('community_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type', 80);
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_key', 500);
            $table->string('title');
            $table->string('description', 1000)->nullable();
            $table->unsignedSmallInteger('priority')->default(50);
            $table->string('status', 40)->default('open');
            $table->string('action_url', 500)->nullable();
            $table->json('metrics')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'subject_key', 'status'], 'community_task_rollup_unique');
            $table->index(['status', 'priority']);
            $table->index(['type', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_tasks');
        Schema::dropIfExists('community_signals');
    }
};
