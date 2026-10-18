<?php

namespace Modules\Referral\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Referral\Http\Requests\FinopalGatewayRequest;
use Modules\Referral\Services\FinopalWebhookService;

class GatewayWebhookController extends Controller
{
    public function __construct(
        protected FinopalWebhookService $webhooks
    ) {}

    /**
     * Receive the "gateway registered" webhook and record a 100% sale for the
     * owning representative, or skip if already present.
     */
    public function __invoke(FinopalGatewayRequest $request): JsonResponse
    {
        $result = $this->webhooks->handleGateway($request->validated());

        return response()->json($result, 200);
    }
}
