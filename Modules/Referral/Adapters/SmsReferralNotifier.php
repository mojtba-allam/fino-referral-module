<?php

namespace Modules\Referral\Adapters;

use Modules\Referral\Contracts\ReferralNotifier;
use Modules\Referral\Jobs\SendReferralWelcomeSms;

/**
 * Finolo notifier: renders the configured welcome template and dispatches the
 * queued SMS job through the Otp SMS gateway.
 */
class SmsReferralNotifier implements ReferralNotifier
{
    public function queueWelcome(string $mobile, ?string $name, string $link): void
    {
        $template = (string) config('referral.welcome_sms.template');
        $message = str_replace(['{name}', '{link}'], [$name ?? '', $link], $template);

        SendReferralWelcomeSms::dispatch($mobile, $message);
    }
}
