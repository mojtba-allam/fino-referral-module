<?php

namespace Modules\Referral\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Referral\Http\Requests\FinopalSignupRequest;
use Modules\Referral\Services\FinopalWebhookService;

class SignupWebhookController extends Controller
{
    public function __construct(
        protected FinopalWebhookService $webhooks
    ) {}

    /**
     * Receive the "user registered" webhook from finopal and register the
     * representative under the referral tree.
     */
    public function __invoke(FinopalSignupRequest $request): JsonResponse
    {
        $result = $this->webhooks->handleSignup($request->validated());

        return response()->json($result, 200);
    }
}
