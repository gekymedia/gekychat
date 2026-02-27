<?php

namespace App\Http\Requests\Sika;

use Illuminate\Foundation\Http\FormRequest;

class PurchasePackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pack_id' => ['required', 'integer', 'exists:sika_packs,id'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'pack_id.required' => 'Please select a coin pack',
            'pack_id.exists' => 'Selected coin pack is not available',
            'idempotency_key.required' => 'Transaction key is required',
        ];
    }
}
