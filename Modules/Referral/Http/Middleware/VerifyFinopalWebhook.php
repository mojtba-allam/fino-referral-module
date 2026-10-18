<?php

namespace Modules\Referral\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyFinopalWebhook
{
    /**
     * Verify the incoming webhook genuinely originates from finopal/finonet by
     * comparing the shared secret against the X-Finopal-Webhook-Secret header
     * (or an Authorization: Bearer fallback). A missing/empty configured secret
     * or a mismatch yields HTTP 401, matching the signup/gateway contract.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('referral.webhook_secret', '');

        if ($expected === '') {
            return response()->json([
                'success' => false,
                'message' => 'راز وب‌هوک فینوپال پیکربندی نشده است.',
            ], 401);
        }

        $provided = $this->resolveSecret($request);

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'success' => false,
                'message' => 'احراز هویت وب‌هوک نامعتبر است.',
            ], 401);
        }

        return $next($request);
    }

    /**
     * Read the secret from the dedicated header, falling back to a bearer token.
     */
    protected function resolveSecret(Request $request): string
    {
        $header = $request->header('X-Finopal-Webhook-Secret');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        $bearer = $request->bearerToken();

        return is_string($bearer) ? $bearer : '';
    }
}
