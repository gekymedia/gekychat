# ⚡ Quick Setup Guide - GekyChat Mobile API

## 🚀 Get Started in 5 Minutes

### Step 1: Environment Configuration

Add these to your `.env` file:

```env
# FCM Push Notifications
FCM_SERVER_KEY=your-firebase-server-key

# Pusher Real-time
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=mt1

# Database (ensure this is set)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gekychat
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Storage
FILESYSTEM_DISK=public

# App URL
APP_URL=https://chat.gekychat.com
```

### Step 2: Run Migrations

```bash
php artisan migrate
```

This will create:
- ✅ Updated `statuses` table
- ✅ `status_privacy_settings` table
- ✅ `status_mutes` table
- ✅ `otp_codes` table
- ✅ `device_tokens` table

### Step 3: Link Storage

```bash
php artisan storage:link
```

### Step 4: Set Up Cron Job

Add to your crontab:
```bash
* * * * * cd /path-to-gekychat && php artisan schedule:run >> /dev/null 2>&1
```

Or manually test:
```bash
php artisan statuses:clean-expired
```

### Step 5: Start the Server

```bash
php artisan serve
```

Or configure your web server (Nginx/Apache) to point to `/public`.

---

## 🧪 Test the API

### 1. Request OTP

```bash
curl -X POST https://chat.gekychat.com/api/v1/auth/phone \
  -H "Content-Type: application/json" \
  -d '{"phone": "+1111111111"}'
```

### 2. Verify OTP (Test Account)

```bash
curl -X POST https://chat.gekychat.com/api/v1/auth/verify \
  -H "Content-Type: application/json" \
  -d '{"phone": "+1111111111", "code": "123456"}'
```

Response:
```json
{
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "User_1111",
    "phone": "1111111111",
    "avatar_url": "https://..."
  }
}
```

### 3. Get Statuses (Authenticated)

```bash
curl -X GET https://chat.gekychat.com/api/v1/statuses \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

### 4. Create Text Status

```bash
curl -X POST https://chat.gekychat.com/api/v1/statuses \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "text",
    "text": "Hello World!",
    "background_color": "#00A884"
  }'
```

---

## 🔑 Quick Testing Account

For easy testing, use:
- **Phone:** `+1111111111`
- **OTP:** `123456` (always works for this number)

---

## 📋 Verify Installation

Run these checks:

### Check Migrations
```bash
php artisan migrate:status
```

Should show all migrations as "Ran".

### Check Routes
```bash
php artisan route:list | grep "api/v1"
```

Should show 40+ API endpoints.

### Check Models
```bash
php artisan tinker
>>> App\Models\Status::count()
>>> App\Models\OtpCode::count()
>>> App\Models\DeviceToken::count()
```

### Test Scheduled Command
```bash
php artisan statuses:clean-expired
```

Should output:
```
Cleaning expired statuses...
Deleted 0 expired statuses.
Deleted 0 expired OTP codes.
```

---

## 🐛 Troubleshooting

### Issue: Migrations fail
**Solution:**
```bash
php artisan migrate:fresh
php artisan db:seed  # If you have seeders
```

### Issue: Storage not working
**Solution:**
```bash
php artisan storage:link
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

### Issue: Pusher not connecting
**Solution:**
- Verify `PUSHER_*` variables in `.env`
- Check Pusher dashboard for connection logs
- Ensure `BroadcastServiceProvider` is registered

### Issue: FCM notifications not sending
**Solution:**
- Get FCM Server Key from Firebase Console
- Add to `.env` as `FCM_SERVER_KEY`
- Test with a simple notification

---

## 📱 Flutter App Configuration

Update your Flutter app's `.env`:

```env
API_BASE_URL=https://chat.gekychat.com/api/v1
PUSHER_APP_KEY=your-app-key
PUSHER_CLUSTER=mt1
PUSHER_AUTH_ENDPOINT=https://chat.gekychat.com/api/v1/broadcasting/auth
```

---

## ✅ Checklist

Before going live:

- [ ] Environment variables configured
- [ ] Migrations run successfully
- [ ] Storage linked
- [ ] Cron job set up
- [ ] FCM server key added
- [ ] Pusher credentials verified
- [ ] SMS service configured (Arkesel)
- [ ] Test OTP authentication
- [ ] Test status creation/viewing
- [ ] Test real-time messaging
- [ ] Test push notifications
- [ ] SSL certificate installed (HTTPS)

---

## 🎯 What's Next?

1. **Configure Firebase:**
   - Go to [Firebase Console](https://console.firebase.google.com)
   - Create project
   - Get Server Key from Cloud Messaging settings
   - Add to `.env`

2. **Test Real-time Features:**
   - Open two browsers
   - Send message from one
   - See it appear in other instantly

3. **Mobile App Integration:**
   - Update API endpoints in Flutter app
   - Test authentication flow
   - Test status creation/viewing
   - Verify push notifications

4. **Production Deployment:**
   - Set up proper web server (Nginx/Apache)
   - Configure SSL certificate
   - Set up Redis for caching (optional)
   - Configure queue workers
   - Set up monitoring (Sentry, etc.)

---

## 📞 Need Help?

- Check `storage/logs/laravel.log` for errors
- Review `MOBILE_API_IMPLEMENTATION.md` for detailed docs
- Test endpoints with Postman
- Verify database tables exist

---

**Ready to go! 🚀**

Your GekyChat backend is now fully compatible with the Flutter mobile app!

