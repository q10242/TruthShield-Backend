<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS votes_evidence_note_trgm_idx ON votes USING gin (evidence_note gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS votes_evidence_url_trgm_idx ON votes USING gin (evidence_url gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS news_urls_title_snapshot_trgm_idx ON news_urls USING gin (title_snapshot gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS news_urls_normalized_url_trgm_idx ON news_urls USING gin (normalized_url gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS news_urls_normalized_url_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS news_urls_title_snapshot_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS votes_evidence_url_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS votes_evidence_note_trgm_idx');
    }
};
