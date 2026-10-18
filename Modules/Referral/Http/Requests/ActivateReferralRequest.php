<?php

namespace Modules\Referral\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Provisions a referral code for the authenticated, eligible user. No payload
 * is required; authorize ensures a signed-in host user is present.
 */
class ActivateReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
