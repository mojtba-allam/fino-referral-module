<?php

namespace Modules\Referral\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Referral\Database\Factories\WebhookEventFactory;

class WebhookEvent extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'finopal_webhook_events';

    protected $fillable = [
        'event',
        'idempotency_key',
        'payload',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    protected static function newFactory(): WebhookEventFactory
    {
        return WebhookEventFactory::new();
    }
}
