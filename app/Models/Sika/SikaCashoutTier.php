<?php

namespace App\Models\Sika;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SikaCashoutTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'min_coins',
        'max_coins',
        'ghs_per_million_coins',
        'fee_percent',
        'fee_flat_ghs',
        'daily_limit',
        'weekly_limit',
        'monthly_limit',
        'hold_days',
        'is_active',
    ];

    protected $casts = [
        'min_coins' => 'integer',
        'max_coins' => 'integer',
        'ghs_per_million_coins' => 'decimal:2',
        'fee_percent' => 'decimal:2',
        'fee_flat_ghs' => 'decimal:2',
        'daily_limit' => 'integer',
        'weekly_limit' => 'integer',
        'monthly_limit' => 'integer',
        'hold_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function cashoutRequests(): HasMany
    {
        return $this->hasMany(SikaCashoutRequest::class, 'tier_id');
    }

    public function calculateGhsForCoins(int $coins): float
    {
        return ($coins / 1_000_000) * (float) $this->ghs_per_million_coins;
    }

    public function calculateFee(float $ghsAmount): float
    {
        $percentFee = $ghsAmount * ((float) $this->fee_percent / 100);
        return $percentFee + (float) $this->fee_flat_ghs;
    }

    public function calculateNetGhs(int $coins): array
    {
        $grossGhs = $this->calculateGhsForCoins($coins);
        $fee = $this->calculateFee($grossGhs);
        $netGhs = max(0, $grossGhs - $fee);

        return [
            'gross_ghs' => round($grossGhs, 2),
            'fee' => round($fee, 2),
            'net_ghs' => round($netGhs, 2),
        ];
    }

    public function isWithinRange(int $coins): bool
    {
        if ($coins < $this->min_coins) {
            return false;
        }

        if ($this->max_coins !== null && $coins > $this->max_coins) {
            return false;
        }

        return true;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCoins($query, int $coins)
    {
        return $query->where('min_coins', '<=', $coins)
            ->where(function ($q) use ($coins) {
                $q->whereNull('max_coins')
                    ->orWhere('max_coins', '>=', $coins);
            });
    }

    public static function findTierForCoins(int $coins): ?self
    {
        return self::active()
            ->forCoins($coins)
            ->orderBy('min_coins', 'desc')
            ->first();
    }
}
