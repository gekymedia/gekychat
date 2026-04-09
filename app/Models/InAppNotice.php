<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InAppNotice extends Model
{
    protected $fillable = [
        'notice_key',
        'is_system_notice',
        'title',
        'body',
        'style',
        'action_label',
        'action_url',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
        'condition_type',
        'condition_value',
    ];

    protected $casts = [
        'is_system_notice' => 'boolean',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sort_order' => 'integer',
        'condition_value' => 'array',
    ];
}
