# 🚀 Complete Setup Instructions for GekyChat Mobile API

## Step-by-Step Configuration Guide

---

## 1️⃣ Firebase Cloud Messaging (FCM) Setup

### **Get Your FCM Server Key:**

1. Go to [Firebase Console](https://console.firebase.google.com)
2. **If you don't have a project:**
   - Click "Add project"
   - Enter project name: "GekyChat" (or your preferred name)
   - Disable Google Analytics (optional)
   - Click "Create project"

3. **Get the Server Key:**
   - In your Firebase project, click the **gear icon** (Settings) → **Project settings**
   - Go to **Cloud Messaging** tab
   - Scroll down to **Cloud Messaging API (Legacy)**
   - Copy the **Server key**
   - Add to your `.env` file:
     ```env
     FCM_SERVER_KEY=your-copied-server-key-here
     ```

4. **For the Flutter App:**
   - In Firebase Console, go to **Project settings** → **General**
   - Under "Your apps", click **Add app** → Choose platform (Android/iOS)
   - Download `google-services.json` (Android) or `GoogleService-Info.plist` (iOS)
   - Add to your Flutter project as per Firebase documentation

---

## 2️⃣ Broadcasting: Choose Your Strategy

### **Option A: Use Pusher (Recommended - Matches Flutter Spec)**

**Why:** The Flutter app specification expects Pusher. This is the easiest path.

**Steps:**
1. Go to [Pusher Dashboard](https://dashboard.pusher.com) and sign up/login
2. Click **Create app** (or select existing app)
3. Enter app details:
   - Name: "GekyChat"
   - Cluster: Choose closest (e.g., mt1, us2, eu)
   - Tech stack: Laravel + Flutter
4. Go to **App Keys** tab
5. Copy these values to your `.env`:
   ```env
   BROADCAST_CONNECTION=pusher
   BROADCAST_DRIVER=pusher
   
   PUSHER_APP_ID=your-app-id
   PUSHER_APP_KEY=your-app-key
   PUSHER_APP_SECRET=your-app-secret
   PUSHER_APP_CLUSTER=mt1
   PUSHER_PORT=443
   PUSHER_SCHEME=https
   ```

**Update Flutter App:**
- No changes needed! It's already configured for Pusher.

---

### **Option B: Keep Reverb (More Work, but Free)**

**Why:** Reverb is Laravel's free WebSocket server. But requires Flutter app updates.

**Steps:**
1. Keep your current Reverb configuration in `.env`
2. Update Flutter app to use Reverb instead of Pusher:
   ```dart
   // In Flutter app, replace Pusher config with:
   import 'package:laravel_echo/laravel_echo.dart';
   
   Echo echo = Echo({
     'broadcaster': 'reverb',
     'authEndpoint': 'http://127.0.0.1:8000/api/v1/broadcasting/auth',
     'wsHost': '127.0.0.1',
     'wsPort': 8080,
   });
   ```
3. Ensure Reverb server is running:
   ```bash
   php artisan reverb:start
   ```

**My Recommendation:** Use **Option A (Pusher)** for production. It's more reliable and the Flutter app is already configured for it. Pusher has a free tier (100 connections, 200k messages/day).

---

## 3️⃣ Complete .env Configuration

I'll create a complete `.env` file for you with all required values. See `.env.complete` file.

---

## 4️⃣ After Configuration Steps

### **Step 1: Clear Config Cache**
```bash
php artisan config:clear
php artisan config:cache
```

### **Step 2: Verify Configuration**
```bash
php artisan tinker
```

Then run these checks:
```php
>>> config('services.fcm.server_key')        // Should show your FCM key
>>> config('broadcasting.default')            // Should show 'pusher' or 'reverb'
>>> config('filesystems.default')             // Should show 'public'
>>> exit
```

### **Step 3: Test Authentication**
```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/phone \
  -H "Content-Type: application/json" \
  -d '{"phone": "+1111111111"}'
```

Expected response:
```json
{
  "success": true,
  "message": "OTP sent successfully",
  "expires_in": 300
}
```

### **Step 4: Verify OTP**
```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/verify \
  -H "Content-Type: application/json" \
  -d '{"phone": "+1111111111", "code": "123456"}'
```

Expected response:
```json
{
  "token": "1|...",
  "user": {
    "id": 1,
    "name": "User_1111",
    "phone": "1111111111",
    "avatar_url": "https://..."
  }
}
```

---

## 5️⃣ Production Checklist

Before deploying to production:

- [ ] Set `APP_ENV=production`
- [ ] Set `APP_DEBUG=false`
- [ ] Use strong database password
- [ ] Get real Pusher credentials (not test)
- [ ] Get real FCM server key
- [ ] Set up SSL/HTTPS
- [ ] Update `APP_URL` to your domain
- [ ] Update `SESSION_DOMAIN` to your domain
- [ ] Update `SANCTUM_STATEFUL_DOMAINS` to your domains
- [ ] Set up cron job for scheduled tasks
- [ ] Configure database backups
- [ ] Set up error monitoring (Sentry)
- [ ] Configure cloud storage (S3/Spaces) instead of local

---

## 🆘 Troubleshooting

### Issue: "FCM Server Key not found"
**Solution:** Make sure you copied the **Server key** from Firebase Console → Cloud Messaging → Cloud Messaging API (Legacy)

### Issue: "Pusher connection failed"
**Solution:** 
- Verify all PUSHER_* values in .env
- Check Pusher app is active in dashboard
- Verify cluster matches (mt1, us2, eu, etc.)

### Issue: "OTP not sending"
**Solution:** Your Arkesel SMS is already configured! If OTP isn't sending:
- Check Arkesel account has credit
- Verify API key is correct
- For testing, use phone `+1111111111` with OTP `123456`

### Issue: "Storage not working"
**Solution:**
```bash
php artisan storage:link
chmod -R 775 storage
chmod -R 775 bootstrap/cache
```

---

## 📱 Flutter App Configuration

After backend setup, update your Flutter app's `.env` or config:

```env
API_BASE_URL=http://127.0.0.1:8000/api/v1
PUSHER_APP_KEY=your-pusher-app-key-here
PUSHER_CLUSTER=mt1
PUSHER_AUTH_ENDPOINT=http://127.0.0.1:8000/api/v1/broadcasting/auth
```

For production:
```env
API_BASE_URL=https://chat.gekychat.com/api/v1
PUSHER_APP_KEY=your-pusher-app-key-here
PUSHER_CLUSTER=mt1
PUSHER_AUTH_ENDPOINT=https://chat.gekychat.com/api/v1/broadcasting/auth
```

---

## ✅ Quick Checklist

- [ ] Added FCM_SERVER_KEY to .env
- [ ] Chose Pusher or Reverb strategy
- [ ] Added broadcasting credentials to .env
- [ ] Added FILESYSTEM_DISK=public
- [ ] Added SESSION_DOMAIN and SANCTUM_STATEFUL_DOMAINS
- [ ] Ran `php artisan config:cache`
- [ ] Tested authentication endpoint
- [ ] Verified configuration with tinker
- [ ] Updated Flutter app config
- [ ] Tested real-time features

---

## 🎯 Next Steps

1. **Right now:** Copy the provided `.env.complete` file content
2. **Get credentials:** Follow Firebase and Pusher setup above
3. **Test:** Use the test commands provided
4. **Deploy:** Use production checklist

---

**Need Help?** Check the troubleshooting section or review the logs at `storage/logs/laravel.log`

🚀 **You're almost there!**

