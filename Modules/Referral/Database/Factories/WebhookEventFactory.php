<?php

namespace Modules\Referral\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Referral\Models\WebhookEvent;

class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return [
            'event' => 'user.registered',
            'idempotency_key' => fake()->unique()->uuid(),
            'payload' => [],
            'status' => 'received',
        ];
    }
}
