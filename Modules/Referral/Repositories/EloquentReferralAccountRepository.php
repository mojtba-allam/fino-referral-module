<?php

namespace Modules\Referral\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Referral\Models\ReferralAccount;

class EloquentReferralAccountRepository implements ReferralAccountRepositoryInterface
{
    public function __construct(
        protected ReferralAccount $model
    ) {}

    public function create(array $data): ReferralAccount
    {
        return $this->model->create($data);
    }

    public function update(ReferralAccount $account, array $data): ReferralAccount
    {
        $account->fill($data)->save();

        return $account->refresh();
    }

    public function findByNationalId(string $nationalId): ?ReferralAccount
    {
        return $this->model->newQuery()->where('national_id', $nationalId)->first();
    }

    public function findByMobile(string $mobile): ?ReferralAccount
    {
        return $this->model->newQuery()->where('mobile', $mobile)->first();
    }

    public function findByFinopalUserId(string $finopalUserId): ?ReferralAccount
    {
        return $this->model->newQuery()->where('finopal_user_id', $finopalUserId)->first();
    }

    public function findByReferralCode(string $code): ?ReferralAccount
    {
        return $this->model->newQuery()->where('referral_code', $code)->first();
    }

    public function findByOwner(string $type, string $id): ?ReferralAccount
    {
        return $this->model->newQuery()
            ->where('owner_type', $type)
            ->where('owner_id', $id)
            ->first();
    }

    public function referralCodeExists(string $code): bool
    {
        return $this->model->newQuery()->where('referral_code', $code)->exists();
    }

    public function countReferred(string $referralCode): int
    {
        return $this->model->newQuery()->where('referred_by_code', $referralCode)->count();
    }

    public function findAncestors(array $ids): Collection
    {
        if ($ids === []) {
            return $this->model->newQuery()->whereRaw('1 = 0')->get();
        }

        return $this->model->newQuery()->whereIn('id', $ids)->get();
    }

    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        if (! empty($filters['referred_by_code'])) {
            $query->where('referred_by_code', $filters['referred_by_code']);
        }

        return $query->latest()->paginate($perPage);
    }
}
