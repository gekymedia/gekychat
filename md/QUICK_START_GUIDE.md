# 🚀 Quick Start Guide - Pusher & 2FA Setup

## ✅ What's Been Done

1. ✅ **Pusher Migration Complete**
   - All Reverb code commented out (preserved for future use)
   - Pusher configuration added to Laravel and Flutter
   - Broadcasting controller updated for Pusher authentication

2. ✅ **Two-Factor Authentication Implemented**
   - 2FA methods added to User model
   - Email verification code system
   - Settings UI for enabling/disabling 2FA
   - Login flow integration

---

## 📋 Step 1: Configure Pusher

### Get Pusher Credentials

1. Go to https://dashboard.pusher.com
2. Create account or sign in
3. Create a new app
4. Copy App ID, Key, Secret, and Cluster

### Update Laravel `.env`

```env
BROADCAST_CONNECTION=pusher
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=mt1
PUSHER_PORT=443
PUSHER_SCHEME=https

# Frontend
VITE_PUSHER_APP_KEY=your-app-key
VITE_PUSHER_APP_CLUSTER=mt1
```

### Update Flutter `.env`

```env
PUSHER_KEY=your-app-key
PUSHER_CLUSTER=mt1
PUSHER_HOST=api-mt1.pusher.com
PUSHER_FORCE_TLS=true
PUSHER_AUTH_ENDPOINT=https://your-api-domain.com/api/v1/broadcasting/auth
```

### Clear Cache

```bash
php artisan config:clear
php artisan config:cache
```

---

## 📋 Step 2: Configure Email for 2FA

### Update Mail Configuration in `.env`

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@gekychat.com
MAIL_FROM_NAME="${APP_NAME}"
```

**For Production:**
- Use your actual SMTP server (Gmail, SendGrid, Mailgun, etc.)
- Or use Laravel's queue system for better performance

### Test Email

```bash
php artisan tinker
```

```php
Mail::to('your-email@example.com')->send(new \App\Mail\TwoFactorCodeMail('123456'));
exit
```

---

## 📋 Step 3: Test 2FA Flow

1. **Add Email to Account:**
   - Go to Settings → Security
   - Add your email address
   - Save

2. **Enable 2FA:**
   - Go to Settings → Account
   - Toggle "Two-factor authentication" ON
   - Save

3. **Test Login:**
   - Logout
   - Login with phone number
   - Enter OTP code
   - You should be redirected to 2FA verification page
   - Check email for 6-digit code
   - Enter code to complete login

---

## 🔍 Verification Checklist

- [ ] Pusher credentials added to `.env`
- [ ] Laravel config cache cleared
- [ ] Email configuration set up
- [ ] Email sending tested
- [ ] 2FA toggle visible in Settings
- [ ] 2FA requires email validation
- [ ] Login flow redirects to 2FA if enabled
- [ ] 2FA code received via email
- [ ] 2FA verification works
- [ ] Resend 2FA code works

---

## 🐛 Troubleshooting

### Pusher Connection Issues

```bash
# Check config
php artisan tinker
>>> config('broadcasting.default')  // Should be 'pusher'
>>> config('broadcasting.connections.pusher.key')  // Should show your key
```

### Email Not Sending

1. Check `.env` mail configuration
2. Test with `php artisan tinker` (see above)
3. Check `storage/logs/laravel.log` for errors
4. For development, use Mailtrap.io

### 2FA Code Not Received

1. Verify email is set on user account
2. Check spam folder
3. Check Laravel logs for email errors
4. Test email sending directly

---

## 📚 Next Steps

1. **API Integration:** Update mobile app API endpoints to handle 2FA
2. **Rate Limiting:** Already implemented, but verify limits are appropriate
3. **Security:** Consider adding backup codes for users
4. **Monitoring:** Add logging for 2FA attempts
5. **User Experience:** Add "Remember this device" option

---

## 📖 Documentation

- **Pusher Setup:** See `PUSHER_SETUP_INSTRUCTIONS.md`
- **2FA Details:** See `TWO_FACTOR_AUTH_SETUP.md`
