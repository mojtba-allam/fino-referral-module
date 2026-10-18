<?php

namespace Modules\Referral\Services;

use Modules\Referral\Actions\ProvisionReferralCode;
use Modules\Referral\Contracts\ReferralOwner;
use Modules\Referral\Models\ReferralAccount;
use Modules\Referral\Repositories\ReferralAccountRepositoryInterface;
use Modules\Referral\Support\ReferralCodeGenerator;

/**
 * Host-facing referral API: shows the current user their referral status and
 * lazily provisions a code/link for eligible (non-finonet-arrived) users.
 */
class ReferralService
{
    public function __construct(
        protected ReferralAccountRepositoryInterface $accounts,
        protected ReferralCodeGenerator $codes,
        protected ProvisionReferralCode $provision
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(ReferralOwner $owner): array
    {
        $account = $this->accounts->findByOwner($owner->type(), $owner->key());

        if ($account === null) {
            return [
                'referral_enabled' => true,
                'show_referral_option' => true,
                'referral_code' => null,
                'referral_link' => null,
                'referred_users_count' => 0,
            ];
        }

        $showOption = ! $account->arrived_via_finonet;

        return [
            'referral_enabled' => true,
            'show_referral_option' => $showOption,
            'referral_code' => $showOption ? $account->referral_code : null,
            'referral_link' => $showOption ? $this->codes->buildLink((string) $account->referral_code) : null,
            'referred_users_count' => $this->accounts->countReferred((string) $account->referral_code),
        ];
    }

    public function activate(ReferralOwner $owner): ReferralAccount
    {
        return $this->provision->execute($owner);
    }
}
