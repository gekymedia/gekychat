# Implementation Guide for New Database Features

**Date:** January 29, 2026  
**Status:** Models & Seeders Complete - Controllers & Jobs Pending

---

## ✅ **COMPLETED**

### 1. Models Updated ✅
- ✅ `User` model - Added SoftDeletes, new relationships, helper methods
- ✅ `Conversation` model - Added SoftDeletes
- ✅ `Group` model - Added SoftDeletes
- ✅ Created `UserPrivacySetting` model with privacy check methods
- ✅ Created `NotificationPreference` model with quiet hours logic
- ✅ Created `AuditLog` model with static log() method
- ✅ Created `UserBadge` model with assign/remove methods
- ✅ Created `MessageEditHistory`, `ScheduledMessage`, `GroupJoinRequest` models

### 2. Seeders Created ✅
- ✅ `UserPrivacySettingsSeeder` - Creates default privacy settings
- ✅ `NotificationPreferencesSeeder` - Creates default notification preferences  
- ✅ `UserBadgesSeeder` - Seeds 7 badge types (verified, premium, etc.)

**Run seeders:**
```bash
php artisan db:seed --class=UserPrivacySettingsSeeder
php artisan db:seed --class=NotificationPreferencesSeeder
php artisan db:seed --class=UserBadgesSeeder
```

---

## ⏭️ **NEXT: Controller Updates**

### Auth Controller - Login Tracking

Update `app/Http/Controllers/Api/V1/AuthController.php`:

```php
// In login method, after successful authentication:
public function login(Request $request)
{
    // ... existing validation and auth logic ...
    
    // After successful login, track it
    $user->recordLogin(
        $request->ip(),
        $request->userAgent(),
        $this->getCountryFromIp($request->ip()) // Implement this
    );
    
    // Log the login event
    AuditLog::log('login', $user, 'User logged in successfully');
    
    return response()->json([
        'user' => $user,
        'token' => $token,
        'is_account_locked' => $user->isLocked(),
    ]);
}

// In failed login:
public function login(Request $request)
{
    // ... after failed credentials check ...
    
    if ($user = User::where('email', $request->email)->first()) {
        $user->recordFailedLogin();
        
        if ($user->isLocked()) {
            return response()->json([
                'message' => 'Account locked due to multiple failed attempts. Try again in 30 minutes.',
                'locked_until' => $user->locked_until,
            ], 423); // 423 Locked
        }
    }
    
    return response()->json(['message' => 'Invalid credentials'], 401);
}
```

---

### Privacy Settings Controller

Create `app/Http/Controllers/Api/V1/PrivacySettingsController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserPrivacySetting;
use Illuminate\Http\Request;

class PrivacySettingsController extends Controller
{
    /**
     * Get user's privacy settings
     */
    public function index(Request $request)
    {
        $settings = $request->user()->privacySettings;
        
        if (!$settings) {
            // Create default settings if don't exist
            $settings = UserPrivacySetting::create([
                'user_id' => $request->user()->id,
            ]);
        }
        
        return response()->json(['data' => $settings]);
    }
    
    /**
     * Update privacy settings
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'who_can_message' => 'sometimes|in:everyone,contacts,nobody',
            'who_can_see_profile' => 'sometimes|in:everyone,contacts,nobody',
            'who_can_see_last_seen' => 'sometimes|in:everyone,contacts,nobody',
            'who_can_see_status' => 'sometimes|in:everyone,contacts,contacts_except,only_share_with',
            'who_can_add_to_groups' => 'sometimes|in:everyone,contacts,admins_only',
            'who_can_call' => 'sometimes|in:everyone,contacts,nobody',
            'profile_photo_visibility' => 'sometimes|in:everyone,contacts,nobody',
            'about_visibility' => 'sometimes|in:everyone,contacts,nobody',
            'send_read_receipts' => 'sometimes|boolean',
            'send_typing_indicator' => 'sometimes|boolean',
            'show_online_status' => 'sometimes|boolean',
        ]);
        
        $settings = $request->user()->privacySettings()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );
        
        // Log the change
        AuditLog::log('privacy_settings_updated', $settings, 'User updated privacy settings');
        
        return response()->json(['data' => $settings, 'message' => 'Privacy settings updated']);
    }
}
```

---

### Notification Preferences Controller

Create `app/Http/Controllers/Api/V1/NotificationPreferencesController.php`:

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;

class NotificationPreferencesController extends Controller
{
    /**
     * Get user's notification preferences
     */
    public function index(Request $request)
    {
        $preferences = $request->user()->notificationPreferences;
        
        if (!$preferences) {
            $preferences = NotificationPreference::create([
                'user_id' => $request->user()->id,
            ]);
        }
        
        return response()->json([
            'data' => $preferences,
            'is_quiet_hours' => $preferences->isQuietHours(),
        ]);
    }
    
    /**
     * Update notification preferences
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'push_messages' => 'sometimes|boolean',
            'push_group_messages' => 'sometimes|boolean',
            'push_calls' => 'sometimes|boolean',
            'push_status_updates' => 'sometimes|boolean',
            'push_reactions' => 'sometimes|boolean',
            'push_mentions' => 'sometimes|boolean',
            'email_messages' => 'sometimes|boolean',
            'email_weekly_digest' => 'sometimes|boolean',
            'email_security_alerts' => 'sometimes|boolean',
            'email_marketing' => 'sometimes|boolean',
            'show_message_preview' => 'sometimes|boolean',
            'notification_sound' => 'sometimes|boolean',
            'vibrate' => 'sometimes|boolean',
            'led_notification' => 'sometimes|boolean',
            'quiet_hours_start' => 'nullable|date_format:H:i',
            'quiet_hours_end' => 'nullable|date_format:H:i',
            'quiet_hours_enabled' => 'sometimes|boolean',
        ]);
        
        $preferences = $request->user()->notificationPreferences()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $validated
        );
        
        return response()->json(['data' => $preferences, 'message' => 'Preferences updated']);
    }
}
```

---

### Add Privacy Check Middleware

Update `app/Http/Controllers/Api/V1/MessageController.php`:

```php
// In store() method, before sending message:
public function store(Request $r, $conversationId)
{
    // ... existing validation ...
    
    // Check privacy settings
    $conversation = Conversation::findOrFail($conversationId);
    $recipient = $conversation->getOtherUser($r->user());
    
    if ($recipient && $recipient->privacySettings) {
        if (!$recipient->privacySettings->canMessage($r->user())) {
            return response()->json([
                'message' => 'This user has restricted who can message them',
            ], 403);
        }
    }
    
    // ... rest of message sending logic ...
}
```

---

### Badge Management Controller (Admin)

Create `app/Http/Controllers/Admin/BadgeController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBadge;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    /**
     * Assign badge to user
     */
    public function assign(Request $request, User $user)
    {
        $request->validate([
            'badge_id' => 'required|exists:user_badges,id',
            'notes' => 'nullable|string|max:500',
        ]);
        
        $badge = UserBadge::findOrFail($request->badge_id);
        $badge->assignTo($user, $request->user(), $request->notes);
        
        // Log the action
        AuditLog::log('badge_assigned', $user, "Badge '{$badge->display_name}' assigned to user");
        
        return response()->json([
            'message' => "Badge '{$badge->display_name}' assigned to {$user->name}",
        ]);
    }
    
    /**
     * Remove badge from user
     */
    public function remove(Request $request, User $user, UserBadge $badge)
    {
        $badge->removeFrom($user);
        
        AuditLog::log('badge_removed', $user, "Badge '{$badge->display_name}' removed from user");
        
        return response()->json([
            'message' => "Badge '{$badge->display_name}' removed from {$user->name}",
        ]);
    }
}
```

---

## ⏭️ **NEXT: API Routes**

Add to `routes/api.php`:

```php
Route::middleware(['auth:sanctum'])->group(function () {
    // Privacy Settings
    Route::get('privacy-settings', [PrivacySettingsController::class, 'index']);
    Route::put('privacy-settings', [PrivacySettingsController::class, 'update']);
    
    // Notification Preferences
    Route::get('notification-preferences', [NotificationPreferencesController::class, 'index']);
    Route::put('notification-preferences', [NotificationPreferencesController::class, 'update']);
    
    // Audit Logs (own logs only)
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    
    // Admin routes
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        // Badge Management
        Route::post('users/{user}/badges', [BadgeController::class, 'assign']);
        Route::delete('users/{user}/badges/{badge}', [BadgeController::class, 'remove']);
        
        // Audit Logs (all logs)
        Route::get('audit-logs', [AuditLogController::class, 'adminIndex']);
    });
});
```

---

## ⏭️ **NEXT: Scheduled Jobs**

### 1. Cleanup Typing Indicators

Create `app/Console/Commands/CleanupTypingIndicators.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\TypingIndicator;
use Illuminate\Console\Command;

class CleanupTypingIndicators extends Command
{
    protected $signature = 'cleanup:typing-indicators';
    protected $description = 'Remove expired typing indicators';

    public function handle()
    {
        $deleted = TypingIndicator::where('expires_at', '<', now())->delete();
        $this->info("Cleaned up {$deleted} expired typing indicators");
    }
}
```

Add to `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('cleanup:typing-indicators')->everyMinute();
}
```

---

### 2. Process Scheduled Messages

Create `app/Console/Commands/ProcessScheduledMessages.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\ScheduledMessage;
use App\Models\Message;
use Illuminate\Console\Command;

class ProcessScheduledMessages extends Command
{
    protected $signature = 'process:scheduled-messages';
    protected $description = 'Send scheduled messages that are due';

    public function handle()
    {
        $dueMessages = ScheduledMessage::where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->get();
        
        foreach ($dueMessages as $scheduled) {
            try {
                // Create actual message
                $message = Message::create([
                    'conversation_id' => $scheduled->conversation_id,
                    'sender_id' => $scheduled->user_id,
                    'body' => $scheduled->body,
                    'reply_to_id' => $scheduled->reply_to_id,
                ]);
                
                // Mark as sent
                $scheduled->update([
                    'status' => 'sent',
                    'sent_message_id' => $message->id,
                ]);
                
                $this->info("Sent scheduled message #{$scheduled->id}");
            } catch (\Exception $e) {
                $scheduled->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                
                $this->error("Failed to send scheduled message #{$scheduled->id}: " . $e->getMessage());
            }
        }
        
        $this->info("Processed {$dueMessages->count()} scheduled messages");
    }
}
```

Add to `app/Console/Kernel.php`:
```php
$schedule->command('process:scheduled-messages')->everyMinute();
```

---

### 3. Cleanup Old Audit Logs

Create `app/Console/Commands/CleanupOldAuditLogs.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class CleanupOldAuditLogs extends Command
{
    protected $signature = 'cleanup:audit-logs {--days=90}';
    protected $description = 'Delete audit logs older than specified days';

    public function handle()
    {
        $days = $this->option('days');
        $deleted = AuditLog::where('created_at', '<', now()->subDays($days))->delete();
        
        $this->info("Deleted {$deleted} audit logs older than {$days} days");
    }
}
```

Add to `app/Console/Kernel.php`:
```php
$schedule->command('cleanup:audit-logs --days=90')->weekly();
```

---

## ⏭️ **NEXT: Mobile App Updates**

### Flutter Changes Needed:

1. **Privacy Settings Screen**
   - API endpoint: `GET/PUT /api/v1/privacy-settings`
   - UI: Settings with toggles and dropdowns

2. **Notification Preferences Screen**
   - API endpoint: `GET/PUT /api/v1/notification-preferences`
   - UI: Settings with toggles, quiet hours picker

3. **Message Editing**
   - API endpoint: `PUT /api/v1/messages/{id}`
   - UI: Long-press menu with "Edit" option
   - Show edit history

4. **Message Scheduling**
   - API endpoint: `POST /api/v1/messages/schedule`
   - UI: Date/time picker when scheduling

5. **Badge Display**
   - Show badges next to usernames
   - Different colors per badge type

---

## Testing Checklist

### Backend:
- [ ] Run seeders to create default settings
- [ ] Test login tracking (check `last_login_at` updates)
- [ ] Test failed login attempts and account lockout
- [ ] Test privacy settings API endpoints
- [ ] Test notification preferences API endpoints
- [ ] Test audit log creation
- [ ] Test badge assignment/removal
- [ ] Test scheduled jobs

### Database:
- [ ] Verify soft deletes work (users, conversations, groups)
- [ ] Verify audit logs are being created
- [ ] Verify default settings created for new users

### Mobile:
- [ ] Privacy settings UI
- [ ] Notification preferences UI
- [ ] Message editing
- [ ] Badge display
- [ ] Scheduled messages

---

## Summary

### ✅ DONE:
1. Models updated with SoftDeletes and relationships
2. New model files created with business logic
3. Seeders created for default data
4. Implementation guide with code examples

### ⏭️ TODO:
1. Create controllers (code examples provided above)
2. Add API routes (code provided above)
3. Create scheduled commands (code provided above)
4. Run seeders on production
5. Update mobile app
6. Test all features

---

**Next Command to Run:**
```bash
# Seed default data
php artisan db:seed --class=UserPrivacySettingsSeeder
php artisan db:seed --class=NotificationPreferencesSeeder
php artisan db:seed --class=UserBadgesSeeder
```

**Status:** Ready for controller implementation and testing! 🚀
