<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 80);
            $table->string('title', 160);
            $table->string('body', 500)->nullable();
            $table->string('action_url', 2048)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at', 'created_at']);
            $table->index('type');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->string('evidence_host', 255)->nullable()->after('evidence_type');
            $table->string('evidence_safety', 40)->default('unknown')->after('evidence_host');
        });
    }

    public function down(): void
    {
        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn(['evidence_host', 'evidence_safety']);
        });

        Schema::dropIfExists('user_notifications');
    }
};
