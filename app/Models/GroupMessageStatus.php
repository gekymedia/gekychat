<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupMessageStatus extends Model
{
    use SoftDeletes; // ✅ if you want per-user deletion on statuses

    public const STATUS_SENT      = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_READ      = 'read';

    protected $fillable = [
        'group_message_id',
        'user_id',
        'status',
        'deleted_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(GroupMessage::class, 'group_message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault([
            'name'       => 'Unknown User',
            'avatar_url' => asset('images/default-avatar.png'),
        ]);
    }

    /* === optional quality-of-life scopes / helpers === */

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopeRead($query)
    {
        return $query->where('status', self::STATUS_READ);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isRead(): bool
    {
        return $this->status === self::STATUS_READ;
    }
}
