<?php

namespace Modules\Referral\Repositories;

use Modules\Referral\Models\WebhookEvent;

interface WebhookEventRepositoryInterface
{
    public function record(array $data): WebhookEvent;

    public function findByEventAndIdempotencyKey(string $event, string $idempotencyKey): ?WebhookEvent;
}
