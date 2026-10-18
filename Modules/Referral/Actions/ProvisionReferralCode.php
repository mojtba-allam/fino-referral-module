<?php

namespace Modules\Referral\Actions;

use Modules\Referral\Contracts\ReferralOwner;
use Modules\Referral\Enums\ReferralAccountStatus;
use Modules\Referral\Models\ReferralAccount;
use Modules\Referral\Repositories\ReferralAccountRepositoryInterface;
use Modules\Referral\Support\ReferralCodeGenerator;

/**
 * Creates (or returns) the referral account for an eligible host user who is
 * not arriving via a finonet link, so they can start referring others.
 */
class ProvisionReferralCode
{
    public function __construct(
        protected ReferralAccountRepositoryInterface $accounts,
        protected ReferralCodeGenerator $codes
    ) {}

    public function execute(ReferralOwner $owner): ReferralAccount
    {
        $existing = $this->accounts->findByOwner($owner->type(), $owner->key());

        if ($existing !== null) {
            return $existing;
        }

        return $this->accounts->create([
            'owner_type' => $owner->type(),
            'owner_id' => $owner->key(),
            'referral_code' => $this->codes->generate(),
            'arrived_via_finonet' => false,
            'name' => $owner->name(),
            'email' => $owner->email(),
            'mobile' => $owner->mobile(),
            'status' => ReferralAccountStatus::Active,
        ]);
    }
}
