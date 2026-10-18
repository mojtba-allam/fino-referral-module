<?php

namespace Modules\Referral\Actions;

use Modules\Referral\Models\ReferralAccount;
use Modules\Referral\Repositories\ReferralAccountRepositoryInterface;

/**
 * Locates an existing referral account using the documented match order:
 * national_id -> mobile -> finopal_user_id.
 */
class ResolveWebhookOwner
{
    public function __construct(
        protected ReferralAccountRepositoryInterface $accounts
    ) {}

    public function execute(?string $nationalId, ?string $mobile, ?string $finopalUserId): ?ReferralAccount
    {
        $nationalId = $this->clean($nationalId);
        $mobile = $this->clean($mobile);
        $finopalUserId = $this->clean($finopalUserId);

        if ($nationalId !== null) {
            $account = $this->accounts->findByNationalId($nationalId);

            if ($account !== null) {
                return $account;
            }
        }

        if ($mobile !== null) {
            $account = $this->accounts->findByMobile($mobile);

            if ($account !== null) {
                return $account;
            }
        }

        if ($finopalUserId !== null) {
            return $this->accounts->findByFinopalUserId($finopalUserId);
        }

        return null;
    }

    protected function clean(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return ($value === '' || $value === null) ? null : $value;
    }
}
