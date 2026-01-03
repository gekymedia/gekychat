# 🔧 Pusher Setup Instructions

## Step 1: Get Pusher Credentials

1. Go to [Pusher Dashboard](https://dashboard.pusher.com)
2. Sign up or log in
3. Create a new app (or use existing)
4. Go to **App Keys** tab
5. Copy these credentials:
   - **App ID**
   - **Key**
   - **Secret**
   - **Cluster** (e.g., `mt1`, `eu`, `ap-southeast-1`)

---

## Step 2: Configure Laravel `.env` File

Add these to your `gekychat/.env` file:

```env
# Broadcasting Configuration
BROADCAST_CONNECTION=pusher
BROADCAST_DRIVER=pusher

# Pusher Credentials
PUSHER_APP_ID=your-app-id-here
PUSHER_APP_KEY=your-app-key-here
PUSHER_APP_SECRET=your-app-secret-here
PUSHER_APP_CLUSTER=mt1
PUSHER_PORT=443
PUSHER_SCHEME=https
```

**Important:** Replace `your-app-id-here`, `your-app-key-here`, and `your-app-secret-here` with your actual Pusher credentials.

---

## Step 3: Configure Frontend (Vite)

Add these to your `gekychat/.env` file for the frontend:

```env
# Frontend Pusher Configuration
VITE_PUSHER_APP_KEY=your-app-key-here
VITE_PUSHER_APP_CLUSTER=mt1
```

**Note:** Only the `APP_KEY` and `CLUSTER` are needed in the frontend (they're public). Never expose `APP_SECRET` in frontend code.

---

## Step 4: Configure Flutter App `.env` File

Add these to your `gekychat_mobile/.env` file:

```env
# Pusher Configuration
PUSHER_KEY=your-app-key-here
PUSHER_CLUSTER=mt1
PUSHER_HOST=api-mt1.pusher.com
PUSHER_FORCE_TLS=true
PUSHER_WS_PORT=80
PUSHER_WSS_PORT=443
PUSHER_AUTH_ENDPOINT=https://your-api-domain.com/api/v1/broadcasting/auth
```

Replace:
- `your-app-key-here` with your Pusher App Key
- `mt1` with your cluster name
- `your-api-domain.com` with your actual API domain

---

## Step 5: Clear Laravel Cache

Run these commands in your `gekychat` directory:

```bash
php artisan config:clear
php artisan cache:clear
php artisan config:cache
```

---

## Step 6: Install/Update Dependencies

### Laravel:
```bash
composer install
```

### Flutter:
```bash
cd gekychat_mobile
flutter pub get
```

---

## Step 7: Test the Setup

### Test Laravel Broadcasting:

1. Check if Pusher is configured correctly:
```bash
php artisan tinker
```

Then run:
```php
config('broadcasting.default'); // Should return 'pusher'
config('broadcasting.connections.pusher.key'); // Should return your Pusher key
exit
```

### Test Frontend Connection:

1. Open your browser console
2. Look for: `✅ Laravel Echo (Pusher) initialized successfully`
3. Look for: `🔗 Pusher connected`

### Test Flutter Connection:

1. Run your Flutter app
2. Check logs for: `✅ Connected to Pusher WebSocket`

---

## Troubleshooting

### Issue: "Pusher connection failed"

**Solutions:**
- Check that `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, and `PUSHER_APP_ID` are correct in `.env`
- Verify `PUSHER_APP_CLUSTER` matches your Pusher dashboard
- Clear config cache: `php artisan config:clear && php artisan config:cache`
- Check firewall/network allows connections to Pusher

### Issue: "Authentication failed" on private channels

**Solutions:**
- Verify `/broadcasting/auth` route exists and works
- Check that user is authenticated
- Verify `BroadcastAuthController` is using `Broadcast::auth()` for Pusher

### Issue: Frontend can't connect

**Solutions:**
- Check `VITE_PUSHER_APP_KEY` is set in `.env`
- Rebuild frontend assets: `npm run build` or `npm run dev`
- Check browser console for errors

---

## Next Steps

Once Pusher is working:
1. Test real-time messaging
2. Test typing indicators
3. Test online/offline status
4. Monitor Pusher dashboard for connection stats

---

## Free Tier Limits

Pusher free tier includes:
- 100 concurrent connections
- 200,000 messages per day
- All features

Upgrade if you need more connections or messages.
