<?php

namespace Modules\Referral\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Referral\Enums\GatewayStatus;
use Modules\Referral\Models\Gateway;
use Modules\Referral\Models\ReferralAccount;

class GatewayFactory extends Factory
{
    protected $model = Gateway::class;

    public function definition(): array
    {
        return [
            'referral_account_id' => ReferralAccount::factory(),
            'external_id' => 'fp-gw-'.fake()->unique()->numerify('####'),
            'merchant_code' => 'MRC-'.fake()->unique()->numerify('####'),
            'name' => fake()->company(),
            'amount' => fake()->randomNumber(6),
            'status' => GatewayStatus::Successful,
            'share_percent' => 100,
        ];
    }
}
