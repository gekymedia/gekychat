<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReaction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'message_id',
        'user_id',
        'reaction'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'user_name',
        'user_avatar'
    ];

    /**
     * Get the message that owns the reaction.
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the user that created the reaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Deleted User',
            'avatar_url' => asset('images/default-avatar.png')
        ]);
    }

    /**
     * Get the user's name for display.
     */
    public function getUserNameAttribute(): string
    {
        return $this->user->name;
    }

    /**
     * Get the user's avatar URL for display.
     */
    public function getUserAvatarAttribute(): string
    {
        return $this->user->avatar_url;
    }

    /**
     * Scope a query to only include reactions for a specific message.
     */
    public function scopeForMessage($query, $messageId)
    {
        return $query->where('message_id', $messageId);
    }

    /**
     * Scope a query to only include reactions from a specific user.
     */
    public function scopeFromUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the reaction data for broadcasting.
     */
    public function toBroadcast(): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->message_id,
            'user_id' => $this->user_id,
            'reaction' => $this->reaction,
            'user_name' => $this->user_name,
            'user_avatar' => $this->user_avatar,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}