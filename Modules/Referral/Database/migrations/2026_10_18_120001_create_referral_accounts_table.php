<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type')->nullable();
            $table->string('owner_id')->nullable();
            $table->string('referral_code', 32)->nullable()->unique();
            $table->string('referred_by_code', 32)->nullable();
            $table->boolean('arrived_via_finonet')->default(false);
            $table->string('national_id', 32)->nullable()->unique();
            $table->string('mobile', 20)->nullable();
            $table->string('finopal_user_id', 64)->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('sheba', 40)->nullable();
            $table->text('referral_path')->nullable();
            $table->string('status', 32)->default('active');
            $table->timestamps();

            $table->index(['owner_type', 'owner_id']);
            $table->index('referred_by_code');
            $table->index('mobile');
            $table->index('finopal_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_accounts');
    }
};
