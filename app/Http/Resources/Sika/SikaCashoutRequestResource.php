<?php

namespace App\Http\Resources\Sika;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SikaCashoutRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'phone' => $this->user->phone,
                ];
            }),
            'coins_requested' => $this->coins_requested,
            'ghs_to_credit' => (float) $this->ghs_to_credit,
            'fee_applied' => (float) $this->fee_applied,
            'net_ghs' => (float) $this->net_ghs,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'tier' => $this->whenLoaded('tier', function () {
                return [
                    'id' => $this->tier->id,
                    'name' => $this->tier->name,
                    'ghs_per_million_coins' => (float) $this->tier->ghs_per_million_coins,
                ];
            }),
            'rejection_reason' => $this->rejection_reason,
            'pbg_credit_reference' => $this->pbg_credit_reference,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'available_at' => $this->available_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    private function getStatusLabel(): string
    {
        return match ($this->status) {
            'PENDING' => 'Pending Review',
            'APPROVED' => 'Approved',
            'REJECTED' => 'Rejected',
            'PROCESSING' => 'Processing',
            'PAID' => 'Paid',
            'FAILED' => 'Failed',
            'REVERSED' => 'Reversed',
            default => $this->status,
        };
    }
}
