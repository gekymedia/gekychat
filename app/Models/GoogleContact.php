<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'google_contact_id',
        'name',
        'phone',
        'email',
        'photo_url',
        'is_deleted_in_google',
        'last_synced_at',
        'sync_status'
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'is_deleted_in_google' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function localContact()
    {
        return $this->hasOne(Contact::class, 'phone', 'phone')
                    ->where('user_id', $this->user_id);
    }

    public function markAsDeletedInGoogle()
    {
        $this->update(['is_deleted_in_google' => true]);
    }

    public function markAsActiveInGoogle()
    {
        $this->update(['is_deleted_in_google' => false]);
    }
}