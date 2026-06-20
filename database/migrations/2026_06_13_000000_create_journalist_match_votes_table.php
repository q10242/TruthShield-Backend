<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journalist_match_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journalist_news_url_id')->constrained('journalist_news_url')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 10);
            $table->timestamps();

            $table->unique(['journalist_news_url_id', 'user_id'], 'journalist_match_votes_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journalist_match_votes');
    }
};
