<?php

namespace Modules\Referral\Repositories;

use Modules\Referral\Models\FinopalTransaction;

interface TransactionRepositoryInterface
{
    public function create(array $data): FinopalTransaction;

    public function findByExternalTransactionId(string $transactionId): ?FinopalTransaction;

    public function findByIdempotencyKey(string $key): ?FinopalTransaction;
}
