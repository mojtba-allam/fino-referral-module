<?php

namespace Tests\Feature\Modules\Referral;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Referral\Models\ReferralAccount;
use Modules\User\Models\User;
use Tests\TestCase;

class ReferralApiTest extends TestCase
{
    use RefreshDatabase;

    protected function shouldSeed(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['referral.referral.link_base' => 'https://finopal.ir/login']);
    }

    public function test_referral_option_is_hidden_for_finonet_arrived_user(): void
    {
        $user = User::factory()->create();

        ReferralAccount::factory()->create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => (string) $user->id,
            'arrived_via_finonet' => true,
            'referral_code' => 'FINNET1',
            'national_id' => null,
            'mobile' => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/referral/me')
            ->assertStatus(200)
            ->assertJsonPath('data.show_referral_option', false)
            ->assertJsonPath('data.referral_code', null);
    }

    public function test_user_without_account_sees_option_and_can_activate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/referral/me')
            ->assertStatus(200)
            ->assertJsonPath('data.show_referral_option', true)
            ->assertJsonPath('data.referral_code', null);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/referral/activate')
            ->assertStatus(200)
            ->assertJsonPath('data.show_referral_option', true);

        $code = $response->json('data.referral_code');
        $this->assertNotEmpty($code);
        $this->assertStringContainsString('finonet_ref='.$code, $response->json('data.referral_link'));

        $this->assertDatabaseHas('referral_accounts', [
            'owner_type' => $user->getMorphClass(),
            'owner_id' => (string) $user->id,
            'arrived_via_finonet' => false,
        ]);
    }

    public function test_unauthenticated_cannot_access_referral_me(): void
    {
        $this->getJson('/api/v1/referral/me')->assertStatus(401);
    }
}
