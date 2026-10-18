<?php

namespace Modules\Referral\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Referral\Database\Factories\ReferralAccountFactory;
use Modules\Referral\Enums\ReferralAccountStatus;

class ReferralAccount extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'owner_type',
        'owner_id',
        'referral_code',
        'referred_by_code',
        'arrived_via_finonet',
        'national_id',
        'mobile',
        'finopal_user_id',
        'name',
        'email',
        'birth_date',
        'sheba',
        'referral_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'arrived_via_finonet' => 'boolean',
            'birth_date' => 'date',
            'status' => ReferralAccountStatus::class,
        ];
    }

    protected static function newFactory(): ReferralAccountFactory
    {
        return ReferralAccountFactory::new();
    }

    /**
     * The host user this account is linked to (optional: webhook-created reps
     * may have no host account yet).
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The account that referred this one, by referral code.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referred_by_code', 'referral_code');
    }

    /**
     * Direct children (users this account referred).
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by_code', 'referral_code');
    }

    /**
     * Ancestor account ids from the materialized path (root-first).
     *
     * @return list<string>
     */
    public function ancestorIds(): array
    {
        $path = (string) $this->referral_path;

        if ($path === '') {
            return [];
        }

        return array_values(array_filter(explode(',', $path)));
    }
}
