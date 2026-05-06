<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_domain_reports', function (Blueprint $table) {
            $table->id();
            $table->string('domain');
            $table->text('url');
            $table->string('page_title')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 24)->default('pending');
            $table->timestamps();

            $table->index(['domain', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_domain_reports');
    }
};
