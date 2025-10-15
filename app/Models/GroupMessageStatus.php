<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMessageStatus extends Model
{
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
    ];

    public function message()
    {
        return $this->belongsTo(GroupMessage::class, 'group_message_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
