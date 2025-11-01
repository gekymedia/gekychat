<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Status extends Model
{
    protected $fillable = [
        'user_id', 'type', 'content', 'media_path', 
        'background_color', 'text_color', 'font_size', 'duration'
    ];

    protected $casts = [
        'font_size' => 'integer',
        'duration' => 'integer'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(StatusView::class);
    }

    public function getMediaUrlAttribute()
    {
        return $this->media_path ? Storage::disk('public')->url($this->media_path) : null;
    }

    public function isExpired()
    {
        return $this->created_at->addSeconds($this->duration)->isPast();
    }
}
