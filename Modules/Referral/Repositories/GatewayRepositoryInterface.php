<?php

namespace Modules\Referral\Repositories;

use Modules\Referral\Models\Gateway;

interface GatewayRepositoryInterface
{
    public function create(array $data): Gateway;

    public function findByExternalId(string $externalId): ?Gateway;

    public function findByMerchantCode(string $merchantCode): ?Gateway;

    public function findByIdempotencyKey(string $key): ?Gateway;

    public function findByMerchant(string $merchantId): ?Gateway;
}
