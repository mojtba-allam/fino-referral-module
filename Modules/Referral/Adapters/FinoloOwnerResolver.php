<?php

namespace Modules\Referral\Adapters;

use Modules\Referral\Contracts\ReferralOwner;
use Modules\Referral\Contracts\ReferralOwnerResolver;
use Modules\User\Models\User;

class FinoloOwnerResolver implements ReferralOwnerResolver
{
    /**
     * Resolve the currently authenticated Finolo user into a ReferralOwner.
     */
    public function current(): ?ReferralOwner
    {
        $user = request()?->user() ?? auth()->user();

        return $user instanceof User ? new UserReferralOwner($user) : null;
    }
}
