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
        'source',
        'is_favorite',
        'avatar_path',
        'last_seen_at',
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'last_seen_at' => 'datetime',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

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
}
