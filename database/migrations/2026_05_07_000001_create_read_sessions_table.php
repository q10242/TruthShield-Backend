<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('read_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('news_url_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('seconds_read')->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'news_url_id']);
            $table->index(['news_url_id', 'seconds_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('read_sessions');
    }
};
