# 🔑 Pusher Credentials Configuration

## Your Pusher Credentials

```
App ID: 2093574
Key: 6481ff66c09182cc96cb
Secret: b1c96463b07964b8fccb
Cluster: mt1
```

---

## Step 1: Update Laravel `.env` File

Add these lines to your `gekychat/.env` file:

```env
# Broadcasting Configuration
BROADCAST_CONNECTION=pusher
BROADCAST_DRIVER=pusher

# Pusher Credentials
PUSHER_APP_ID=2093574
PUSHER_APP_KEY=6481ff66c09182cc96cb
PUSHER_APP_SECRET=b1c96463b07964b8fccb
PUSHER_APP_CLUSTER=mt1
PUSHER_PORT=443
PUSHER_SCHEME=https

# Frontend Pusher Configuration (for Vite/build)
VITE_PUSHER_APP_KEY=6481ff66c09182cc96cb
VITE_PUSHER_APP_CLUSTER=mt1
```

---

## Step 2: Update Flutter `.env` File

Add these lines to your `gekychat_mobile/.env` file:

```env
# Pusher Configuration
PUSHER_KEY=6481ff66c09182cc96cb
PUSHER_CLUSTER=mt1
PUSHER_HOST=api-mt1.pusher.com
PUSHER_FORCE_TLS=true
PUSHER_WS_PORT=80
PUSHER_WSS_PORT=443
PUSHER_AUTH_ENDPOINT=https://chat.gekychat.com/api/v1/broadcasting/auth
```

**Note:** Update `PUSHER_AUTH_ENDPOINT` with your actual API domain if different from `chat.gekychat.com`

---

## Step 3: Clear Laravel Cache

After updating the `.env` file, run these commands in your `gekychat` directory:

```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

---

## Step 4: Verify Configuration

Test that Pusher is configured correctly:

```bash
php artisan tinker
```

Then run:
```php
config('broadcasting.default'); // Should return 'pusher'
config('broadcasting.connections.pusher.key'); // Should return '6481ff66c09182cc96cb'
config('broadcasting.connections.pusher.cluster'); // Should return 'mt1'
exit
```

---

## Step 5: Rebuild Frontend Assets (if needed)

If you've updated `VITE_PUSHER_APP_KEY`, rebuild your frontend:

```bash
npm run build
# OR for development
npm run dev
```

---

## ✅ Verification Checklist

- [ ] Added Pusher credentials to Laravel `.env`
- [ ] Added `VITE_PUSHER_APP_KEY` for frontend
- [ ] Added Pusher config to Flutter `.env`
- [ ] Cleared Laravel config cache
- [ ] Verified config in tinker
- [ ] Rebuilt frontend assets (if using Vite)
- [ ] Tested real-time connection in browser console
- [ ] Tested Flutter app connection

---

## 🔒 Security Note

**Important:** Never commit your `.env` file to Git! The `.env` file should already be in `.gitignore`.

These credentials (especially the Secret) should be kept private.

---

## 🧪 Testing Connection

### Browser Console Test:
1. Open your app in browser
2. Open Developer Console (F12)
3. Look for: `✅ Laravel Echo (Pusher) initialized successfully`
4. Look for: `🔗 Pusher connected`

### Flutter App Test:
1. Run your Flutter app
2. Check logs for: `✅ Connected to Pusher WebSocket`

---

## 📚 Next Steps

After Pusher is configured:
1. Test real-time messaging
2. Test typing indicators
3. Test online/offline status
4. Monitor Pusher dashboard for connections
