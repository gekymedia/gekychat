<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAnalyticsDailyRollup extends Model
{
    protected $fillable = [
        'rollup_date',
        'metric_key',
        'dimension',
        'value',
        'metadata',
    ];

    protected $casts = [
        'rollup_date' => 'date',
        'value' => 'float',
        'metadata' => 'array',
    ];
}
