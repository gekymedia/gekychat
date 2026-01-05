<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusComment extends Model
{
    protected $fillable = [
        'status_id',
        'user_id',
        'comment',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The status this comment belongs to
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * The user who made this comment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
