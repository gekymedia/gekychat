<?php

namespace App\Http\Requests\Sika;

use Illuminate\Foundation\Http\FormRequest;

class MerchantPayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merchant_id' => ['required', 'integer', 'exists:sika_merchants,id'],
            'coins' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required_with:items', 'string', 'max:255'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.coins' => ['required_with:items', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'merchant_id.required' => 'Please select a merchant',
            'merchant_id.exists' => 'Merchant not found',
            'coins.required' => 'Please enter the payment amount',
            'coins.min' => 'Minimum payment is 1 coin',
            'idempotency_key.required' => 'Transaction key is required',
        ];
    }
}
