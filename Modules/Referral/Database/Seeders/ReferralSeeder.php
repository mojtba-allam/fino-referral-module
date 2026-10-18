<?php

namespace Modules\Referral\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Referral\Enums\ReferralAccountStatus;
use Modules\Referral\Models\ReferralAccount;

/**
 * Seeds a root representative so referral links exist out-of-the-box and the
 * signup webhook has a tree to attach new reps under. Idempotent.
 */
class ReferralSeeder extends Seeder
{
    public function run(): void
    {
        $rootCode = (string) config('referral.referral.root_code', 'ROOT01');

        if (ReferralAccount::where('referral_code', $rootCode)->exists()) {
            return;
        }

        ReferralAccount::create([
            'referral_code' => $rootCode,
            'arrived_via_finonet' => false,
            'name' => 'Root Representative',
            'status' => ReferralAccountStatus::Active,
        ]);
    }
}
