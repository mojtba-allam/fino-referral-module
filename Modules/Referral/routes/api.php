<?php

use Illuminate\Support\Facades\Route;
use Modules\Referral\Http\Controllers\Api\V1\GatewayWebhookController;
use Modules\Referral\Http\Controllers\Api\V1\ReferralController;
use Modules\Referral\Http\Controllers\Api\V1\SignupWebhookController;
use Modules\Referral\Http\Controllers\Api\V1\TransactionWebhookController;
use Modules\Referral\Http\Middleware\VerifyFinopalWebhook;

// Inbound finopal/finonet webhooks (public, secret-guarded).
// The provider wraps these with the "api" middleware group and the "api" prefix,
// so the final URLs are /api/webhooks/finopal/{signup,gateway,transaction}.
// Each endpoint is opt-in per project via config('referral.webhooks.*').
Route::prefix('webhooks/finopal')->middleware(VerifyFinopalWebhook::class)->group(function () {
    if (config('referral.webhooks.signup')) {
        Route::post('/signup', SignupWebhookController::class);
    }

    if (config('referral.webhooks.gateway')) {
        Route::post('/gateway', GatewayWebhookController::class);
    }

    if (config('referral.webhooks.transaction')) {
        Route::post('/transaction', TransactionWebhookController::class);
    }
});

// Host-facing referral API for the authenticated user.
Route::prefix('v1/referral')->middleware('auth:sanctum')->group(function () {
    Route::get('/me', [ReferralController::class, 'me']);
    Route::post('/activate', [ReferralController::class, 'activate']);
});
