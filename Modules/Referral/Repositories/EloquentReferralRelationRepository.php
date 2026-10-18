<?php

namespace Modules\Referral\Repositories;

use Illuminate\Support\Collection;
use Modules\Referral\Models\ReferralRelation;

class EloquentReferralRelationRepository implements ReferralRelationRepositoryInterface
{
    public function __construct(
        protected ReferralRelation $model
    ) {}

    public function create(array $data): ReferralRelation
    {
        return $this->model->create($data);
    }

    public function childrenOf(string $referrerReferralId): Collection
    {
        return $this->model->newQuery()
            ->where('referrer_referral_id', $referrerReferralId)
            ->orderBy('depth')
            ->get();
    }
}
