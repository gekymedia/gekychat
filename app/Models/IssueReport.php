<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueReport extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'description',
        'source',
        'app_version',
        'platform',
        'device_model',
        'os_version',
        'screen_name',
        'diagnostics',
        'screenshot_path',
        'status',
        'admin_notes',
        'admin_reply',
        'admin_reply_at',
        'replied_by_user_id',
    ];

    protected $casts = [
        'diagnostics' => 'array',
        'admin_reply_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by_user_id');
    }
}
