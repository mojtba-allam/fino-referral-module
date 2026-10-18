<?php

namespace Modules\Referral\Contracts;

/**
 * Resolves the currently authenticated host user into a ReferralOwner so the
 * module's own referral API can act on behalf of that user without knowing the
 * host's auth guard or model.
 */
interface ReferralOwnerResolver
{
    /**
     * The authenticated host user as a ReferralOwner, or null when unauthenticated.
     */
    public function current(): ?ReferralOwner;
}
