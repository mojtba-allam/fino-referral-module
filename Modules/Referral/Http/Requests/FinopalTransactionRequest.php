<?php

namespace Modules\Referral\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinopalTransactionRequest extends FormRequest
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
            'merchant_id' => ['required', 'string', 'max:64'],
            'profit' => ['required', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'transaction_id' => ['nullable', 'string', 'max:120'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'currency' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'merchant_id.required' => 'شناسه مرچنت الزامی است.',
            'profit.required' => 'مبلغ پورسانت (profit) الزامی است.',
            'profit.numeric' => 'مبلغ پورسانت باید عددی باشد.',
        ];
    }
}
