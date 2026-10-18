<?php

namespace Modules\Referral\Repositories;

use Modules\Referral\Models\Gateway;

class EloquentGatewayRepository implements GatewayRepositoryInterface
{
    public function __construct(
        protected Gateway $model
    ) {}

    public function create(array $data): Gateway
    {
        return $this->model->create($data);
    }

    public function findByExternalId(string $externalId): ?Gateway
    {
        return $this->model->newQuery()->where('external_id', $externalId)->first();
    }

    public function findByMerchantCode(string $merchantCode): ?Gateway
    {
        return $this->model->newQuery()->where('merchant_code', $merchantCode)->first();
    }

    public function findByIdempotencyKey(string $key): ?Gateway
    {
        return $this->model->newQuery()->where('idempotency_key', $key)->first();
    }

    public function findByMerchant(string $merchantId): ?Gateway
    {
        return $this->model->newQuery()
            ->where(function ($query) use ($merchantId) {
                $query->where('external_id', $merchantId)
                    ->orWhere('merchant_code', $merchantId);
            })
            ->first();
    }
}
