<?php

namespace Tests\Feature\Modules\Referral;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Referral\Jobs\SendReferralWelcomeSms;
use Modules\Referral\Models\FinopalTransaction;
use Modules\Referral\Models\Gateway;
use Modules\Referral\Models\ReferralAccount;
use Tests\TestCase;

class FinopalWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'test-secret';

    protected function shouldSeed(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'referral.webhook_secret' => $this->secret,
            'referral.welcome_sms.enabled' => true,
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function headers(?string $secret = null): array
    {
        return ['X-Finopal-Webhook-Secret' => $secret ?? $this->secret];
    }

    protected function parentAccount(string $code = 'PARENT1'): ReferralAccount
    {
        return ReferralAccount::factory()->create([
            'referral_code' => $code,
            'arrived_via_finonet' => false,
            'national_id' => null,
            'mobile' => null,
        ]);
    }

    public function test_signup_creates_representative_and_queues_welcome(): void
    {
        Queue::fake();
        $this->parentAccount();

        $response = $this->postJson('/api/webhooks/finopal/signup', [
            'event' => 'user.registered',
            'referral_code' => 'PARENT1',
            'user' => [
                'name' => 'Finopal User',
                'mobile' => '09123334455',
                'national_id' => '1234567890',
                'email' => 'user@example.com',
                'finopal_user_id' => 'FP-USER-1001',
            ],
        ], $this->headers());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user_created', true)
            ->assertJsonPath('welcome_sms_queued', true);

        $this->assertDatabaseHas('referral_accounts', [
            'national_id' => '1234567890',
            'referred_by_code' => 'PARENT1',
            'arrived_via_finonet' => true,
        ]);

        // A tree edge and an audit log row are recorded.
        $this->assertDatabaseHas('referral_relations', ['referred_referral_id' => $response->json('user_id')]);
        $this->assertDatabaseHas('finopal_webhook_events', ['event' => 'user.registered', 'status' => 'processed']);

        Queue::assertPushed(SendReferralWelcomeSms::class);
    }

    public function test_signup_duplicate_skips(): void
    {
        $this->parentAccount();

        $payload = [
            'referral_code' => 'PARENT1',
            'user' => ['name' => 'X', 'mobile' => '09123334455', 'national_id' => '1234567890'],
        ];

        $this->postJson('/api/webhooks/finopal/signup', $payload, $this->headers())->assertStatus(200);

        $this->postJson('/api/webhooks/finopal/signup', $payload, $this->headers())
            ->assertStatus(200)
            ->assertJsonPath('user_created', false)
            ->assertJsonPath('user_skipped', true);

        $this->assertSame(2, ReferralAccount::count());
    }

    public function test_signup_invalid_referral_code_returns_422(): void
    {
        $this->postJson('/api/webhooks/finopal/signup', [
            'referral_code' => 'NOPE',
            'user' => ['name' => 'X', 'mobile' => '09990001122', 'national_id' => '5554443332'],
        ], $this->headers())->assertStatus(422);
    }

    public function test_wrong_secret_returns_401(): void
    {
        $this->postJson('/api/webhooks/finopal/signup', [
            'referral_code' => 'PARENT1',
            'user' => ['name' => 'X', 'mobile' => '09120000000', 'national_id' => '0000000000'],
        ], $this->headers('wrong'))->assertStatus(401);

        $this->postJson('/api/webhooks/finopal/gateway', [], $this->headers('wrong'))->assertStatus(401);
        $this->postJson('/api/webhooks/finopal/transaction', [], $this->headers('wrong'))->assertStatus(401);
    }

    public function test_gateway_after_signup_creates_sale(): void
    {
        $this->signupUser();

        $response = $this->postJson('/api/webhooks/finopal/gateway', [
            'idempotency_key' => 'fp-gateway-1',
            'user' => ['mobile' => '09123334455', 'national_id' => '1234567890'],
            'gateway' => [
                'external_id' => 'fp-gw-1001',
                'merchant_code' => 'MRC-1001',
                'name' => 'Sample Shop',
                'amount' => 1500000,
            ],
        ], $this->headers());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('gateway_created', true)
            ->assertJsonStructure(['user_id', 'gateway_id', 'gateway_sale_id']);

        $this->assertDatabaseHas('finopal_gateways', [
            'external_id' => 'fp-gw-1001',
            'merchant_code' => 'MRC-1001',
            'share_percent' => 100,
        ]);
    }

    public function test_gateway_before_signup_returns_422(): void
    {
        $this->postJson('/api/webhooks/finopal/gateway', [
            'user' => ['mobile' => '09120001122', 'national_id' => '9998887776'],
            'gateway' => ['external_id' => 'fp-gw-missing', 'merchant_code' => 'MRC-MISSING'],
        ], $this->headers())->assertStatus(422);

        $this->assertDatabaseCount('finopal_gateways', 0);
    }

    public function test_gateway_repeat_is_skipped(): void
    {
        $this->signupUser();

        $payload = [
            'idempotency_key' => 'fp-gateway-1',
            'user' => ['mobile' => '09123334455', 'national_id' => '1234567890'],
            'gateway' => ['external_id' => 'fp-gw-1001', 'merchant_code' => 'MRC-1001', 'name' => 'Sample Shop'],
        ];

        $this->postJson('/api/webhooks/finopal/gateway', $payload, $this->headers())->assertStatus(200);
        $this->postJson('/api/webhooks/finopal/gateway', $payload, $this->headers())
            ->assertStatus(200)
            ->assertJsonPath('gateway_created', false)
            ->assertJsonPath('gateway_skipped', true);

        $this->assertSame(1, Gateway::count());
    }

    public function test_gateway_idempotency_key_conflict_returns_422(): void
    {
        $this->signupUser();

        $this->postJson('/api/webhooks/finopal/gateway', [
            'idempotency_key' => 'shared-key',
            'user' => ['mobile' => '09123334455', 'national_id' => '1234567890'],
            'gateway' => ['external_id' => 'fp-gw-A', 'merchant_code' => 'MRC-A'],
        ], $this->headers())->assertStatus(200);

        // Same idempotency key, different gateway -> conflict.
        $this->postJson('/api/webhooks/finopal/gateway', [
            'idempotency_key' => 'shared-key',
            'user' => ['mobile' => '09123334455', 'national_id' => '1234567890'],
            'gateway' => ['external_id' => 'fp-gw-B', 'merchant_code' => 'MRC-B'],
        ], $this->headers())->assertStatus(422);
    }

    public function test_transaction_records_commission_from_profit(): void
    {
        $this->signupUser();
        $this->createGateway();

        $response = $this->postJson('/api/webhooks/finopal/transaction', [
            'merchant_id' => 'MRC-1001',
            'transaction_id' => 'TXN-1',
            'profit' => 250000,
            'amount' => 1000000,
        ], $this->headers());

        $response->assertStatus(200)
            ->assertJsonPath('transaction_created', true)
            ->assertJsonPath('commission', 250000);

        $this->assertDatabaseHas('finopal_transactions', [
            'external_txn_id' => 'TXN-1',
            'profit' => 250000,
            'commission' => 250000,
        ]);
    }

    public function test_transaction_duplicate_is_skipped(): void
    {
        $this->signupUser();
        $this->createGateway();

        $payload = ['merchant_id' => 'MRC-1001', 'transaction_id' => 'TXN-1', 'profit' => 100];
        $this->postJson('/api/webhooks/finopal/transaction', $payload, $this->headers())->assertStatus(200);
        $this->postJson('/api/webhooks/finopal/transaction', $payload, $this->headers())
            ->assertStatus(200)
            ->assertJsonPath('transaction_created', false);

        $this->assertSame(1, FinopalTransaction::count());
    }

    public function test_transaction_unknown_merchant_returns_422(): void
    {
        $this->postJson('/api/webhooks/finopal/transaction', [
            'merchant_id' => 'MRC-UNKNOWN',
            'profit' => 100,
        ], $this->headers())->assertStatus(422);
    }

    protected function signupUser(): void
    {
        $this->parentAccount();

        $this->postJson('/api/webhooks/finopal/signup', [
            'referral_code' => 'PARENT1',
            'user' => ['name' => 'Finopal User', 'mobile' => '09123334455', 'national_id' => '1234567890'],
        ], $this->headers())->assertStatus(200);
    }

    protected function createGateway(): void
    {
        $this->postJson('/api/webhooks/finopal/gateway', [
            'user' => ['mobile' => '09123334455', 'national_id' => '1234567890'],
            'gateway' => ['external_id' => 'fp-gw-1001', 'merchant_code' => 'MRC-1001'],
        ], $this->headers())->assertStatus(200);
    }
}
