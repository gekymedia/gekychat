<?php

namespace App\Models\Sika;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SikaCashoutRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'wallet_id',
        'tier_id',
        'coins_requested',
        'ghs_to_credit',
        'fee_applied',
        'net_ghs',
        'status',
        'approved_by',
        'processed_by',
        'pbg_credit_reference',
        'idempotency_key',
        'rejection_reason',
        'meta',
        'approved_at',
        'paid_at',
        'available_at',
    ];

    protected $casts = [
        'coins_requested' => 'integer',
        'ghs_to_credit' => 'decimal:2',
        'fee_applied' => 'decimal:2',
        'net_ghs' => 'decimal:2',
        'meta' => 'array',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'available_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_PROCESSING = 'PROCESSING';
    public const STATUS_PAID = 'PAID';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_REVERSED = 'REVERSED';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(SikaWallet::class, 'wallet_id');
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(SikaCashoutTier::class, 'tier_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        return $this->isPending();
    }

    public function canBeProcessed(): bool
    {
        return $this->isApproved();
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function findByIdempotencyKey(string $key): ?self
    {
        return self::where('idempotency_key', $key)->first();
    }
}
