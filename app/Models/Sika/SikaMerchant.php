<?php

namespace App\Models\Sika;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SikaMerchant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_user_id',
        'business_name',
        'business_type',
        'description',
        'logo',
        'contact_email',
        'contact_phone',
        'status',
        'merchant_code',
        'commission_percent',
        'settings',
        'meta',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'commission_percent' => 'decimal:2',
        'settings' => 'array',
        'meta' => 'array',
        'approved_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_REJECTED = 'rejected';

    protected static function booted(): void
    {
        static::creating(function (SikaMerchant $merchant) {
            if (empty($merchant->merchant_code)) {
                $merchant->merchant_code = self::generateMerchantCode();
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SikaMerchantOrder::class, 'merchant_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function canReceivePayments(): bool
    {
        return $this->isActive();
    }

    public function calculateCommission(int $coins): int
    {
        return (int) floor($coins * ((float) $this->commission_percent / 100));
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public static function generateMerchantCode(): string
    {
        do {
            $code = 'MRC' . strtoupper(Str::random(8));
        } while (self::where('merchant_code', $code)->exists());

        return $code;
    }
}
