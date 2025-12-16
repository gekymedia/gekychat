# 🔐 Environment Configuration Guide

## Required Environment Variables for Mobile API

Add these to your `.env` file:

### 1. Firebase Cloud Messaging (FCM) - **REQUIRED**

```env
FCM_SERVER_KEY=your-firebase-server-key
```

**How to get:**
1. Go to [Firebase Console](https://console.firebase.google.com)
2. Select your project (or create new)
3. Go to **Project Settings** → **Cloud Messaging**
4. Copy the **Server Key** (Legacy)

---

### 2. Pusher (Real-time Broadcasting) - **REQUIRED**

```env
BROADCAST_CONNECTION=pusher
BROADCAST_DRIVER=pusher

PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_KEY=your-pusher-app-key
PUSHER_APP_SECRET=your-pusher-app-secret
PUSHER_APP_CLUSTER=mt1
PUSHER_PORT=443
PUSHER_SCHEME=https
```

**How to get:**
1. Go to [Pusher Dashboard](https://dashboard.pusher.com)
2. Create a new app or select existing
3. Go to **App Keys** tab
4. Copy the credentials

---

### 3. Database Configuration

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gekychat
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

---

### 4. App Configuration

```env
APP_NAME=GekyChat
APP_URL=https://chat.gekychat.com
APP_ENV=production
APP_DEBUG=false
```

---

### 5. File Storage

```env
FILESYSTEM_DISK=public
```

**For production with S3:**
```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket-name
```

---

### 6. SMS Service (Already Configured)

```env
ARKESEL_API_KEY=your-arkesel-api-key
ARKESEL_SENDER_ID=GekyChat
```

---

### 7. Session & CORS

```env
SESSION_DOMAIN=.gekychat.com
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,chat.gekychat.com
```

---

## Complete .env Template

```env
# ========================================
# REQUIRED FOR MOBILE API
# ========================================

# FCM Push Notifications
FCM_SERVER_KEY=your-firebase-server-key

# Pusher Real-time
BROADCAST_CONNECTION=pusher
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your-pusher-app-id
PUSHER_APP_KEY=your-pusher-app-key
PUSHER_APP_SECRET=your-pusher-app-secret
PUSHER_APP_CLUSTER=mt1
PUSHER_PORT=443
PUSHER_SCHEME=https

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gekychat
DB_USERNAME=your_username
DB_PASSWORD=your_password

# App
APP_NAME=GekyChat
APP_URL=https://chat.gekychat.com
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-generated-key

# Storage
FILESYSTEM_DISK=public

# Session & CORS
SESSION_DOMAIN=.gekychat.com
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,chat.gekychat.com

# ========================================
# OPTIONAL BUT RECOMMENDED
# ========================================

# Queue (for async jobs)
QUEUE_CONNECTION=database

# Cache
CACHE_STORE=database

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@gekychat.com"
MAIL_FROM_NAME="${APP_NAME}"

# Redis (recommended for production)
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Sentry (error tracking)
# SENTRY_LARAVEL_DSN=your-sentry-dsn
```

---

## Testing Configuration

For development/testing, use these test credentials:

```env
# Test Phone Number
# Phone: +1111111111
# OTP: 123456 (always works)
```

---

## Security Checklist

- [ ] Set `APP_DEBUG=false` in production
- [ ] Use strong database password
- [ ] Keep FCM_SERVER_KEY secret
- [ ] Keep PUSHER_APP_SECRET secret
- [ ] Enable HTTPS (SSL certificate)
- [ ] Configure proper CORS domains
- [ ] Set secure SESSION_DOMAIN
- [ ] Use Redis in production (optional but recommended)
- [ ] Set up error monitoring (Sentry)
- [ ] Configure database backups

---

## After Configuration

1. **Generate App Key** (if not already):
   ```bash
   php artisan key:generate
   ```

2. **Run Migrations:**
   ```bash
   php artisan migrate
   ```

3. **Link Storage:**
   ```bash
   php artisan storage:link
   ```

4. **Clear Config Cache:**
   ```bash
   php artisan config:clear
   php artisan config:cache
   ```

5. **Test the Setup:**
   ```bash
   php artisan tinker
   >>> config('services.fcm.server_key')
   >>> config('broadcasting.connections.pusher')
   ```

---

## Troubleshooting

### Issue: Pusher not connecting
**Check:**
- `BROADCAST_DRIVER=pusher` is set
- All PUSHER_* variables are correct
- Pusher app is active in dashboard
- Cluster is correct (mt1, us2, eu, etc.)

### Issue: FCM notifications not sending
**Check:**
- `FCM_SERVER_KEY` is the **Server Key** (not Client Key)
- Server Key is from Cloud Messaging section
- Firebase project is active

### Issue: OTP not sending
**Check:**
- Arkesel API key is valid
- SMS service has credit
- Phone number format is correct

### Issue: CORS errors
**Check:**
- `SANCTUM_STATEFUL_DOMAINS` includes your frontend domain
- `SESSION_DOMAIN` is correct (include leading dot for subdomains)
- CORS middleware is configured

---

## Next Steps

1. ✅ Copy template to `.env`
2. ✅ Fill in all REQUIRED values
3. ✅ Run migrations
4. ✅ Test authentication endpoint
5. ✅ Test status creation
6. ✅ Test real-time messaging
7. ✅ Test push notifications

---

**Ready to configure! 🚀**

