<?php

namespace Modules\Referral\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Referral\Enums\ReferralAccountStatus;
use Modules\Referral\Models\ReferralAccount;

class ReferralAccountFactory extends Factory
{
    protected $model = ReferralAccount::class;

    public function definition(): array
    {
        return [
            'referral_code' => strtoupper(fake()->unique()->bothify('??##??')),
            'referred_by_code' => null,
            'arrived_via_finonet' => false,
            'national_id' => fake()->unique()->numerify('##########'),
            'mobile' => '09'.fake()->unique()->numerify('#########'),
            'name' => fake()->name(),
            'status' => ReferralAccountStatus::Active,
        ];
    }

    public function arrivedViaFinonet(): static
    {
        return $this->state(fn (array $attributes) => [
            'arrived_via_finonet' => true,
        ]);
    }
}
