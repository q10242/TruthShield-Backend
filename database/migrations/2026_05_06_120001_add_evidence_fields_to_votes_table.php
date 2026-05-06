<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            if (! Schema::hasColumn('votes', 'evidence_type')) {
                $table->string('evidence_type', 24)->nullable()->after('evidence_url');
            }

            if (! Schema::hasColumn('votes', 'evidence_note')) {
                $table->string('evidence_note', 320)->nullable()->after('evidence_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn(['evidence_type', 'evidence_note']);
        });
    }
};
