<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PHASE 2: Phase Mode Model
 * 
 * Server-wide phase configuration (BASIC, ESSENTIAL, COMFORT)
 */
class PhaseMode extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'limits',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'limits' => 'array',
    ];
}


