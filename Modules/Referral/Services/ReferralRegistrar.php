<?php

namespace Modules\Referral\Services;

use Illuminate\Support\Facades\DB;
use Modules\Referral\Actions\ProvisionReferralCode;
use Modules\Referral\Contracts\ReferralOwner;
use Modules\Referral\Enums\ReferralAccountStatus;
use Modules\Referral\Models\ReferralAccount;
use Modules\Referral\Repositories\ReferralAccountRepositoryInterface;
use Modules\Referral\Repositories\ReferralRelationRepositoryInterface;

/**
 * The single public entry point a host project calls right after a user is
 * registered. If the signup carried a valid finonet_referral code the user is
 * recorded as a finonet-arrived representative (referral option hidden);
 * otherwise they are provisioned a normal referral code so they can refer others.
 */
class ReferralRegistrar
{
    public function __construct(
        protected ReferralAccountRepositoryInterface $accounts,
        protected ProvisionReferralCode $provision,
        protected ReferralRelationRepositoryInterface $relations
    ) {}

    public function onRegistered(ReferralOwner $owner, ?string $referralRef): void
    {
        if ($this->accounts->findByOwner($owner->type(), $owner->key()) !== null) {
            return;
        }

        $referralRef = is_string($referralRef) ? trim($referralRef) : '';

        if ($referralRef === '') {
            $this->provision->execute($owner);

            return;
        }

        $parent = $this->accounts->findByReferralCode($referralRef);

        if ($parent === null) {
            // Unknown code: fall back to a normal referral account so the user
            // is never blocked from signing up.
            $this->provision->execute($owner);

            return;
        }

        DB::transaction(function () use ($owner, $parent): void {
            $account = $this->accounts->create([
                'owner_type' => $owner->type(),
                'owner_id' => $owner->key(),
                'referral_code' => null,
                'referred_by_code' => $parent->referral_code,
                'arrived_via_finonet' => true,
                'name' => $owner->name(),
                'email' => $owner->email(),
                'mobile' => $owner->mobile(),
                'referral_path' => $this->buildPath($parent),
                'status' => ReferralAccountStatus::Active,
            ]);

            $this->relations->create([
                'referrer_referral_id' => $parent->id,
                'referred_referral_id' => $account->id,
                'depth' => count($parent->ancestorIds()) + 1,
            ]);
        });
    }

    protected function buildPath(ReferralAccount $parent): string
    {
        $ancestors = $parent->ancestorIds();
        $ancestors[] = $parent->id;

        return implode(',', $ancestors);
    }
}
