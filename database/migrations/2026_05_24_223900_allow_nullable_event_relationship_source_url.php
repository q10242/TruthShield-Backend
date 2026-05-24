<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE event_relationships ALTER COLUMN source_url DROP NOT NULL');
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE event_relationships MODIFY source_url TEXT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("UPDATE event_relationships SET source_url = '' WHERE source_url IS NULL");
            DB::statement('ALTER TABLE event_relationships ALTER COLUMN source_url SET NOT NULL');
        }

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("UPDATE event_relationships SET source_url = '' WHERE source_url IS NULL");
            DB::statement('ALTER TABLE event_relationships MODIFY source_url TEXT NOT NULL');
        }
    }
};
