<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_urls', function (Blueprint $table) {
            $table->index(['voting_closes_at', 'finalized_at'], 'news_urls_voting_finalized_idx');
            $table->index(['media_outlet_id', 'created_at'], 'news_urls_media_created_idx');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'votes_user_created_idx');
            $table->index(['news_url_id', 'hidden', 'updated_at'], 'votes_news_hidden_updated_idx');
            $table->index('evidence_url', 'votes_evidence_url_idx');
            $table->index(['evidence_host', 'evidence_safety'], 'votes_evidence_host_safety_idx');
        });

        Schema::table('evidence_reactions', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'evidence_reactions_user_created_idx');
        });

        Schema::table('extension_events', function (Blueprint $table) {
            $table->index('created_at', 'extension_events_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('extension_events', function (Blueprint $table) {
            $table->dropIndex('extension_events_created_idx');
        });

        Schema::table('evidence_reactions', function (Blueprint $table) {
            $table->dropIndex('evidence_reactions_user_created_idx');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropIndex('votes_user_created_idx');
            $table->dropIndex('votes_news_hidden_updated_idx');
            $table->dropIndex('votes_evidence_url_idx');
            $table->dropIndex('votes_evidence_host_safety_idx');
        });

        Schema::table('news_urls', function (Blueprint $table) {
            $table->dropIndex('news_urls_voting_finalized_idx');
            $table->dropIndex('news_urls_media_created_idx');
        });
    }
};
