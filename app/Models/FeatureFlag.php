<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * FeatureFlag Model
 * 
 * PHASE 0: Foundation model for feature flags.
 * Stores feature flag configuration for gradual rollouts and feature gating.
 */
class FeatureFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'enabled',
        'conditions',
        'platform',
        'description',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'conditions' => 'array',
    ];
}
