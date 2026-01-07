<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PHASE 2: Testing Mode Model
 * 
 * User-scoped testing mode with override limits
 */
class TestingMode extends Model
{
    protected $fillable = [
        'is_enabled',
        'user_ids',
        'max_lives',
        'max_test_rooms',
        'max_test_users',
        'features',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'user_ids' => 'array',
        'features' => 'array',
    ];
}


