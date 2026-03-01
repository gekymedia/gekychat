<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * Get the user that performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the model that was audited
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create audit log entry
     * 
     * @param string $action The action being logged (e.g., 'login', 'update', 'delete')
     * @param Model|null $auditable The model being audited (optional)
     * @param string|null $description Human-readable description of the action
     * @param array|null $oldValues Previous values (for updates)
     * @param array|null $newValues New values (for updates)
     * @param int|null $userId Explicit user ID (use when auth()->id() is not available, e.g., during login)
     */
    public static function log(
        string $action,
        ?Model $auditable = null,
        ?string $description = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null
    ): self {
        // Use explicit userId if provided, otherwise fall back to auth()->id()
        // This is important for login events where auth()->id() returns null
        $effectiveUserId = $userId ?? auth()->id();
        
        // For login/register actions on User model, use the auditable's ID if user_id is still null
        if ($effectiveUserId === null && $auditable instanceof User && in_array($action, ['login', 'register', 'logout'])) {
            $effectiveUserId = $auditable->id;
        }
        
        return static::create([
            'user_id' => $effectiveUserId,
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->id,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ]);
    }

    /**
     * Get changes as human-readable text
     */
    public function getChangesText(): string
    {
        if (!$this->old_values || !$this->new_values) {
            return '';
        }

        $changes = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? 'N/A';
            if ($oldValue != $newValue) {
                $changes[] = "{$key}: {$oldValue} → {$newValue}";
            }
        }

        return implode(', ', $changes);
    }
}
