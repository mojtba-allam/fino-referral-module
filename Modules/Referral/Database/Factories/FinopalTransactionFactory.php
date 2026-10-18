<?php

namespace Modules\Referral\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Referral\Enums\TransactionStatus;
use Modules\Referral\Models\FinopalTransaction;
use Modules\Referral\Models\Gateway;
use Modules\Referral\Models\ReferralAccount;

class FinopalTransactionFactory extends Factory
{
    protected $model = FinopalTransaction::class;

    public function definition(): array
    {
        return [
            'gateway_id' => Gateway::factory(),
            'referral_account_id' => ReferralAccount::factory(),
            'merchant_code' => 'MRC-'.fake()->numerify('####'),
            'external_txn_id' => 'fp-txn-'.fake()->unique()->numerify('######'),
            'idempotency_key' => 'txn-'.fake()->unique()->uuid(),
            'amount' => fake()->randomNumber(6),
            'profit' => fake()->numberBetween(1000, 90000),
            'commission' => fake()->numberBetween(1000, 90000),
            'currency' => 'IRR',
            'status' => TransactionStatus::Recorded,
            'manager_path' => [],
            'payload' => [],
        ];
    }
}
