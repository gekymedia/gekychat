<?php

namespace App\Models\Sika;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SikaMerchantOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'merchant_id',
        'buyer_user_id',
        'order_reference',
        'coins_amount',
        'commission_coins',
        'net_coins',
        'status',
        'idempotency_key',
        'ledger_group_id',
        'description',
        'items',
        'meta',
        'paid_at',
        'completed_at',
        'refunded_at',
    ];

    protected $casts = [
        'coins_amount' => 'integer',
        'commission_coins' => 'integer',
        'net_coins' => 'integer',
        'items' => 'array',
        'meta' => 'array',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DISPUTED = 'disputed';

    protected static function booted(): void
    {
        static::creating(function (SikaMerchantOrder $order) {
            if (empty($order->order_reference)) {
                $order->order_reference = self::generateOrderReference();
            }
        });
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(SikaMerchant::class, 'merchant_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    public function canBePaid(): bool
    {
        return $this->isPending();
    }

    public function canBeRefunded(): bool
    {
        return $this->isPaid() || $this->isCompleted();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeForMerchant($query, int $merchantId)
    {
        return $query->where('merchant_id', $merchantId);
    }

    public function scopeForBuyer($query, int $userId)
    {
        return $query->where('buyer_user_id', $userId);
    }

    public static function generateOrderReference(): string
    {
        return 'ORD' . date('Ymd') . strtoupper(Str::random(8));
    }

    public static function findByIdempotencyKey(string $key): ?self
    {
        return self::where('idempotency_key', $key)->first();
    }
}
