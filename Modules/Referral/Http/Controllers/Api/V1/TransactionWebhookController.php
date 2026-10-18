<?php

namespace Modules\Referral\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Referral\Http\Requests\FinopalTransactionRequest;
use Modules\Referral\Services\FinopalWebhookService;

class TransactionWebhookController extends Controller
{
    public function __construct(
        protected FinopalWebhookService $webhooks
    ) {}

    /**
     * Receive a transaction webhook and record commission from the `profit`
     * value for the owning representative + upstream manager chain.
     */
    public function __invoke(FinopalTransactionRequest $request): JsonResponse
    {
        $result = $this->webhooks->handleTransaction($request->validated());

        return response()->json($result, 200);
    }
}
