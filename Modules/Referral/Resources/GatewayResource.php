<?php

namespace Modules\Referral\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GatewayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'merchant_code' => $this->merchant_code,
            'name' => $this->name,
            'amount' => $this->amount,
            'status' => $this->status?->value,
            'share_percent' => $this->share_percent,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
