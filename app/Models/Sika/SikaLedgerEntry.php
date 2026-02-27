<?php

namespace App\Models\Sika;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SikaLedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'type',
        'direction',
        'coins',
        'status',
        'group_id',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'meta',
        'balance_after',
    ];

    protected $casts = [
        'coins' => 'integer',
        'balance_after' => 'integer',
        'meta' => 'array',
    ];

    public const TYPE_PURCHASE_CREDIT = 'PURCHASE_CREDIT';
    public const TYPE_TRANSFER_OUT = 'TRANSFER_OUT';
    public const TYPE_TRANSFER_IN = 'TRANSFER_IN';
    public const TYPE_GIFT_OUT = 'GIFT_OUT';
    public const TYPE_GIFT_IN = 'GIFT_IN';
    public const TYPE_SPEND = 'SPEND';
    public const TYPE_MERCHANT_PAY = 'MERCHANT_PAY';
    public const TYPE_MERCHANT_RECEIVE = 'MERCHANT_RECEIVE';
    public const TYPE_CASHOUT_DEBIT = 'CASHOUT_DEBIT';
    public const TYPE_REFUND = 'REFUND';
    public const TYPE_ADMIN_ADJUST = 'ADMIN_ADJUST';

    public const DIRECTION_CREDIT = 'CREDIT';
    public const DIRECTION_DEBIT = 'DEBIT';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_POSTED = 'POSTED';
    public const STATUS_REVERSED = 'REVERSED';

    public const REFERENCE_TYPE_PBG_DEBIT = 'pbg_debit';
    public const REFERENCE_TYPE_PBG_CREDIT = 'pbg_credit';
    public const REFERENCE_TYPE_CHAT_MESSAGE = 'chat_message';
    public const REFERENCE_TYPE_WORLDFEED_POST = 'worldfeed_post';
    public const REFERENCE_TYPE_MERCHANT_ORDER = 'merchant_order';

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(SikaWallet::class, 'wallet_id');
    }

    public function isCredit(): bool
    {
        return $this->direction === self::DIRECTION_CREDIT;
    }

    public function isDebit(): bool
    {
        return $this->direction === self::DIRECTION_DEBIT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isReversed(): bool
    {
        return $this->status === self::STATUS_REVERSED;
    }

    public function getMetaValue(string $key, $default = null)
    {
        return $this->meta[$key] ?? $default;
    }

    public function scopePosted($query)
    {
        return $query->where('status', self::STATUS_POSTED);
    }

    public function scopeCredits($query)
    {
        return $query->where('direction', self::DIRECTION_CREDIT);
    }

    public function scopeDebits($query)
    {
        return $query->where('direction', self::DIRECTION_DEBIT);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByGroupId($query, string $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    public static function findByIdempotencyKey(string $key): ?self
    {
        return self::where('idempotency_key', $key)->first();
    }
}
