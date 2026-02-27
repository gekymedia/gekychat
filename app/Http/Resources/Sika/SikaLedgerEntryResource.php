<?php

namespace App\Http\Resources\Sika;

use App\Models\Sika\SikaLedgerEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SikaLedgerEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'direction' => $this->direction,
            'coins' => $this->coins,
            'formatted_coins' => $this->formatCoins(),
            'status' => $this->status,
            'balance_after' => $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'meta' => $this->getSafeMetaData(),
            'created_at' => $this->created_at->toIso8601String(),
            'created_at_human' => $this->created_at->diffForHumans(),
        ];
    }

    private function getTypeLabel(): string
    {
        return match ($this->type) {
            SikaLedgerEntry::TYPE_PURCHASE_CREDIT => 'Coin Purchase',
            SikaLedgerEntry::TYPE_TRANSFER_OUT => 'Transfer Sent',
            SikaLedgerEntry::TYPE_TRANSFER_IN => 'Transfer Received',
            SikaLedgerEntry::TYPE_GIFT_OUT => 'Gift Sent',
            SikaLedgerEntry::TYPE_GIFT_IN => 'Gift Received',
            SikaLedgerEntry::TYPE_SPEND => 'Spent',
            SikaLedgerEntry::TYPE_MERCHANT_PAY => 'Merchant Payment',
            SikaLedgerEntry::TYPE_MERCHANT_RECEIVE => 'Merchant Income',
            SikaLedgerEntry::TYPE_CASHOUT_DEBIT => 'Cashout',
            SikaLedgerEntry::TYPE_REFUND => 'Refund',
            SikaLedgerEntry::TYPE_ADMIN_ADJUST => 'Adjustment',
            default => $this->type,
        };
    }

    private function formatCoins(): string
    {
        $prefix = $this->direction === SikaLedgerEntry::DIRECTION_CREDIT ? '+' : '-';
        return $prefix . number_format($this->coins);
    }

    private function getSafeMetaData(): array
    {
        $meta = $this->meta ?? [];
        
        $safeKeys = [
            'pack_name',
            'to_user_id',
            'from_user_id',
            'post_id',
            'message_id',
            'merchant_name',
            'order_reference',
            'note',
        ];

        return array_intersect_key($meta, array_flip($safeKeys));
    }
}
