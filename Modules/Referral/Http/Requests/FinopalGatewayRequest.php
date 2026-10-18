<?php

namespace Modules\Referral\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinopalGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event' => ['nullable', 'string', 'max:64'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'user' => ['required', 'array'],
            'user.national_id' => ['required', 'string', 'max:32'],
            'user.mobile' => ['required', 'string', 'max:20'],
            'user.finopal_user_id' => ['nullable', 'string', 'max:64'],
            'user.name' => ['nullable', 'string', 'max:255'],
            'gateway' => ['required', 'array'],
            'gateway.external_id' => ['required_without:gateway.merchant_code', 'nullable', 'string', 'max:64'],
            'gateway.merchant_code' => ['required_without:gateway.external_id', 'nullable', 'string', 'max:64'],
            'gateway.name' => ['nullable', 'string', 'max:255'],
            'gateway.amount' => ['nullable', 'numeric', 'min:0'],
            'gateway.website' => ['nullable', 'url', 'max:255'],
            'gateway.callback_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user.national_id.required' => 'کد ملی الزامی است.',
            'user.mobile.required' => 'شماره موبایل الزامی است.',
            'gateway.external_id.required_without' => 'حداقل یکی از external_id یا merchant_code الزامی است.',
            'gateway.merchant_code.required_without' => 'حداقل یکی از external_id یا merchant_code الزامی است.',
        ];
    }
}
