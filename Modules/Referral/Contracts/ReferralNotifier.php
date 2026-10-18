<?php

namespace Modules\Referral\Contracts;

/**
 * Delivers the welcome message to a newly created representative. Kept as a
 * contract so the module stays host-agnostic: Finolo binds an SMS notifier,
 * another project may bind email or a no-op. Webhook-created reps have no host
 * user, so this takes the raw contact rather than a ReferralOwner.
 */
interface ReferralNotifier
{
    public function queueWelcome(string $mobile, ?string $name, string $link): void;
}
