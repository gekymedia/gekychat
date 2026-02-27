<?php

namespace App\Http\Requests\Sika;

use Illuminate\Foundation\Http\FormRequest;

class TransferCoinsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'coins' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_user_id.required' => 'Please select a recipient',
            'to_user_id.exists' => 'Recipient not found',
            'coins.required' => 'Please enter the amount of coins',
            'coins.min' => 'Minimum transfer is 1 coin',
            'idempotency_key.required' => 'Transaction key is required',
        ];
    }
}
