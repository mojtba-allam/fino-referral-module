<?php

namespace Modules\Referral\Repositories;

use Modules\Referral\Models\FinopalTransaction;

class EloquentTransactionRepository implements TransactionRepositoryInterface
{
    public function __construct(
        protected FinopalTransaction $model
    ) {}

    public function create(array $data): FinopalTransaction
    {
        return $this->model->create($data);
    }

    public function findByExternalTransactionId(string $transactionId): ?FinopalTransaction
    {
        return $this->model->newQuery()->where('external_txn_id', $transactionId)->first();
    }

    public function findByIdempotencyKey(string $key): ?FinopalTransaction
    {
        return $this->model->newQuery()->where('idempotency_key', $key)->first();
    }
}
