<?php

namespace Modules\Referral\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Referral\Contracts\ReferralOwnerResolver;
use Modules\Referral\Http\Requests\ActivateReferralRequest;
use Modules\Referral\Services\ReferralService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Host-facing referral API for the authenticated user.
 */
class ReferralController extends Controller
{
    public function __construct(
        protected ReferralService $referrals,
        protected ReferralOwnerResolver $owners
    ) {}

    /**
     * Show the current user's referral status. A user who arrived via a finonet
     * link gets show_referral_option=false (no code/link exposed).
     */
    public function me(): JsonResponse
    {
        $owner = $this->owners->current();

        if ($owner === null) {
            return response()->json(['message' => __('api.errors.unauthorized')], Response::HTTP_UNAUTHORIZED);
        }

        return response()->json([
            'data' => $this->referrals->present($owner),
        ]);
    }

    /**
     * Provision a referral code + link for an eligible user.
     */
    public function activate(ActivateReferralRequest $request): JsonResponse
    {
        $owner = $this->owners->current();

        if ($owner === null) {
            return response()->json(['message' => __('api.errors.unauthorized')], Response::HTTP_UNAUTHORIZED);
        }

        $this->referrals->activate($owner);

        return response()->json([
            'data' => $this->referrals->present($owner),
        ]);
    }
}
