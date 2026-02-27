<?php

namespace App\Http\Requests\Sika;

use Illuminate\Foundation\Http\FormRequest;

class AdminAdjustRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'coins' => ['required', 'integer', 'min:1'],
            'direction' => ['required', 'string', 'in:CREDIT,DEBIT'],
            'reason' => ['required', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Please select a user',
            'user_id.exists' => 'User not found',
            'coins.required' => 'Please enter the amount of coins',
            'coins.min' => 'Minimum adjustment is 1 coin',
            'direction.required' => 'Please select credit or debit',
            'direction.in' => 'Direction must be CREDIT or DEBIT',
            'reason.required' => 'Please provide a reason for this adjustment',
            'idempotency_key.required' => 'Transaction key is required',
        ];
    }
}
