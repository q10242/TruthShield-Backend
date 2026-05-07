<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bug_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type')->default('bug')->index();
            $table->string('severity')->default('medium')->index();
            $table->string('status')->default('new')->index();
            $table->string('title');
            $table->text('description');
            $table->text('steps_to_reproduce')->nullable();
            $table->text('page_url')->nullable();
            $table->text('attachment_url')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('browser')->nullable();
            $table->string('extension_version')->nullable();
            $table->string('source')->default('website')->index();
            $table->json('diagnostics')->nullable();
            $table->text('triage_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_reports');
    }
};
