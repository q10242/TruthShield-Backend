<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_events', function (Blueprint $table) {
            $table->unsignedBigInteger('view_count')->default(0)->after('controversy_score');
            $table->timestamp('last_viewed_at')->nullable()->after('last_activity_at');
            $table->index('view_count');
            $table->index('last_viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('news_events', function (Blueprint $table) {
            $table->dropIndex(['view_count']);
            $table->dropIndex(['last_viewed_at']);
            $table->dropColumn(['view_count', 'last_viewed_at']);
        });
    }
};
