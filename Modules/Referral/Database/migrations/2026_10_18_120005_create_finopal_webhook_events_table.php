<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finopal_webhook_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event', 64);
            $table->string('idempotency_key', 120)->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('received');
            $table->timestamps();

            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finopal_webhook_events');
    }
};
