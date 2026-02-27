<?php

namespace App\Http\Resources\Sika;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SikaPackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price_ghs' => (float) $this->price_ghs,
            'coins' => $this->coins,
            'bonus_coins' => $this->bonus_coins,
            'total_coins' => $this->total_coins,
            'coins_per_ghs' => round($this->coins_per_ghs, 2),
            'icon' => $this->icon,
            'is_popular' => $this->sort_order === 1,
        ];
    }
}
