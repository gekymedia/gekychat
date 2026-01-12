<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Conversation extends Model
{
    protected $fillable = [
        'is_group',
        'name',
        'avatar_path',
        'description',
        'is_private',
        'created_by',
        'invite_code',
        'slug', // Add slug for pretty URLs
        'call_id', // Unique call ID for this conversation
        'created_at',
        'verified',
        'metadata',
    ];

    protected $casts = [
        'is_group'   => 'boolean',
        'is_private' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'verified'   => 'boolean',
        'metadata'   => 'array',
    ];

    // Keep these so the web views/mobile summaries can use them easily
    protected $appends = [
        'unread_count',
        'other_user',
        'title',
        'avatar_url',
        'is_saved_messages', // New attribute to identify saved messages
    ];

    // Eager-load the latest message (cheap and commonly shown)
    protected $with = ['latestMessage'];

    /* -------------------------
     | Model Events
     * ------------------------*/

    protected static function booted()
    {
        static::creating(function (Conversation $conversation) {
            // Generate unique slug if not provided
            if (empty($conversation->slug)) {
                $conversation->slug = $conversation->generateSlug();
            }
            
            // Generate invite code for private groups if needed
            if ($conversation->is_group && $conversation->is_private && empty($conversation->invite_code)) {
                $conversation->invite_code = Str::random(10);
            }
            
            // Generate unique call_id if not provided
            if (empty($conversation->call_id)) {
                $conversation->call_id = $conversation->generateCallId();
            }
        });

        static::updating(function (Conversation $conversation) {
            // Regenerate slug if name changed
            if ($conversation->isDirty('name') && $conversation->is_group) {
                $conversation->slug = $conversation->generateSlug();
            }
        });
    }

    /* -------------------------
     | Relationships
     * ------------------------*/

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot(['role', 'last_read_message_id', 'muted_until', 'pinned_at', 'archived_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id', 'asc');
    }

    // Back-compat: many blades expect "latestMessage"
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // Optional alias (some code prefers lastMessage)
    public function lastMessage(): HasOne
    {
        return $this->latestMessage();
    }

    // Relationship to creator
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Relationships for direct messages (user_one_id and user_two_id)
    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * Labels assigned to this conversation.
     */
    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'conversation_label');
    }

    /* -------------------------
     | Scopes
     * ------------------------*/

    public function scopeDirect(Builder $q): Builder
    {
        return $q->where('is_group', false);
    }

    public function scopeGroup(Builder $q): Builder
    {
        return $q->where('is_group', true);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->whereHas('members', fn($m) => $m->where('users.id', $userId));
    }

    public function scopeSavedMessages(Builder $q, int $userId): Builder
    {
        return $q->direct()
            ->whereHas('members', fn($m) => $m->where('users.id', $userId))
            ->whereHas('members', fn($m) => $m->where('users.id', $userId), '=', 1); // Only one member (self)
    }

    /**
     * Filter direct chats that contain both users $a and $b.
     * Handles saved messages (when $a === $b) and regular DMs.
     */
    public function scopeBetweenUsers(Builder $q, int $a, int $b): Builder
    {
        if ($a === $b) {
            // Saved messages - conversation with only one member (self)
            return $q->direct()
                ->whereHas('members', fn($m) => $m->where('users.id', $a))
                ->whereHas('members', fn($m) => $m, '=', 1);
        }

        // Regular DM - conversation with exactly these two members
        return $q->direct()
            ->whereHas('members', fn($m) => $m->where('users.id', $a))
            ->whereHas('members', fn($m) => $m->where('users.id', $b))
            ->whereHas('members', fn($m) => $m, '=', 2);
    }

    /* -------------------------
     | Helpers
     * ------------------------*/

    /**
     * Generate unique slug for conversations
     */
    public function generateSlug(): string
    {
        if ($this->is_group) {
            // Group conversation - use name-based slug
            $baseSlug = Str::slug($this->name ?: 'group');
            $randomSuffix = Str::lower(Str::random(5));
            $slug = "{$baseSlug}-{$randomSuffix}";
            
            $counter = 1;
            while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
                $randomSuffix = Str::lower(Str::random(5));
                $slug = "{$baseSlug}-{$randomSuffix}";
                $counter++;
            }
        } else {
            // Direct message - use participant-based slug
            if ($this->is_saved_messages) {
                $slug = "saved-messages-" . Str::lower(Str::random(8));
            } else {
                $participants = $this->members->pluck('name', 'id')->toArray();
                ksort($participants); // Ensure consistent order
                $names = array_values($participants);
                $baseSlug = Str::slug(implode('-', $names));
                $slug = $baseSlug ?: 'chat-' . Str::lower(Str::random(8));
            }
            
            // Ensure uniqueness
            $counter = 1;
            $originalSlug = $slug;
            while (static::where('slug', $slug)->where('id', '!=', $this->id)->exists()) {
                $slug = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        return $slug;
    }

    /**
     * Get the route key for the model (for pretty URLs)
     */
    public function getRouteKeyName()
    {
        return 'slug';
    }

    /**
     * Create (if needed) a deterministic 1:1 conversation between two user IDs.
     * Now supports saved messages when $a === $b.
     * Uses database locking to prevent race conditions and duplicate creation.
     */
    public static function findOrCreateDirect(int $a, int $b, ?int $createdBy = null): self
    {
        $isSavedMessages = $a === $b;
        
        if ($isSavedMessages) {
            // Handle saved messages - conversation with only one member (self)
            // Use transaction with locking to prevent race conditions
            return DB::transaction(function () use ($a, $createdBy) {
                $existing = static::query()
                    ->savedMessages($a)
                    ->lockForUpdate()
                    ->first();
                
                if ($existing) {
                    return $existing;
                }

                // Double-check after acquiring lock (another process might have created it)
                $existing = static::query()->savedMessages($a)->first();
                if ($existing) {
                    return $existing;
                }

                $conv = static::create([
                    'is_group'   => false,
                    'name'       => 'Saved Messages',
                    'created_by' => $createdBy ?? $a,
                    'slug'       => 'saved-messages-' . Str::random(8), // Will be regenerated if needed
                ]);

                // Add only the user themselves
                $conv->members()->syncWithPivotValues([$a], ['role' => 'member']);

                return $conv->fresh(['members', 'latestMessage']);
            });
        }

        // Regular DM between two different users
        // Normalize user IDs (always use min/max to ensure consistency)
        $minUserId = min($a, $b);
        $maxUserId = max($a, $b);
        
        // Use database transaction with locking to prevent race conditions
        return DB::transaction(function () use ($minUserId, $maxUserId, $a, $b, $createdBy) {
            // Lock and check for existing conversation
            // Check by looking for conversations with exactly these two users
            $existing = static::query()
                ->where('is_group', false)
                ->whereHas('members', function ($q) use ($minUserId) {
                    $q->where('users.id', $minUserId);
                })
                ->whereHas('members', function ($q) use ($maxUserId) {
                    $q->where('users.id', $maxUserId);
                })
                ->whereDoesntHave('members', function ($q) use ($minUserId, $maxUserId) {
                    $q->whereNotIn('users.id', [$minUserId, $maxUserId]);
                })
                ->lockForUpdate()
                ->first();
            
            if ($existing) {
                // Double-check it has exactly 2 members
                $memberCount = $existing->members()->count();
                if ($memberCount === 2) {
                    return $existing;
                }
            }

            // Double-check without lock (another process might have created it)
            $existing = static::query()
                ->where('is_group', false)
                ->whereHas('members', function ($q) use ($minUserId) {
                    $q->where('users.id', $minUserId);
                })
                ->whereHas('members', function ($q) use ($maxUserId) {
                    $q->where('users.id', $maxUserId);
                })
                ->whereDoesntHave('members', function ($q) use ($minUserId, $maxUserId) {
                    $q->whereNotIn('users.id', [$minUserId, $maxUserId]);
                })
                ->first();
            
            if ($existing) {
                $memberCount = $existing->members()->count();
                if ($memberCount === 2) {
                    return $existing;
                }
            }

            // Create new conversation
            $conv = static::create([
                'is_group'   => false,
                'name'       => null,
                'created_by' => $createdBy ?? $a,
            ]);

            $conv->members()->syncWithPivotValues([$a, $b], ['role' => 'member']);

            return $conv->fresh(['members', 'latestMessage']);
        });
    }

    /**
     * Get or create saved messages conversation for a user
     */
    public static function findOrCreateSavedMessages(int $userId): self
    {
        return static::findOrCreateDirect($userId, $userId, $userId);
    }

    /**
     * Check if a given user participates in this conversation.
     */
    public function isParticipant(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        // For direct messages (non-group), check user_one_id and user_two_id
        if (!$this->is_group && ($this->user_one_id || $this->user_two_id)) {
            return ($this->user_one_id == $userId) || ($this->user_two_id == $userId);
        }

        if ($this->relationLoaded('members')) {
            return $this->members->contains('id', $userId);
        }
        
        // Direct pivot table query (most reliable)
        try {
            return DB::table('conversation_user')
                ->where('conversation_id', $this->id)
                ->where('user_id', $userId)
                ->exists();
        } catch (\Exception $e) {
            // Fallback: Use relationship query
            try {
                return $this->members()->where('users.id', $userId)->exists();
            } catch (\Exception $e2) {
                Log::warning("isParticipant check failed for conversation {$this->id} and user {$userId}: " . $e2->getMessage());
                return false;
            }
        }
    }

    /**
     * Mark messages as read for a user. If $messageId is null, mark up to latest.
     */
    public function markRead(int $userId, ?int $messageId = null): void
    {
        if (!$this->isParticipant($userId)) return;

        $lastId = $messageId ?? (int) ($this->messages()->max('id') ?: 0);
        $this->members()->updateExistingPivot($userId, ['last_read_message_id' => $lastId]);
    }

    /**
     * Unread count for a specific user, using the pivot's last_read_message_id.
     * Excludes messages sent by that user.
     */
    public function unreadCountFor(int $userId): int
    {
        $pivot = $this->members()->where('users.id', $userId)->first()?->pivot;
        $lastReadId = (int) ($pivot?->last_read_message_id ?? 0);

        return $this->messages()
            ->where('id', '>', $lastReadId)
            ->where('sender_id', '!=', $userId)
            ->count();
    }

    /**
     * For a direct chat, return the other participant relative to $userId (or current auth user).
     * Returns null for saved messages or if user is not in conversation.
     */
    public function otherParticipant(?int $userId = null): ?User
    {
        if ($this->is_group) return null;
        if ($this->is_saved_messages) return null;

        $uid = $userId ?? Auth::id();
        if (!$uid) return null;

        // Ensure members are available
        $members = $this->relationLoaded('members') ? $this->members : $this->members()->get();
        return $members->firstWhere('id', '!=', $uid);
    }

    /* -------------------------
     | Accessors (appends)
     * ------------------------*/

    public function getUnreadCountAttribute(): int
    {
        $userId = Auth::id();
        if (!$userId) return 0;
        return $this->unreadCountFor($userId);
    }

    /**
     * Check if this conversation is saved messages (user chatting with themselves)
     */
    public function getIsSavedMessagesAttribute(): bool
    {
        if ($this->is_group) return false;
        
        $members = $this->relationLoaded('members') ? $this->members : $this->members()->get();
        return $members->count() === 1 && $members->first()->id === Auth::id();
    }

    /**
     * Back-compat for older views expecting $conversation->other_user (1:1 only).
     * Returns null for saved messages.
     */
    public function getOtherUserAttribute(): ?User
    {
        if ($this->is_saved_messages) {
            return null; // No "other user" for saved messages
        }
        return $this->otherParticipant();
    }

    /**
     * A human title for listing: group name, saved messages, or the other user's name/phone.
     */
    public function getTitleAttribute(): string
    {
        if ($this->is_group) {
            return (string) ($this->name ?? 'Group');
        }

        if ($this->is_saved_messages) {
            return 'Saved Messages';
        }

        $other = $this->otherParticipant();
        if (!$other) {
            return 'Unknown';
        }

        // Check if there's a contact with a display name for this user
        $currentUserId = Auth::id();
        if ($currentUserId) {
            $contact = \App\Models\Contact::where('user_id', $currentUserId)
                ->where('contact_user_id', $other->id)
                ->first();
            
            if ($contact && $contact->display_name) {
                return $contact->display_name;
            }
        }

        return $other->name ?: ($other->phone ?: 'Unknown');
    }

    /**
     * A display avatar URL: group avatar, saved messages icon, or the other user's avatar.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->is_group) {
            return $this->avatar_path ? \App\Helpers\UrlHelper::secureStorageUrl($this->avatar_path) : null;
        }

        if ($this->is_saved_messages) {
            // Return a special icon for saved messages, or user's own avatar
            $user = Auth::user();
            return $user?->avatar_path ? \App\Helpers\UrlHelper::secureStorageUrl($user->avatar_path) : null;
        }

        $other = $this->otherParticipant();
        if ($other?->avatar_path) {
            return \App\Helpers\UrlHelper::secureStorageUrl($other->avatar_path);
        }
        return null;
    }

    /**
     * Get the public URL for this conversation
     */
    public function getUrlAttribute(): string
    {
        return route('chat.show', $this->slug);
    }

    /**
     * Generate a unique call ID for this conversation
     */
    public function generateCallId(): string
    {
        do {
            $callId = Str::random(16);
        } while (static::where('call_id', $callId)->exists());

        return $callId;
    }

    /**
     * Get or generate the call ID for this conversation
     */
    public function getOrGenerateCallId(): string
    {
        if (empty($this->call_id)) {
            $this->call_id = $this->generateCallId();
            $this->save();
        }

        return $this->call_id;
    }

    /**
     * Get the call link URL for this conversation
     */
    public function getCallLinkAttribute(): string
    {
        $callId = $this->getOrGenerateCallId();
        return route('calls.join', $callId);
    }
    
}