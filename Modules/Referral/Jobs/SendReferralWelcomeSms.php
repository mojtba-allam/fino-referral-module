<?php

namespace Modules\Referral\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Otp\Contracts\SmsGatewayInterface;

/**
 * Sends a rendered welcome SMS to a newly registered representative. Kept as a
 * queued job so the webhook response is not blocked by the SMS gateway.
 */
class SendReferralWelcomeSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public function __construct(
        public string $mobile,
        public string $message
    ) {}

    public function handle(SmsGatewayInterface $gateway): void
    {
        $gateway->send($this->mobile, $this->message);
    }
}
