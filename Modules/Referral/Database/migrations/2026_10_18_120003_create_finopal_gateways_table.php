<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finopal_gateways', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referral_account_id')->nullable();
            $table->string('owner_type')->nullable();
            $table->string('owner_id')->nullable();
            $table->string('external_id', 64)->nullable()->unique();
            $table->string('merchant_code', 64)->nullable()->unique();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('website')->nullable();
            $table->string('callback_url')->nullable();
            $table->string('status', 32)->default('successful');
            $table->unsignedInteger('share_percent')->default(100);
            $table->text('manager_path')->nullable();
            $table->string('idempotency_key', 120)->nullable()->unique();
            $table->timestamps();

            $table->foreign('referral_account_id')->references('id')->on('referral_accounts')->nullOnDelete();
            $table->index('referral_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finopal_gateways');
    }
};
