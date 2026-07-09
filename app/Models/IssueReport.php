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
    ];

    protected $casts = [
        'diagnostics' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
