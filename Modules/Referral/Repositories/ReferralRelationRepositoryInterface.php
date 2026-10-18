<?php

namespace Modules\Referral\Repositories;

use Illuminate\Support\Collection;
use Modules\Referral\Models\ReferralRelation;

interface ReferralRelationRepositoryInterface
{
    public function create(array $data): ReferralRelation;

    /**
     * @return Collection<int, ReferralRelation>
     */
    public function childrenOf(string $referrerReferralId): Collection;
}
