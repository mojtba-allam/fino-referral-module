<?php

namespace Modules\Referral\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinopalSignupRequest extends FormRequest
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
            'referral_code' => ['required', 'string', 'max:64'],
            'user' => ['required', 'array'],
            'user.name' => ['required', 'string', 'max:255'],
            'user.mobile' => ['required', 'string', 'max:20'],
            'user.national_id' => ['required', 'string', 'max:32'],
            'user.email' => ['nullable', 'email', 'max:255'],
            'user.birth_date' => ['nullable', 'date_format:Y-m-d'],
            'user.sheba' => ['nullable', 'string', 'max:40'],
            'user.finopal_user_id' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'referral_code.required' => 'کد معرف الزامی است.',
            'user.required' => 'اطلاعات کاربر الزامی است.',
            'user.name.required' => 'نام کاربر الزامی است.',
            'user.mobile.required' => 'شماره موبایل الزامی است.',
            'user.national_id.required' => 'کد ملی الزامی است.',
            'user.birth_date.date_format' => 'تاریخ تولد باید به شکل YYYY-MM-DD باشد.',
        ];
    }
}
