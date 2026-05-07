<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            if (! Schema::hasColumn('votes', 'secondary_tag_ids')) {
                $table->json('secondary_tag_ids')->nullable()->after('tag_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            if (Schema::hasColumn('votes', 'secondary_tag_ids')) {
                $table->dropColumn('secondary_tag_ids');
            }
        });
    }
};
