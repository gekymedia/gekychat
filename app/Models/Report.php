<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a user report. Users can report other users for spam, abuse, or other
 * reasons. Admins can review reports and optionally ban users for a period of
 * time. A report can also be used to permanently block the reported user for
 * the reporter.
 */
class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'reported_user_id',
        'reason',
        'details',
        'status',
        'banned_until',
    ];

    protected $casts = [
        'banned_until' => 'datetime',
    ];

    /**
     * The user who filed the report.
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * The user being reported.
     */
    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }
}