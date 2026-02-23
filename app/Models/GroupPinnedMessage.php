<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupPinnedMessage extends Model
{
    protected $table = 'group_pinned_messages';

    protected $fillable = [
        'group_id',
        'message_id',
        'pinned_by',
        'pinned_at',
        'pin_order',
    ];

    protected $casts = [
        'pinned_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(GroupMessage::class, 'message_id');
    }

    public function pinnedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pinned_by');
    }
}
