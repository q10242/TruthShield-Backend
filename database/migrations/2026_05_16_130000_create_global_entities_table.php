<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_entities', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 160)->index();
            $table->string('entity_type', 40)->index();
            $table->json('aliases')->nullable();
            $table->text('description')->nullable();
            $table->string('wikipedia_url', 2048)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entity_type', 'name']);
        });

        Schema::table('event_entities', function (Blueprint $table): void {
            $table->foreignId('global_entity_id')
                ->nullable()
                ->after('id')
                ->constrained('global_entities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_entities', function (Blueprint $table): void {
            $table->dropForeign(['global_entity_id']);
            $table->dropColumn('global_entity_id');
        });

        Schema::dropIfExists('global_entities');
    }
};
