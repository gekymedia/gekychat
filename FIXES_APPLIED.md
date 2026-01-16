# Server Error Fixes Applied

## Date: 2026-01-16

### Issue 1: BotService - Call to a member function lt() on string

**Error**: `Call to a member function lt() on string at BotService.php:910`

**Root Cause**: The `ai_last_used_at` field in the User model was not being cast to a Carbon instance, so when accessed it was returned as a string instead of a Carbon object.

**Fix Applied**:
1. Added `'ai_last_used_at' => 'datetime'` to the `$casts` array in `app/Models/User.php`
2. Added safety checks in `BotService::checkRateLimit()` and `BotService::trackUsage()` methods to handle cases where the field might still be a string (defensive programming)

**Files Modified**:
- `app/Models/User.php` - Added cast for `ai_last_used_at`
- `app/Services/BotService.php` - Added safety checks in `checkRateLimit()` and `trackUsage()` methods

### Issue 2: Settings Route - POST method not supported

**Error**: `The POST method is not supported for route settings. Supported methods: GET, HEAD, PUT.`

**Root Cause**: This appears to be a route cache issue. The route is correctly defined as `Route::match(['PUT', 'POST'], '/', ...)` in `routes/web.php`, but Laravel's route cache may be stale.

**Fix Required** (Server-side):
Run the following command on the server to clear the route cache:
```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

**Note**: The route definition in `routes/web.php` is correct and should accept both PUT and POST methods. The issue is likely due to cached routes.

**Files to Check**:
- `routes/web.php` - Line 170: `Route::match(['PUT', 'POST'], '/', [SettingsController::class, 'update'])->name('update');`
