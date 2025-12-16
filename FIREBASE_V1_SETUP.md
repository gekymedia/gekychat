# 🔥 Firebase Cloud Messaging V1 API Setup Guide

## Quick Setup (5 minutes)

### Step 1: Get Service Account JSON

1. **In Firebase Console** (where you just were):
   - Click **"Manage Service Accounts"** next to your Sender ID (480117137376)
   
2. **In Google Cloud Console** (will open):
   - Find the service account (looks like: `firebase-adminsdk-xxxxx@your-project.iam.gserviceaccount.com`)
   - Click the **three dots** (⋮) on the right
   - Select **"Manage keys"**
   
3. **Create New Key:**
   - Click **"Add Key"** → **"Create new key"**
   - Choose **JSON** format
   - Click **"Create"**
   - A JSON file will download (e.g., `gekychat-firebase-adminsdk-xxxxx.json`)

### Step 2: Add JSON File to Your Project

```bash
# In your Laravel project root
mkdir -p storage/app/firebase

# Copy the downloaded JSON file
# Windows (PowerShell):
copy C:\Users\YourName\Downloads\gekychat-*.json storage\app\firebase\firebase-credentials.json

# Or manually move it to:
# D:\projects\gekychat\storage\app\firebase\firebase-credentials.json
```

### Step 3: Get Your Project ID

**Option A:** From the JSON file you just downloaded, open it and look for:
```json
{
  "project_id": "YOUR-PROJECT-ID-HERE",
  ...
}
```

**Option B:** From your Firebase Console URL:
```
https://console.firebase.google.com/project/YOUR-PROJECT-ID/settings/cloudmessaging
```

### Step 4: Update Your .env

Add these lines to your `.env` file:

```env
# Firebase Cloud Messaging V1 API
FIREBASE_CREDENTIALS=storage/app/firebase/firebase-credentials.json
FIREBASE_PROJECT_ID=your-project-id-from-step-3

# Also add these for storage and CORS:
FILESYSTEM_DISK=public
SESSION_DOMAIN=.gekychat.com
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,chat.gekychat.com,127.0.0.1:8000

# Cache & Queue
CACHE_STORE=database
QUEUE_CONNECTION=database
```

### Step 5: Clear Config Cache

```bash
php artisan config:clear
php artisan config:cache
```

### Step 6: Test

```bash
# Test authentication
php artisan tinker
```

Then in tinker:
```php
>>> config('services.fcm.project_id')
// Should show your project ID

>>> config('services.fcm.credentials_path')
// Should show: storage/app/firebase/firebase-credentials.json

>>> file_exists(base_path(config('services.fcm.credentials_path')))
// Should return: true

>>> exit
```

---

## 🧪 Test Push Notification

Create a test script to verify FCM works:

```bash
php artisan tinker
```

```php
>>> $fcm = app(\App\Services\FcmService::class);
>>> $token = 'your-device-token-here'; // Get from Flutter app
>>> $fcm->sendToToken($token, ['title' => 'Test', 'body' => 'Hello from GekyChat!'], []);
```

---

## ✅ Verification Checklist

- [ ] Downloaded service account JSON file
- [ ] Moved JSON file to `storage/app/firebase/firebase-credentials.json`
- [ ] Added `FIREBASE_CREDENTIALS` to .env
- [ ] Added `FIREBASE_PROJECT_ID` to .env
- [ ] Added `FILESYSTEM_DISK=public` to .env
- [ ] Added `SANCTUM_STATEFUL_DOMAINS` to .env
- [ ] Ran `php artisan config:cache`
- [ ] Verified in tinker (file exists, config loaded)

---

## 🎯 What About Broadcasting (Pusher/Reverb)?

You still need to decide on broadcasting for real-time messaging:

### Option A: Use Pusher (Easier for Flutter)
```env
BROADCAST_CONNECTION=pusher
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=get-from-pusher.com
PUSHER_APP_KEY=get-from-pusher.com
PUSHER_APP_SECRET=get-from-pusher.com
PUSHER_APP_CLUSTER=mt1
```

### Option B: Keep Reverb (Your current setup)
- No .env changes needed
- But update Flutter app to use Reverb

---

## 📱 Complete .env Template

Here's what your complete .env should look like:

```env
# App
APP_NAME=GekyChat
APP_ENV=local
APP_KEY=base64:u8ksOcmj7VVmwObyYmV2g4hZ6wEXcKXTOCBnU8u3cK0=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gekychat2
DB_USERNAME=root
DB_PASSWORD=

# Firebase V1 API ⭐ NEW
FIREBASE_CREDENTIALS=storage/app/firebase/firebase-credentials.json
FIREBASE_PROJECT_ID=your-project-id-here

# Broadcasting (Choose one)
# Option A: Pusher
BROADCAST_CONNECTION=pusher
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1

# Option B: Reverb (your current)
# BROADCAST_CONNECTION=reverb
# BROADCAST_DRIVER=reverb
# REVERB_APP_ID=764795
# REVERB_APP_KEY=qvrnvlk7g8xkc1tydknr
# REVERB_APP_SECRET=by1djftyyyddozmo3srr
# REVERB_HOST=127.0.0.1
# REVERB_PORT=8080
# REVERB_SCHEME=http

# Storage & CORS ⭐ NEW
FILESYSTEM_DISK=public
SESSION_DOMAIN=.gekychat.com
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,chat.gekychat.com,127.0.0.1:8000

# Cache & Queue ⭐ NEW
CACHE_STORE=database
QUEUE_CONNECTION=database

# SMS (Already configured ✅)
ARKESEL_API_KEY=elppSXJiemh6ZHpTUVpGTW5mSW0
ARKESEL_SMS_SENDER=GekyChat
ARKESEL_SENDER_ID=GekyChat

# Google OAuth (Optional)
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/google/callback
```

---

## 🆘 Troubleshooting

### Issue: "FCM credentials file not found"
**Check:**
```bash
# Verify file exists
ls storage/app/firebase/firebase-credentials.json

# If not, create directory and copy file again
mkdir -p storage/app/firebase
```

### Issue: "Invalid FCM credentials file"
**Check:** Make sure the JSON file is valid. Open it and verify it has:
- `"type": "service_account"`
- `"project_id": "your-project"`
- `"private_key": "-----BEGIN PRIVATE KEY-----..."`

### Issue: "Failed to get FCM access token"
**Check:**
- JSON file is in correct location
- JSON file contains valid private key
- Project ID in .env matches JSON file

---

## 🎉 You're Done!

Once you complete these steps, your push notifications will work with the modern Firebase V1 API!

**Next:** Test authentication and create a status to verify everything works:

```bash
# Test OTP
curl -X POST http://127.0.0.1:8000/api/v1/auth/phone \
  -H "Content-Type: application/json" \
  -d '{"phone": "+1111111111"}'

# Verify OTP
curl -X POST http://127.0.0.1:8000/api/v1/auth/verify \
  -H "Content-Type: application/json" \
  -d '{"phone": "+1111111111", "code": "123456"}'
```

