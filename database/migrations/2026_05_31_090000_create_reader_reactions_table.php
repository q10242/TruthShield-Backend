<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reader_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('source_news_url_id')->nullable()->constrained('news_urls')->nullOnDelete();
            $table->json('feelings');
            $table->json('needs');
            $table->float('weight_score')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'subject_type', 'subject_id'], 'reader_reaction_user_subject_unique');
            $table->index(['subject_type', 'subject_id', 'updated_at']);
            $table->index(['source_news_url_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reader_reactions');
    }
};
