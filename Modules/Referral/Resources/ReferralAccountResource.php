<?php

namespace Modules\Referral\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Referral\Support\ReferralCodeGenerator;

class ReferralAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ReferralCodeGenerator $generator */
        $generator = app(ReferralCodeGenerator::class);

        return [
            'id' => $this->id,
            'referral_code' => $this->referral_code,
            'referral_link' => $this->referral_code ? $generator->buildLink((string) $this->referral_code) : null,
            'arrived_via_finonet' => $this->arrived_via_finonet,
            'status' => $this->status?->value,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
