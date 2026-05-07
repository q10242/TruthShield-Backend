<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('ecpay');
            $table->string('merchant_trade_no')->unique();
            $table->unsignedInteger('amount');
            $table->string('status')->default('pending')->index();
            $table->string('donor_name')->nullable();
            $table->string('donor_email')->nullable();
            $table->string('message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
