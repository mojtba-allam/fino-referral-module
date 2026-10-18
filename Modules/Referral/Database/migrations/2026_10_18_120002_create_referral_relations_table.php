<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_relations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referrer_referral_id');
            $table->uuid('referred_referral_id');
            $table->unsignedInteger('depth')->default(1);
            $table->timestamps();

            $table->foreign('referrer_referral_id')->references('id')->on('referral_accounts')->cascadeOnDelete();
            $table->foreign('referred_referral_id')->references('id')->on('referral_accounts')->cascadeOnDelete();
            $table->index(['referrer_referral_id', 'referred_referral_id'], 'referral_relations_pair_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_relations');
    }
};
