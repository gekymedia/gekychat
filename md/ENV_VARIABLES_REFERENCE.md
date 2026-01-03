# Complete Environment Variables Reference

This document lists all environment variables used in GekyChat. Copy from `ENV_TEMPLATE_COMPLETE.txt` or use this as a reference.

## 📋 Quick Copy-Paste Template

Copy this entire section to your `.env` file:

```env
# ========================================
# Application
# ========================================
APP_NAME=GekyChat
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_URL=http://gekychat.test
ASSET_URL=

# ========================================
# Subdomain Configuration
# ========================================
LANDING_DOMAIN=gekychat.test
CHAT_DOMAIN=chat.gekychat.test
API_DOMAIN=api.gekychat.test

# ========================================
# App Store & Download URLs
# ========================================
PLAY_STORE_URL=https://play.google.com/store/apps/details?id=com.gekychat.app
APP_STORE_URL=https://apps.apple.com/app/gekychat/id123456789
WINDOWS_DOWNLOAD_URL=https://github.com/gekychat/desktop/releases/download/latest/GekyChat-Setup.exe
MACOS_DOWNLOAD_URL=https://github.com/gekychat/desktop/releases/download/latest/GekyChat.dmg
LINUX_DOWNLOAD_URL=https://github.com/gekychat/desktop/releases/download/latest/gekychat_amd64.deb

# ========================================
# Database
# ========================================
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gekychat
DB_USERNAME=root
DB_PASSWORD=

# ========================================
# Session & Cookies
# ========================================
SESSION_DRIVER=database
SESSION_LIFETIME=43200
SESSION_DOMAIN=.gekychat.test
SANCTUM_STATEFUL_DOMAINS=gekychat.test,chat.gekychat.test,api.gekychat.test,127.0.0.1,localhost

# ========================================
# Broadcasting (Pusher)
# ========================================
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=mt1
PUSHER_HOST=
PUSHER_PORT=443
PUSHER_SCHEME=https
PUSHER_FORCE_TLS=true

# ========================================
# SMS (Arkesel)
# ========================================
ARKESEL_API_KEY=
ARKESEL_SENDER_ID=GEKYCHAT

# ========================================
# Firebase (Push Notifications)
# ========================================
FIREBASE_CREDENTIALS=storage/app/firebase/firebase-credentials.json
FIREBASE_PROJECT_ID=
FCM_SERVER_KEY=

# ========================================
# Frontend (Vite)
# ========================================
VITE_APP_NAME="${APP_NAME}"
VITE_API_BASE_URL="${APP_URL}/api/v1"
VITE_BROADCAST_AUTH="${APP_URL}/broadcasting/auth"
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_HOST="${PUSHER_HOST}"
VITE_PUSHER_PORT="${PUSHER_PORT}"
VITE_PUSHER_SCHEME="${PUSHER_SCHEME}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

## 📝 Variable Descriptions

### Application Variables
- `APP_NAME` - Application name (default: GekyChat)
- `APP_ENV` - Environment (local, production, staging)
- `APP_KEY` - Encryption key (generate with `php artisan key:generate`)
- `APP_DEBUG` - Debug mode (true/false)
- `APP_URL` - Base application URL

### Subdomain Configuration
- `LANDING_DOMAIN` - Main domain for landing page
- `CHAT_DOMAIN` - Subdomain for web chat interface
- `API_DOMAIN` - Subdomain for API endpoints

### App Store URLs
- `PLAY_STORE_URL` - Google Play Store link
- `APP_STORE_URL` - Apple App Store link
- `WINDOWS_DOWNLOAD_URL` - Windows desktop app download
- `MACOS_DOWNLOAD_URL` - macOS desktop app download
- `LINUX_DOWNLOAD_URL` - Linux desktop app download

### Session & Authentication
- `SESSION_DOMAIN` - Cookie domain (use `.gekychat.test` for subdomain sharing)
- `SANCTUM_STATEFUL_DOMAINS` - Domains allowed for stateful authentication

### Broadcasting (Pusher)
- `PUSHER_APP_ID` - Pusher App ID
- `PUSHER_APP_KEY` - Pusher App Key
- `PUSHER_APP_SECRET` - Pusher App Secret
- `PUSHER_APP_CLUSTER` - Pusher cluster (e.g., mt1)
- `PUSHER_HOST` - Custom Pusher host (optional)
- `PUSHER_PORT` - Pusher port (default: 443)
- `PUSHER_SCHEME` - http or https (default: https)

## 🔄 Local Development (Herd)

For Laravel Herd, use `.test` domains:

```env
APP_URL=http://gekychat.test
LANDING_DOMAIN=gekychat.test
CHAT_DOMAIN=chat.gekychat.test
API_DOMAIN=api.gekychat.test
SESSION_DOMAIN=.gekychat.test
SANCTUM_STATEFUL_DOMAINS=gekychat.test,chat.gekychat.test,api.gekychat.test,127.0.0.1,localhost
```

## 🚀 Production Configuration

For production, update domains:

```env
APP_URL=https://gekychat.com
LANDING_DOMAIN=gekychat.com
CHAT_DOMAIN=chat.gekychat.com
API_DOMAIN=api.gekychat.com
SESSION_DOMAIN=.gekychat.com
SANCTUM_STATEFUL_DOMAINS=gekychat.com,chat.gekychat.com,api.gekychat.com
APP_DEBUG=false
APP_ENV=production
```

