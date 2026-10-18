<?php

namespace Modules\Referral;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Referral\Adapters\FinoloOwnerResolver;
use Modules\Referral\Adapters\SmsReferralNotifier;
use Modules\Referral\Contracts\ReferralNotifier;
use Modules\Referral\Contracts\ReferralOwnerResolver;
use Modules\Referral\Repositories\EloquentGatewayRepository;
use Modules\Referral\Repositories\EloquentReferralAccountRepository;
use Modules\Referral\Repositories\EloquentReferralRelationRepository;
use Modules\Referral\Repositories\EloquentTransactionRepository;
use Modules\Referral\Repositories\EloquentWebhookEventRepository;
use Modules\Referral\Repositories\GatewayRepositoryInterface;
use Modules\Referral\Repositories\ReferralAccountRepositoryInterface;
use Modules\Referral\Repositories\ReferralRelationRepositoryInterface;
use Modules\Referral\Repositories\TransactionRepositoryInterface;
use Modules\Referral\Repositories\WebhookEventRepositoryInterface;
use Modules\Referral\Support\ReferralCodeGenerator;

class ReferralServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/referral.php', 'referral');

        // Data-access repositories.
        $this->app->bind(ReferralAccountRepositoryInterface::class, EloquentReferralAccountRepository::class);
        $this->app->bind(ReferralRelationRepositoryInterface::class, EloquentReferralRelationRepository::class);
        $this->app->bind(GatewayRepositoryInterface::class, EloquentGatewayRepository::class);
        $this->app->bind(TransactionRepositoryInterface::class, EloquentTransactionRepository::class);
        $this->app->bind(WebhookEventRepositoryInterface::class, EloquentWebhookEventRepository::class);

        // Host-agnostic seams, bound to the Finolo adapters.
        $this->app->bind(ReferralOwnerResolver::class, FinoloOwnerResolver::class);
        $this->app->bind(ReferralNotifier::class, SmsReferralNotifier::class);

        $this->app->singleton(ReferralCodeGenerator::class);
    }

    public function boot(): void
    {
        $this->loadRoutes();
        $this->loadMigrationsFrom(__DIR__.'/Database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/lang', 'referral');
    }

    protected function loadRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/routes/api.php');
    }
}
