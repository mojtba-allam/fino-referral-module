<?php

namespace Modules\Referral\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Referral\Enums\GatewayStatus;
use Modules\Referral\Models\Gateway;
use Modules\Referral\Models\ReferralAccount;
use Modules\Referral\Repositories\GatewayRepositoryInterface;

/**
 * Handles the "gateway registered" inbound webhook. The owner must already
 * exist (signup webhook first). Deduplicates by external_id / merchant_code and
 * records a successful sale with a 100% share for the owning representative.
 */
class RegisterGatewayFromWebhook
{
    public function __construct(
        protected GatewayRepositoryInterface $gateways,
        protected ResolveWebhookOwner $resolveOwner
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data): array
    {
        $user = $data['user'] ?? [];
        $gateway = $data['gateway'] ?? [];

        $owner = $this->resolveOwner->execute(
            $user['national_id'] ?? null,
            $user['mobile'] ?? null,
            $user['finopal_user_id'] ?? null
        );

        if ($owner === null) {
            throw ValidationException::withMessages([
                'user' => [__('referral::webhook.gateway.user_not_found')],
            ]);
        }

        $idempotencyKey = $this->clean($data['idempotency_key'] ?? null);
        $externalId = $this->clean($gateway['external_id'] ?? null);
        $merchantCode = $this->clean($gateway['merchant_code'] ?? null);

        if ($idempotencyKey !== null) {
            $byKey = $this->gateways->findByIdempotencyKey($idempotencyKey);

            if ($byKey !== null && ! $this->sameGateway($byKey, $externalId, $merchantCode)) {
                throw ValidationException::withMessages([
                    'idempotency_key' => [__('referral::webhook.gateway.idempotency_conflict')],
                ]);
            }
        }

        $duplicate = $this->findDuplicate($externalId, $merchantCode);

        if ($duplicate !== null) {
            return $this->respond($owner, $duplicate, false, true);
        }

        $created = $this->gateways->create([
            'referral_account_id' => $owner->id,
            'owner_type' => $owner->owner_type,
            'owner_id' => $owner->owner_id,
            'external_id' => $externalId,
            'merchant_code' => $merchantCode,
            'name' => $gateway['name'] ?? null,
            'amount' => $gateway['amount'] ?? null,
            'website' => $gateway['website'] ?? null,
            'callback_url' => $gateway['callback_url'] ?? null,
            'status' => GatewayStatus::Successful,
            'share_percent' => 100,
            'manager_path' => $owner->ancestorIds(),
            'idempotency_key' => $idempotencyKey,
        ]);

        return $this->respond($owner, $created, true, false);
    }

    private function findDuplicate(?string $externalId, ?string $merchantCode): ?Gateway
    {
        if ($externalId !== null) {
            $byExternal = $this->gateways->findByExternalId($externalId);

            if ($byExternal !== null) {
                return $byExternal;
            }
        }

        if ($merchantCode !== null) {
            return $this->gateways->findByMerchantCode($merchantCode);
        }

        return null;
    }

    private function sameGateway(Gateway $gateway, ?string $externalId, ?string $merchantCode): bool
    {
        return ($externalId !== null && $gateway->external_id === $externalId)
            || ($merchantCode !== null && $gateway->merchant_code === $merchantCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function respond(ReferralAccount $owner, Gateway $gateway, bool $created, bool $skipped): array
    {
        return [
            'success' => true,
            'message' => __('referral::webhook.gateway.'.($created ? 'success' : 'duplicate')),
            'user_id' => $owner->id,
            'gateway_id' => $gateway->id,
            'gateway_sale_id' => $gateway->id,
            'gateway_created' => $created,
            'gateway_skipped' => $skipped,
        ];
    }

    protected function clean(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return ($value === '' || $value === null) ? null : $value;
    }
}
