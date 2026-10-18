<?php

namespace Modules\Referral\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Referral\Contracts\ReferralNotifier;
use Modules\Referral\Enums\ReferralAccountStatus;
use Modules\Referral\Models\ReferralAccount;
use Modules\Referral\Repositories\ReferralAccountRepositoryInterface;
use Modules\Referral\Repositories\ReferralRelationRepositoryInterface;
use Modules\Referral\Support\ReferralCodeGenerator;

/**
 * Handles the "user registered" inbound webhook from finopal: matches an
 * existing representative, or creates a new one under the referral tree and
 * queues the welcome SMS. Returns the documented response payload.
 */
class RegisterSignupFromWebhook
{
    public function __construct(
        protected ReferralAccountRepositoryInterface $accounts,
        protected ResolveWebhookOwner $resolveOwner,
        protected ReferralCodeGenerator $codes,
        protected ReferralNotifier $notifier,
        protected ReferralRelationRepositoryInterface $relations
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $user = $data['user'] ?? [];
        $nationalId = $user['national_id'] ?? null;
        $mobile = $user['mobile'] ?? null;
        $finopalUserId = $user['finopal_user_id'] ?? null;

        $existing = $this->resolveOwner->execute($nationalId, $mobile, $finopalUserId);

        if ($existing !== null) {
            return [
                'success' => true,
                'message' => __('referral::webhook.signup.duplicate'),
                'user_id' => $existing->id,
                'user_created' => false,
                'user_skipped' => true,
                'welcome_sms_queued' => false,
            ];
        }

        $referralCode = is_string($data['referral_code'] ?? null) ? trim($data['referral_code']) : '';

        if ($referralCode === '') {
            throw ValidationException::withMessages([
                'referral_code' => [__('referral::webhook.signup.missing_referral')],
            ]);
        }

        $parent = $this->accounts->findByReferralCode($referralCode);

        if ($parent === null) {
            throw ValidationException::withMessages([
                'referral_code' => [__('referral::webhook.signup.invalid_referral')],
            ]);
        }

        $account = DB::transaction(function () use ($parent, $user, $nationalId, $mobile, $finopalUserId): ReferralAccount {
            $created = $this->accounts->create([
                'referral_code' => $this->codes->generate(),
                'referred_by_code' => $parent->referral_code,
                'arrived_via_finonet' => true,
                'national_id' => $this->clean($nationalId),
                'mobile' => $this->clean($mobile),
                'finopal_user_id' => $this->clean($finopalUserId),
                'name' => $user['name'] ?? null,
                'email' => $user['email'] ?? null,
                'birth_date' => $user['birth_date'] ?? null,
                'sheba' => $user['sheba'] ?? null,
                'referral_path' => $this->buildPath($parent),
                'status' => ReferralAccountStatus::Active,
            ]);

            $this->relations->create([
                'referrer_referral_id' => $parent->id,
                'referred_referral_id' => $created->id,
                'depth' => count($parent->ancestorIds()) + 1,
            ]);

            return $created;
        });

        $link = $this->codes->buildLink($account->referral_code);
        $welcomeQueued = false;

        if (config('referral.welcome_sms.enabled') && $account->mobile) {
            $this->notifier->queueWelcome((string) $account->mobile, $account->name, $link);
            $welcomeQueued = true;
        }

        return [
            'success' => true,
            'message' => __('referral::webhook.signup.success'),
            'user_id' => $account->id,
            'user_created' => true,
            'user_skipped' => false,
            'welcome_sms_queued' => $welcomeQueued,
        ];
    }

    protected function buildPath(ReferralAccount $parent): string
    {
        $ancestors = $parent->ancestorIds();
        $ancestors[] = $parent->id;

        return implode(',', $ancestors);
    }

    protected function clean(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return ($value === '' || $value === null) ? null : $value;
    }
}
