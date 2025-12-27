# 🔍 Debug CSRF "Page Expired" Issue

## Quick Fix: Unregister Service Worker Manually

Open browser console (F12) and run:

```javascript
// Unregister all service workers
navigator.serviceWorker.getRegistrations().then(registrations => {
  registrations.forEach(registration => {
    registration.unregister();
    console.log('Service worker unregistered');
  });
});

// Clear all caches
caches.keys().then(keys => {
  keys.forEach(key => {
    caches.delete(key);
    console.log('Cache deleted:', key);
  });
});

// Then hard refresh
location.reload(true);
```

---

## Check CSRF Token in Browser

1. Open DevTools (F12)
2. Go to Console tab
3. Run: `document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')`
4. Should show a long token string
5. If it shows `null`, CSRF token is missing from page

---

## Check Form Has CSRF Token

1. Right-click on the form → Inspect Element
2. Look for: `<input type="hidden" name="_token" value="...">`
3. If missing, the `@csrf` directive failed

---

## Verify Service Worker Status

1. Open DevTools (F12)
2. Go to Application tab → Service Workers
3. Check if any service workers are registered
4. If yes, click "Unregister"
5. Refresh page

---

## Check Network Tab

1. Open DevTools (F12)
2. Go to Network tab
3. Submit the form
4. Look for the `send-otp` request
5. Check:
   - Status code (should be 200 or 302, not 419)
   - Request Headers (should include `X-CSRF-TOKEN`)
   - Form Data (should include `_token`)

---

## Test Direct URL

Try accessing the route directly to see actual error:

```bash
# Check what URL the form is submitting to
# Should be: http://127.0.0.1:8000/send-otp (or your domain)
```

---

## Temporary Workaround

If nothing works, temporarily disable CSRF for this route (NOT RECOMMENDED FOR PRODUCTION):

In `app/Http/Middleware/ValidateCsrfToken.php`:

```php
protected $except = [
    'send-otp', // TEMPORARY - REMOVE AFTER FIXING
];
```

Then clear config cache:
```bash
php artisan config:clear
```

**Remember to remove this exception after fixing the issue!**

