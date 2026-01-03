# 🔧 Fix 419 Error (CSRF Token Mismatch)

## Problem
You're getting a 419 error when submitting the send-otp form. This is a CSRF token mismatch issue, likely caused by the service worker interfering.

## ✅ What I Fixed

1. **Service Worker Detection** - Improved localhost detection to always disable SW in dev
2. **Force Unregister** - Service worker now forces unregistration on localhost/dev
3. **CSRF Token Check** - Added validation before form submission

## 🔧 Steps to Fix (Do This Now)

### Step 1: Unregister Service Worker in Browser Console

Open browser console (F12) and run:

```javascript
// Force unregister all service workers
navigator.serviceWorker.getRegistrations().then(registrations => {
  registrations.forEach(registration => {
    registration.unregister().then(() => {
      console.log('✅ Service worker unregistered');
    });
  });
});

// Clear all caches
caches.keys().then(keys => {
  keys.forEach(key => {
    caches.delete(key);
    console.log('✅ Cache deleted:', key);
  });
});

// Reload page
location.reload(true);
```

### Step 2: Clear Browser Data

1. Press `F12` → Application tab
2. Click "Clear site data" button
3. Check all boxes
4. Click "Clear site data"

### Step 3: Hard Refresh

Press `Ctrl + Shift + R` (or `Cmd + Shift + R` on Mac)

### Step 4: Try Again

Submit the form again.

---

## If Still Getting 419 Error

### Check Session Configuration

Make sure your `.env` has:

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=
```

(Leave SESSION_DOMAIN empty for localhost)

Then:
```bash
php artisan config:clear
php artisan config:cache
```

### Check CSRF Token in Browser

1. Open DevTools → Console
2. Run: `document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')`
3. Should show a token (not null)

### Check Network Request

1. Open DevTools → Network tab
2. Submit form
3. Click on `send-otp` request
4. Check:
   - Request Headers should have `X-CSRF-TOKEN`
   - Form Data should have `_token` field
   - Both should have the same value

---

## Why This Happens

1. Service worker was registered from a previous visit
2. Service worker may cache old CSRF tokens
3. Service worker interferes with form submission
4. Session/Cookie issues prevent CSRF token validation

The fix ensures service worker is ALWAYS disabled in development/localhost.

