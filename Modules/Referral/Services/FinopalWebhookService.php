<?php

namespace Modules\Referral\Services;

use Illuminate\Support\Facades\Log;
use Modules\Referral\Actions\RegisterGatewayFromWebhook;
use Modules\Referral\Actions\RegisterSignupFromWebhook;
use Modules\Referral\Actions\RegisterTransactionFromWebhook;
use Modules\Referral\Repositories\WebhookEventRepositoryInterface;

/**
 * Thin orchestrator over the three inbound webhook actions, keeping the
 * controllers thin per the module architecture guide. Every received webhook is
 * recorded in the finopal_webhook_events audit log.
 */
class FinopalWebhookService
{
    public function __construct(
        protected RegisterSignupFromWebhook $signup,
        protected RegisterGatewayFromWebhook $gateway,
        protected RegisterTransactionFromWebhook $transaction,
        protected WebhookEventRepositoryInterface $events
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handleSignup(array $data): array
    {
        $result = $this->signup->execute($data);
        $this->log('user.registered', $data['referral_code'] ?? null, $data, 'processed');

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handleGateway(array $data): array
    {
        $result = $this->gateway->execute($data);
        $this->log('gateway.registered', $data['idempotency_key'] ?? null, $data, 'processed');

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function handleTransaction(array $data): array
    {
        $result = $this->transaction->execute($data);
        $this->log('transaction.settled', $data['transaction_id'] ?? $data['idempotency_key'] ?? null, $data, 'processed');

        return $result;
    }

    /**
     * Persist an audit row without letting logging failures break the webhook.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function log(string $event, ?string $idempotencyKey, array $payload, string $status): void
    {
        try {
            $this->events->record([
                'event' => $event,
                'idempotency_key' => $idempotencyKey,
                'payload' => $payload,
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            report($e);
            Log::warning('Failed to record finopal webhook event', ['event' => $event]);
        }
    }
}
