<?php

namespace Tests\Feature\Modules\Referral;

use Illuminate\Support\Facades\Http;
use Modules\Referral\Jobs\ForwardSignupToFinonet;
use Tests\TestCase;

/**
 * Outbound signup forwarder (host acting as a SENDER -> finonet.ir). Fully
 * faked: nothing here hits the network. Mirrors the finopal inbound contract:
 * POST {event, referral_code, user{name,mobile,national_id}} signed with the
 * OUTBOUND secret in X-Finopal-Webhook-Secret. See docs/SENDER.md.
 */
class ForwardSignupToFinonetTest extends TestCase
{
    private const URL = 'https://finonet.test/api/webhooks/finopal/signup';

    protected function shouldSeed(): bool
    {
        return false;
    }

    private function enableForward(array $overrides = []): void
    {
        config()->set('referral.forward', array_merge([
            'enabled' => true,
            'signup_url' => self::URL,
            'secret' => 'outbound-secret',
            'timeout' => 5,
        ], $overrides));
    }

    public function test_forwards_signup_with_contract_payload_and_header(): void
    {
        $this->enableForward();
        Http::fake([self::URL => Http::response(['success' => true, 'user_created' => true], 200)]);

        (new ForwardSignupToFinonet('QM4VGJ7XBL', 'Referred Lead', '09129990001', '1234567890'))->handle();

        Http::assertSent(fn ($request): bool => $request->url() === self::URL
            && $request->hasHeader('X-Finopal-Webhook-Secret', 'outbound-secret')
            && $request['event'] === 'user.registered'
            && $request['referral_code'] === 'QM4VGJ7XBL'
            && $request['user']['mobile'] === '09129990001'
            && $request['user']['national_id'] === '1234567890');
    }

    public function test_drops_empty_optional_user_fields(): void
    {
        $this->enableForward();
        Http::fake([self::URL => Http::response(['success' => true], 200)]);

        (new ForwardSignupToFinonet('QM4VGJ7XBL', 'Name Only', null, null))->handle();

        Http::assertSent(fn ($request): bool => $request['user'] === ['name' => 'Name Only']);
    }

    public function test_skips_when_forwarding_disabled(): void
    {
        $this->enableForward(['enabled' => false]);
        Http::fake();

        (new ForwardSignupToFinonet('QM4VGJ7XBL', 'X', '09120000000'))->handle();

        Http::assertNothingSent();
    }

    public function test_skips_when_secret_or_url_missing(): void
    {
        $this->enableForward(['secret' => '']);
        Http::fake();

        (new ForwardSignupToFinonet('QM4VGJ7XBL', 'X', '09120000000'))->handle();

        Http::assertNothingSent();
    }

    public function test_client_error_is_swallowed_and_not_retried(): void
    {
        $this->enableForward();
        // Unknown referral code at finonet -> 422; must NOT throw (best-effort).
        Http::fake([self::URL => Http::response(['message' => 'invalid referral'], 422)]);

        (new ForwardSignupToFinonet('BADCODE', 'X', '09120000000'))->handle();

        Http::assertSentCount(1);
        $this->assertTrue(true);
    }

    public function test_server_error_throws_so_the_queue_retries(): void
    {
        $this->enableForward();
        Http::fake([self::URL => Http::response('upstream unavailable', 503)]);

        $this->expectException(\Illuminate\Http\Client\RequestException::class);

        (new ForwardSignupToFinonet('QM4VGJ7XBL', 'X', '09120000000'))->handle();
    }
}
