<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('email_preferences')->nullable()->after('profile_bio');
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('email_category', 80)->nullable()->after('metadata');
            $table->string('email_status', 40)->default('not_requested')->after('email_category');
            $table->timestamp('email_sent_at')->nullable()->after('email_status');
            $table->string('email_error', 500)->nullable()->after('email_sent_at');
        });

        Schema::table('bug_reports', function (Blueprint $table) {
            $table->text('admin_response')->nullable()->after('triage_note');
            $table->timestamp('reporter_notified_at')->nullable()->after('admin_response');
            $table->string('reporter_email_status', 40)->default('not_requested')->after('reporter_notified_at');
            $table->string('reporter_email_error', 500)->nullable()->after('reporter_email_status');
        });

        Schema::table('donations', function (Blueprint $table) {
            $table->string('receipt_email_status', 40)->default('not_requested')->after('paid_at');
            $table->timestamp('receipt_email_sent_at')->nullable()->after('receipt_email_status');
            $table->string('receipt_email_error', 500)->nullable()->after('receipt_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn(['receipt_email_status', 'receipt_email_sent_at', 'receipt_email_error']);
        });

        Schema::table('bug_reports', function (Blueprint $table) {
            $table->dropColumn(['admin_response', 'reporter_notified_at', 'reporter_email_status', 'reporter_email_error']);
        });

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropColumn(['email_category', 'email_status', 'email_sent_at', 'email_error']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_preferences');
        });
    }
};
