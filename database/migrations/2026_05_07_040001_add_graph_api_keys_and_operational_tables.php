<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('signal_type', 80);
            $table->string('signal_hash', 128);
            $table->foreignId('news_url_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['signal_type', 'signal_hash']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('account_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('edge_type', 80);
            $table->float('score')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_user_id', 'target_user_id', 'edge_type'], 'account_edge_unique');
            $table->index(['edge_type', 'score']);
        });

        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('key_hash', 128)->unique();
            $table->string('status', 40)->default('active');
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('operational_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 80);
            $table->string('status', 40)->default('ok');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['type', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_events');
        Schema::dropIfExists('api_clients');
        Schema::dropIfExists('account_edges');
        Schema::dropIfExists('account_signals');
    }
};
