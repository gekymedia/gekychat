# 🔧 Fix: "Page Expired" - Session Domain Issue

## Problem
Your session domain is set to `.gekychat.com`, but if you're accessing via `localhost` or `127.0.0.1`, the session cookie won't be set properly, causing CSRF token mismatch.

## Solution

### Option 1: Update .env for Local Development

Add/update in your `.env` file:

```env
# For local development (localhost/127.0.0.1)
SESSION_DOMAIN=null

# OR if you want to keep it empty
SESSION_DOMAIN=
```

**Important:** Leave it empty/null for local development!

### Option 2: Update .env for Production

For production (chat.gekychat.com):
```env
SESSION_DOMAIN=.gekychat.com
```

### After Updating

```bash
php artisan config:clear
php artisan config:cache
```

---

## Quick Test

1. Clear browser cookies for your localhost domain
2. Restart your Laravel server
3. Hard refresh the login page (`Ctrl + Shift + R`)
4. Try submitting the form again

---

## Why This Happens

- Session cookies are domain-specific
- `.gekychat.com` means cookies work for `chat.gekychat.com`, `www.gekychat.com`, etc.
- But NOT for `localhost` or `127.0.0.1`
- When the cookie can't be set, the session can't store the CSRF token
- Result: "Page expired" error

---

## Verify It's Fixed

After updating, check:
```bash
php artisan config:show session.domain
```

Should show `null` for local development.
