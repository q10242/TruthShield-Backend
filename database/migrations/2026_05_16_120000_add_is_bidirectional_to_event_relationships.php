<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_relationships', function (Blueprint $table): void {
            $table->boolean('is_bidirectional')->default(false)->after('is_high_risk');
        });
    }

    public function down(): void
    {
        Schema::table('event_relationships', function (Blueprint $table): void {
            $table->dropColumn('is_bidirectional');
        });
    }
};
