<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents an API client subscription. Users can subscribe to GekyChat's API
 * by providing a callback URL and selecting which features they need. Admins
 * can monitor these subscriptions from the admin panel.
 */
class ApiClient extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_id',
        'client_secret',
        'callback_url',
        'features',
        'status',
        'is_active',
        'scopes',
    ];

    protected $casts = [
        'features' => 'array',
        'scopes' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'client_secret',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get all messages created by this API client
     */
    public function messages()
    {
        return $this->hasMany(Message::class, 'platform_client_id');
    }
    
    /**
     * Get count of messages created by this API client
     */
    public function getMessagesCountAttribute(): int
    {
        return $this->messages()->count();
    }
    
    /**
     * Get count of conversations created by this API client
     * Note: Conversations don't have api_client_id yet, so we'll count conversations
     * where the first message was created by this client
     */
    public function getConversationsCountAttribute(): int
    {
        // Count conversations where the first message was created by this API client
        return \DB::table('conversations')
            ->join('messages', function($join) {
                $join->on('conversations.id', '=', 'messages.conversation_id')
                     ->whereRaw('messages.id = (
                         SELECT MIN(m.id) 
                         FROM messages m 
                         WHERE m.conversation_id = conversations.id
                     )');
            })
            ->where('messages.platform_client_id', $this->id)
            ->distinct()
            ->count('conversations.id');
    }
}