<?php

namespace Modules\Referral\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Referral\Database\Factories\ReferralRelationFactory;

class ReferralRelation extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'referrer_referral_id',
        'referred_referral_id',
        'depth',
    ];

    protected function casts(): array
    {
        return [
            'depth' => 'integer',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(ReferralAccount::class, 'referrer_referral_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(ReferralAccount::class, 'referred_referral_id');
    }

    protected static function newFactory(): ReferralRelationFactory
    {
        return ReferralRelationFactory::new();
    }
}
