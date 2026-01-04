<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BroadcastList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    /**
     * Owner of the broadcast list.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Recipients in this broadcast list.
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'broadcast_list_recipients', 'broadcast_list_id', 'recipient_id')
            ->withTimestamps();
    }
}

