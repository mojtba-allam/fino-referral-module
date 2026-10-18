<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finopal_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('gateway_id')->nullable();
            $table->uuid('referral_account_id')->nullable();
            $table->string('merchant_code', 64)->nullable();
            $table->string('external_txn_id', 120)->nullable()->unique();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->unsignedBigInteger('amount')->nullable();
            $table->unsignedBigInteger('profit')->default(0);
            $table->unsignedBigInteger('commission')->default(0);
            $table->string('currency', 10)->nullable();
            $table->string('status', 32)->default('recorded');
            $table->text('manager_path')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('gateway_id')->references('id')->on('finopal_gateways')->nullOnDelete();
            $table->foreign('referral_account_id')->references('id')->on('referral_accounts')->nullOnDelete();
            $table->index(['gateway_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finopal_transactions');
    }
};
