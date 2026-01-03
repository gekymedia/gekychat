# Laravel Herd Environment Configuration

## 🐑 For Laravel Herd Users

Since you're using Laravel Herd, you need to configure your `.env` file with Herd-specific domain settings. Herd automatically handles SSL certificates and uses `.test` domains by default.

## 📝 Add These to Your `.env` File

Add these lines to your `.env` file (around line 1-88 as you mentioned):

```env
# ============================================
# SUBDOMAIN CONFIGURATION (Laravel Herd)
# ============================================

# Main Application URL (Herd uses .test domains)
APP_URL=http://gekychat.test

# Subdomain Configuration for Herd
LANDING_DOMAIN=gekychat.test
CHAT_DOMAIN=chat.gekychat.test
API_DOMAIN=api.gekychat.test

# Session Configuration (Important for subdomain sessions!)
# The leading dot allows cookies to work across all subdomains
SESSION_DOMAIN=.gekychat.test

# Sanctum Configuration (for API authentication across subdomains)
# This allows Sanctum to work with all your subdomains
SANCTUM_STATEFUL_DOMAINS=gekychat.test,chat.gekychat.test,api.gekychat.test,127.0.0.1,localhost
```

## 🔧 Herd Setup Steps

### Step 1: Link Your Project to Herd

If you haven't already, link your project:

```bash
cd gekychat
herd link gekychat
```

This will create `gekychat.test` automatically.

### Step 2: Configure Subdomains in Herd

Herd automatically handles subdomains! Once you link the project, you can access:
- `http://gekychat.test` (main domain)
- `http://chat.gekychat.test` (chat subdomain)
- `http://api.gekychat.test` (API subdomain)

**Note:** Herd uses HTTP by default for `.test` domains. If you need HTTPS, you can enable it in Herd settings, but HTTP works fine for local development.

### Step 3: Update Your `.env` File

Add the configuration above to your `.env` file.

### Step 4: Clear Configuration Cache

After updating `.env`, clear Laravel's config cache:

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

## ✅ Testing

1. **Test Landing Page:**
   ```
   http://gekychat.test
   ```

2. **Test Chat Interface:**
   ```
   http://chat.gekychat.test
   ```

3. **Test API:**
   ```bash
   curl http://api.gekychat.test/api/v1/auth/phone
   ```

## 🔍 Complete `.env` Example (Relevant Sections)

Here's what your `.env` should look like (showing only the relevant parts):

```env
APP_NAME=GekyChat
APP_ENV=local
APP_KEY=base64:your-app-key-here
APP_DEBUG=true
APP_TIMEZONE=UTC
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

# Main Application URL (Herd)
APP_URL=http://gekychat.test

# Subdomain Configuration (Herd)
LANDING_DOMAIN=gekychat.test
CHAT_DOMAIN=chat.gekychat.test
API_DOMAIN=api.gekychat.test

# Session Configuration
SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=.gekychat.test

# Sanctum Configuration
SANCTUM_STATEFUL_DOMAINS=gekychat.test,chat.gekychat.test,api.gekychat.test,127.0.0.1,localhost

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gekychat
DB_USERNAME=root
DB_PASSWORD=

# ... rest of your configuration
```

## 🚨 Important Notes for Herd

1. **No Manual DNS Configuration Needed**: Herd automatically handles `.test` domains
2. **No SSL Setup Needed**: Herd handles SSL automatically (though HTTP works fine for local dev)
3. **Session Cookies**: The `.gekychat.test` domain (with leading dot) allows sessions to work across all subdomains
4. **Sanctum**: Make sure `SANCTUM_STATEFUL_DOMAINS` includes all your subdomains

## 🐛 Troubleshooting

### Routes Not Working
```bash
php artisan route:clear
php artisan config:clear
php artisan route:list
```

### Sessions Not Working Across Subdomains
- Verify `SESSION_DOMAIN=.gekychat.test` (with leading dot)
- Clear browser cookies
- Check browser console for cookie errors

### API Not Accessible
- Verify `SANCTUM_STATEFUL_DOMAINS` includes all subdomains
- Check CORS configuration if making cross-origin requests

## 📱 For Production

When deploying to production, update these values:

```env
APP_URL=https://gekychat.com
LANDING_DOMAIN=gekychat.com
CHAT_DOMAIN=chat.gekychat.com
API_DOMAIN=api.gekychat.com
SESSION_DOMAIN=.gekychat.com
SANCTUM_STATEFUL_DOMAINS=gekychat.com,chat.gekychat.com,api.gekychat.com
```

---

**Herd Version**: Works with all Herd versions  
**Last Updated**: January 2025


