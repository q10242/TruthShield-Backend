<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_events', function (Blueprint $table): void {
            $table->string('primary_category', 60)->nullable()->after('summary')->index();
            $table->json('tags')->nullable()->after('primary_category');
            $table->string('progress_status', 60)->default('collecting')->after('tags')->index();
        });
    }

    public function down(): void
    {
        Schema::table('news_events', function (Blueprint $table): void {
            $table->dropIndex(['primary_category']);
            $table->dropIndex(['progress_status']);
            $table->dropColumn(['primary_category', 'tags', 'progress_status']);
        });
    }
};
