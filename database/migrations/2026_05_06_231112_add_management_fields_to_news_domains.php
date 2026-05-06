<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_domains', function (Blueprint $table) {
            $table->string('article_selector', 500)->nullable()->after('notes');
            $table->integer('priority')->default(100)->after('article_selector');
        });
    }

    public function down(): void
    {
        Schema::table('news_domains', function (Blueprint $table) {
            $table->dropColumn(['article_selector', 'priority']);
        });
    }
};
