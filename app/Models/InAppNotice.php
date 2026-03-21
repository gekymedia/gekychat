<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InAppNotice extends Model
{
    protected $fillable = [
        'notice_key',
        'title',
        'body',
        'style',
        'action_label',
        'action_url',
        'starts_at',
        'ends_at',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'sort_order' => 'integer',
    ];
}
