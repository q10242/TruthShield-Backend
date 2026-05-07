<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_tasks', function (Blueprint $table) {
            $table->json('generation_snapshot')->nullable()->after('metrics');
            $table->timestamp('expires_at')->nullable()->after('generation_snapshot');
            $table->string('resolved_reason', 500)->nullable()->after('resolved_at');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('community_tasks', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at']);
            $table->dropColumn(['generation_snapshot', 'expires_at', 'resolved_reason']);
        });
    }
};
