<?php

namespace Modules\Referral\Repositories;

use Modules\Referral\Models\WebhookEvent;

class EloquentWebhookEventRepository implements WebhookEventRepositoryInterface
{
    public function __construct(
        protected WebhookEvent $model
    ) {}

    public function record(array $data): WebhookEvent
    {
        return $this->model->create($data);
    }

    public function findByEventAndIdempotencyKey(string $event, string $idempotencyKey): ?WebhookEvent
    {
        return $this->model->newQuery()
            ->where('event', $event)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }
}
