# 🔧 Fix "Page Expired" Error on OTP Form

## Quick Fixes

### Fix 1: Clear Browser Cache & Refresh Page
1. Press `Ctrl + Shift + Delete` (Windows) or `Cmd + Shift + Delete` (Mac)
2. Clear cache and cookies
3. Refresh the login page
4. Try submitting the form again

### Fix 2: Ensure Sessions Table Exists
The session driver is set to `database`. Make sure the sessions table exists:

```bash
php artisan migrate
```

### Fix 3: Clear Laravel Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan session:flush
php artisan config:cache
```

### Fix 4: Check .env Session Configuration
Make sure your `.env` has:
```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

### Fix 5: Restart Your Development Server
If using `php artisan serve`, restart it:
```bash
# Stop the server (Ctrl+C)
php artisan serve
```

### Fix 6: Check Browser Console
1. Open browser DevTools (F12)
2. Go to Console tab
3. Submit the form
4. Look for any JavaScript errors

### Fix 7: Verify CSRF Token is Present
1. Right-click on the form → Inspect
2. Look for `<input type="hidden" name="_token" value="...">`
3. If missing, the `@csrf` directive isn't working

---

## Common Causes

1. **Session expired** - Page was open too long (default: 120 minutes)
2. **CSRF token mismatch** - Token regenerated between page load and submit
3. **Database sessions table missing** - Sessions can't be stored
4. **Browser cache** - Old CSRF token cached
5. **JavaScript interference** - Form submission being intercepted incorrectly

---

## If Still Not Working

Check the Laravel logs:
```bash
tail -f storage/logs/laravel.log
```

Then try submitting the form and see what error appears in the logs.
