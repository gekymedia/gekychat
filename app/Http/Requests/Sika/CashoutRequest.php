<?php

namespace App\Http\Requests\Sika;

use Illuminate\Foundation\Http\FormRequest;

class CashoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coins' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'coins.required' => 'Please enter the amount of coins to cash out',
            'coins.min' => 'Minimum cashout is 1 coin',
            'idempotency_key.required' => 'Transaction key is required',
        ];
    }
}
