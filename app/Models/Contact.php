<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $fillable = [
        'user_id',
        'contact_user_id',
        'display_name',
        'phone',
        'normalized_phone',
        'is_favorite',
        'avatar_path',
        'last_seen_at',
        'source', // 'manual', 'google_sync'
        'google_contact_id', // Reference to Google contact
        'is_deleted', // For soft deletion in GekyChat
    ];

    protected $casts = [
        'is_favorite'  => 'boolean',
        'last_seen_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
        'is_deleted'   => 'boolean',
    ];

    // In your Contact model
    // public static function normalizePhone($phone)
    // {
    //     // Remove all non-digit characters except leading +
    //     $normalized = preg_replace('/[^\d+]/', '', $phone);

    //     // If it starts with +, keep it, otherwise ensure proper format
    //     if (str_starts_with($normalized, '+')) {
    //         return $normalized;
    //     }

    //     // Handle local numbers - for Ghana numbers starting with 0, convert to +233
    //     if (str_starts_with($normalized, '0')) {
    //         return '+233' . substr($normalized, 1);
    //     }

    //     // If no country code, assume it's a local number and add default country code
    //     if (strlen($normalized) <= 11 && !str_starts_with($normalized, '+')) {
    //         return '+233' . ltrim($normalized, '0');
    //     }

    //     return $normalized;
    // }


    // Add these methods
    public function googleContact()
    {
        return $this->belongsTo(GoogleContact::class, 'google_contact_id');
    }

    public function markAsDeleted()
    {
        $this->update(['is_deleted' => true]);
    }

    public function restore()
    {
        $this->update(['is_deleted' => false]);
    }

    public function isFromGoogleSync()
    {
        return $this->source === 'google_sync';
    }
    /** Owner of this address-book entry (the person who uploaded it). */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** If the phone is a registered user, this links to them. */
    public function contactUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    /** Normalize: keep digits and an optional leading +. */
    public static function normalizePhone(?string $raw): string
    {
        if (!$raw) return '';
        $raw   = trim($raw);
        $plus  = str_starts_with($raw, '+') ? '+' : '';
        $digits = preg_replace('/\D+/', '', $raw);
        return $plus . $digits;
    }

    /** Helper for fuzzy matching: last 9 digits of a normalized number. */
    public static function last9(string $normalized): string
    {
        $digits = preg_replace('/\D+/', '', $normalized);
        return strlen($digits) >= 9 ? substr($digits, -9) : $digits;
    }
    // In App\Models\Contact
    /** Alias for contactUser for backward compatibility */
    public function user(): BelongsTo
    {
        return $this->contactUser();
    }

    /** Get the initial for avatar placeholder */
    public function getInitialAttribute(): string
    {
        if ($this->contactUser) {
            return $this->contactUser->initial ?? 'U';
        }
        $name = $this->display_name ?: $this->phone;
        return strtoupper(substr($name ?: 'U', 0, 1));
    }
}
