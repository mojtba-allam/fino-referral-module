<?php

namespace Modules\Referral\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Referral\Models\ReferralAccount;
use Modules\Referral\Models\ReferralRelation;

class ReferralRelationFactory extends Factory
{
    protected $model = ReferralRelation::class;

    public function definition(): array
    {
        return [
            'referrer_referral_id' => ReferralAccount::factory(),
            'referred_referral_id' => ReferralAccount::factory(),
            'depth' => 1,
        ];
    }
}
