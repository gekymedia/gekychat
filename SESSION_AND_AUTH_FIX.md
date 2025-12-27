# Session Expiration & Auth Null Fix

## Issues Fixed

### 1. TypeError: null given to isParticipant()
**Problem:** When session expires, `Auth::id()` returns `null`, but `Conversation::isParticipant()` expected an `int`, causing a TypeError.

**Solution:**
- ✅ Updated `Conversation::isParticipant()` to accept nullable `?int $userId`
- ✅ Updated `ChatController::ensureMember()` to check authentication first
- ✅ Added null checks in other direct `isParticipant()` calls

### 2. Session Expiring Too Frequently
**Problem:** Session expires after 2 hours (120 minutes), requiring frequent SMS OTP re-authentication which is costly and annoying.

**Solution:**
- ✅ Extended session lifetime to 30 days (43,200 minutes)
- ✅ Session persists until manual logout or 30 days of inactivity

## Changes Made

### 1. `app/Models/Conversation.php`
```php
// Before:
public function isParticipant(int $userId): bool

// After:
public function isParticipant(?int $userId): bool
{
    if ($userId === null) {
        return false;
    }
    // ... rest of logic
}
```

### 2. `app/Http/Controllers/ChatController.php`
```php
// Before:
protected function ensureMember(Conversation $conversation): void
{
    abort_unless(
        $conversation->isParticipant(Auth::id()),
        403,
        'Not a participant of this conversation.'
    );
}

// After:
protected function ensureMember(Conversation $conversation): void
{
    // First check if user is authenticated
    $userId = Auth::id();
    if ($userId === null) {
        abort(401, 'You must be logged in to access this conversation.');
    }

    abort_unless(
        $conversation->isParticipant($userId),
        403,
        'Not a participant of this conversation.'
    );
}
```

### 3. `config/session.php`
```php
// Before:
'lifetime' => env('SESSION_LIFETIME', 120), // 2 hours

// After:
'lifetime' => env('SESSION_LIFETIME', 43200), // 30 days (43,200 minutes)
```

## Environment Variable

You can override the session lifetime in your `.env` file:

```env
# Session lifetime in minutes (default: 43200 = 30 days)
# Set to a very high number for "never expire until logout" (e.g., 525600 = 1 year)
SESSION_LIFETIME=43200

# Or for "never expire" until manual logout (1 year = 525,600 minutes)
# SESSION_LIFETIME=525600
```

## How It Works

1. **Authentication Check:** When accessing protected routes, the app first checks if user is authenticated
2. **Null Safety:** If session expired, user gets 401 (Unauthorized) instead of TypeError
3. **Extended Sessions:** Sessions now last 30 days, so users won't need to re-authenticate frequently
4. **Manual Logout:** Users can still logout manually, which will clear the session

## Testing

1. **Test expired session:**
   - Let session expire or manually clear session cookie
   - Visit a chat page
   - Should redirect to login with proper error message (not TypeError)

2. **Test long session:**
   - Login once
   - Close browser
   - Reopen browser after several hours/days
   - Should still be logged in (up to 30 days)

3. **Test logout:**
   - Click logout
   - Session should be cleared immediately
   - Should redirect to login

## Security Considerations

- Sessions still expire after 30 days of inactivity
- Manual logout still works
- Session cookies are HTTP-only and secure (if HTTPS)
- Consider setting `SESSION_LIFETIME` based on your security requirements

## Recommendations

For maximum "never expire until logout" behavior, set in `.env`:
```env
SESSION_LIFETIME=525600  # 1 year in minutes
```

Or for even longer (5 years):
```env
SESSION_LIFETIME=2628000  # 5 years in minutes
```

Note: Very long sessions may have security implications. Choose a balance between user convenience and security based on your application's needs.

