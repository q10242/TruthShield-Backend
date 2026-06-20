<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type');           // news_url
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('source_news_url_id')->nullable()->constrained('news_urls')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->nullOnDelete();
            $table->text('body');
            $table->float('weight_score')->default(1.0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('unhelpful_count')->default(0);
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['source_news_url_id', 'created_at']);
            $table->index('parent_id');
        });

        Schema::create('comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('helpful');
            $table->timestamps();

            $table->unique(['comment_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reactions');
        Schema::dropIfExists('comments');
    }
};
