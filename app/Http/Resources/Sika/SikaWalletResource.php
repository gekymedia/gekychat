<?php

namespace App\Http\Resources\Sika;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SikaWalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'balance' => $this->balance_cached,
            'formatted_balance' => $this->formatBalance($this->balance_cached),
            'status' => $this->status,
            'can_transact' => $this->canTransact(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function formatBalance(int $balance): string
    {
        if ($balance >= 1000000) {
            return number_format($balance / 1000000, 1) . 'M';
        }
        if ($balance >= 1000) {
            return number_format($balance / 1000, 1) . 'K';
        }
        return number_format($balance);
    }
}
