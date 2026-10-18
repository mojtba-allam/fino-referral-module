<?php

namespace Modules\Referral\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Referral\Enums\TransactionStatus;
use Modules\Referral\Models\Gateway;
use Modules\Referral\Repositories\GatewayRepositoryInterface;
use Modules\Referral\Repositories\TransactionRepositoryInterface;

/**
 * Handles the "transaction" inbound webhook from finopal. Commission is derived
 * from the transaction's `profit` (never the gateway `amount`) and attributed to
 * the owning representative + upstream manager chain. Idempotent per transaction
 * id / idempotency key.
 */
class RegisterTransactionFromWebhook
{
    public function __construct(
        protected GatewayRepositoryInterface $gateways,
        protected TransactionRepositoryInterface $transactions
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $merchantId = $this->clean($data['merchant_id'] ?? $data['gateway']['merchant_id'] ?? null);
        $profit = (int) ($data['profit'] ?? 0);

        if ($merchantId === null) {
            throw ValidationException::withMessages([
                'merchant_id' => [__('referral::webhook.transaction.missing_merchant')],
            ]);
        }

        $gateway = $this->gateways->findByMerchant($merchantId);

        if ($gateway === null) {
            throw ValidationException::withMessages([
                'merchant_id' => [__('referral::webhook.transaction.unknown_merchant')],
            ]);
        }

        $externalTxnId = $this->clean($data['transaction_id'] ?? $data['external_txn_id'] ?? null);
        $idempotencyKey = $this->clean($data['idempotency_key'] ?? null);

        $existing = $externalTxnId !== null
            ? $this->transactions->findByExternalTransactionId($externalTxnId)
            : null;

        if ($existing === null && $idempotencyKey !== null) {
            $existing = $this->transactions->findByIdempotencyKey($idempotencyKey);
        }

        if ($existing !== null) {
            return [
                'success' => true,
                'message' => __('referral::webhook.transaction.duplicate'),
                'gateway_id' => $gateway->id,
                'transaction_id' => $existing->id,
                'commission' => (int) $existing->commission,
                'transaction_created' => false,
            ];
        }

        $transaction = $this->transactions->create([
            'gateway_id' => $gateway->id,
            'referral_account_id' => $gateway->referral_account_id,
            'merchant_code' => $gateway->merchant_code,
            'external_txn_id' => $externalTxnId,
            'idempotency_key' => $idempotencyKey,
            'amount' => $data['amount'] ?? null,
            'profit' => $profit,
            'commission' => $profit,
            'currency' => $data['currency'] ?? null,
            'status' => TransactionStatus::Recorded,
            'manager_path' => $this->managerChain($gateway),
            'payload' => $data,
        ]);

        return [
            'success' => true,
            'message' => __('referral::webhook.transaction.success'),
            'gateway_id' => $gateway->id,
            'transaction_id' => $transaction->id,
            'commission' => (int) $transaction->commission,
            'transaction_created' => true,
        ];
    }

    /**
     * @return list<string>
     */
    protected function managerChain(Gateway $gateway): array
    {
        $chain = $gateway->manager_path ?? [];
        $chain[] = $gateway->referral_account_id;

        return array_values(array_filter($chain));
    }

    protected function clean(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return ($value === '' || $value === null) ? null : $value;
    }
}
