<?php

namespace Modules\Referral\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Referral\Models\ReferralAccount;

interface ReferralAccountRepositoryInterface
{
    public function create(array $data): ReferralAccount;

    public function update(ReferralAccount $account, array $data): ReferralAccount;

    public function findByNationalId(string $nationalId): ?ReferralAccount;

    public function findByMobile(string $mobile): ?ReferralAccount;

    public function findByFinopalUserId(string $finopalUserId): ?ReferralAccount;

    public function findByReferralCode(string $code): ?ReferralAccount;

    public function findByOwner(string $type, string $id): ?ReferralAccount;

    public function referralCodeExists(string $code): bool;

    public function countReferred(string $referralCode): int;

    /**
     * @return Collection<int, ReferralAccount>
     */
    public function findAncestors(array $ids): Collection;

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;
}
