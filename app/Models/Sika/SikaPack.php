<?php

namespace App\Models\Sika;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SikaPack extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price_ghs',
        'coins',
        'bonus_coins',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_ghs' => 'decimal:2',
        'coins' => 'integer',
        'bonus_coins' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getTotalCoinsAttribute(): int
    {
        return $this->coins + $this->bonus_coins;
    }

    public function getCoinsPerGhsAttribute(): float
    {
        if ($this->price_ghs <= 0) {
            return 0;
        }
        return $this->total_coins / (float) $this->price_ghs;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('price_ghs');
    }
}
