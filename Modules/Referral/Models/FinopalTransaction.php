<?php

namespace Modules\Referral\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Referral\Database\Factories\FinopalTransactionFactory;
use Modules\Referral\Enums\TransactionStatus;

class FinopalTransaction extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'finopal_transactions';

    protected $fillable = [
        'gateway_id',
        'referral_account_id',
        'merchant_code',
        'external_txn_id',
        'idempotency_key',
        'amount',
        'profit',
        'commission',
        'currency',
        'status',
        'manager_path',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'profit' => 'integer',
            'commission' => 'integer',
            'status' => TransactionStatus::class,
            'manager_path' => 'array',
            'payload' => 'array',
        ];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class, 'gateway_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ReferralAccount::class, 'referral_account_id');
    }

    protected static function newFactory(): FinopalTransactionFactory
    {
        return FinopalTransactionFactory::new();
    }
}
