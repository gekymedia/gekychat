<?php

namespace App\Models\Sika;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SikaWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'balance_cached',
        'status',
    ];

    protected $casts = [
        'balance_cached' => 'integer',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_FROZEN = 'frozen';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SikaLedgerEntry::class, 'wallet_id');
    }

    public function cashoutRequests(): HasMany
    {
        return $this->hasMany(SikaCashoutRequest::class, 'wallet_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isFrozen(): bool
    {
        return $this->status === self::STATUS_FROZEN;
    }

    public function canTransact(): bool
    {
        return $this->isActive();
    }

    public function calculateLedgerBalance(): int
    {
        $credits = $this->ledgerEntries()
            ->where('status', SikaLedgerEntry::STATUS_POSTED)
            ->where('direction', SikaLedgerEntry::DIRECTION_CREDIT)
            ->sum('coins');

        $debits = $this->ledgerEntries()
            ->where('status', SikaLedgerEntry::STATUS_POSTED)
            ->where('direction', SikaLedgerEntry::DIRECTION_DEBIT)
            ->sum('coins');

        return (int) ($credits - $debits);
    }

    public function syncBalanceFromLedger(): void
    {
        $this->balance_cached = $this->calculateLedgerBalance();
        $this->save();
    }

    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            ['balance_cached' => 0, 'status' => self::STATUS_ACTIVE]
        );
    }
}
