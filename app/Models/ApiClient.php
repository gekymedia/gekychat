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
        'callback_url',
        'features',
        'status',
    ];

    protected $casts = [
        'features' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}