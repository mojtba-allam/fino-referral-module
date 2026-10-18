<?php

namespace Modules\Referral\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Referral\Database\Factories\GatewayFactory;
use Modules\Referral\Enums\GatewayStatus;

class Gateway extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'finopal_gateways';

    protected $fillable = [
        'referral_account_id',
        'owner_type',
        'owner_id',
        'external_id',
        'merchant_code',
        'name',
        'amount',
        'website',
        'callback_url',
        'status',
        'share_percent',
        'manager_path',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'share_percent' => 'integer',
            'status' => GatewayStatus::class,
            'manager_path' => 'array',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ReferralAccount::class, 'referral_account_id');
    }

    protected static function newFactory(): GatewayFactory
    {
        return GatewayFactory::new();
    }
}
