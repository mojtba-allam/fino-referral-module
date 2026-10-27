<?php

namespace Modules\Referral\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Outbound signup webhook (the host acts as a SENDER). When a user signs up on
 * this host carrying a finonet_ref code, this job forwards a signup webhook to
 * the sales organization (finonet.ir) — exactly like finopal does — so the
 * referrer is credited there. Guarded by config('referral.forward.enabled').
 *
 * Best-effort by design: a 4xx (permanent, e.g. validation) is logged and not
 * retried; a 5xx/network error rethrows so the queue retries. It must never
 * bubble up into the signup flow. See docs/SENDER.md.
 */
class ForwardSignupToFinonet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public string $referralCode,
        public ?string $name = null,
        public ?string $mobile = null,
        public ?string $nationalId = null,
    ) {}

    public function handle(): void
    {
        /** @var array{enabled?:bool,signup_url?:string,secret?:string,timeout?:int} $config */
        $config = (array) config('referral.forward', []);

        if (! ($config['enabled'] ?? false)) {
            return;
        }

        $url = (string) ($config['signup_url'] ?? '');
        $secret = (string) ($config['secret'] ?? '');

        if ($url === '' || $secret === '') {
            Log::warning('referral.forward_skipped', ['reason' => 'missing signup_url or secret']);

            return;
        }

        $user = array_filter([
            'name' => $this->name,
            'mobile' => $this->mobile,
            'national_id' => $this->nationalId,
        ], fn ($value) => $value !== null && $value !== '');

        $response = Http::timeout((int) ($config['timeout'] ?? 15))
            ->withHeaders([
                'X-Finopal-Webhook-Secret' => $secret,
                'Accept' => 'application/json',
            ])
            ->asJson()
            ->post($url, [
                'event' => 'user.registered',
                'referral_code' => $this->referralCode,
                'user' => $user,
            ]);

        if ($response->clientError()) {
            // Permanent rejection (e.g. validation) — do not retry and never break signup.
            Log::warning('referral.forward_rejected', [
                'status' => $response->status(),
                'referral_code' => $this->referralCode,
                'body' => $response->body(),
            ]);

            return;
        }

        if ($response->failed()) {
            // Server error — log and rethrow so the queue retries.
            Log::warning('referral.forward_failed', [
                'status' => $response->status(),
                'referral_code' => $this->referralCode,
                'body' => $response->body(),
            ]);

            $response->throw();
        }
    }
}
