<?php

namespace App\Http\Requests\Sika;

use Illuminate\Foundation\Http\FormRequest;

class GiftCoinsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_user_id' => ['required_without:post_id', 'nullable', 'integer', 'exists:users,id'],
            'post_id' => ['required_without:to_user_id', 'nullable', 'integer', 'exists:world_feed_posts,id'],
            'message_id' => ['nullable', 'integer'],
            'coins' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_user_id.required_without' => 'Please select a recipient or post',
            'to_user_id.exists' => 'Recipient not found',
            'post_id.exists' => 'Post not found',
            'coins.required' => 'Please enter the amount of coins',
            'coins.min' => 'Minimum gift is 1 coin',
            'idempotency_key.required' => 'Transaction key is required',
        ];
    }
}
