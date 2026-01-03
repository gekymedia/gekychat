# API Landing Page Setup - Like api.whatsapp.com

## ✅ What's Implemented

Your `api.gekychat.com` domain now works like `api.whatsapp.com`:

1. **Browser Requests**: Shows a beautiful landing page prompting users to:
   - Open the GekyChat app (if installed)
   - Download the app from Play Store / App Store
   - Automatically attempts to open the app after a few seconds

2. **API Requests**: Still works normally - returns JSON for API clients
   - Detects if request wants JSON (`wantsJson()` or `/api/*` path)
   - Returns proper JSON response for API clients
   - All existing API endpoints (`/api/v1/*`) continue to work

## 📱 Features

- **Smart Detection**: Automatically detects mobile devices (Android/iOS)
- **Deep Linking**: Attempts to open app using custom scheme `gekychat://`
- **Android Intent**: Uses Android intent URLs for better app opening
- **Auto Redirect**: After attempting to open app, redirects to app store if app not installed
- **Download Buttons**: Direct links to Play Store and App Store
- **Responsive Design**: Works beautifully on all devices

## ⚙️ Configuration

### 1. Update App Store URLs in `.env`

Add these to your `gekychat/.env` file:

```env
# App Store URLs (update with your actual app store links)
PLAY_STORE_URL=https://play.google.com/store/apps/details?id=com.gekychat.app
APP_STORE_URL=https://apps.apple.com/app/gekychat/id123456789
```

**Important**: Replace these with your actual app store URLs when you publish your app!

### 2. Configure Deep Linking in Mobile App

Your mobile app needs to be configured to handle the `gekychat://` URL scheme:

#### Android (`android/app/src/main/AndroidManifest.xml`):

```xml
<activity
    android:name=".MainActivity"
    android:launchMode="singleTop"
    android:theme="@style/LaunchTheme"
    android:exported="true">
    
    <!-- Existing intent filter -->
    <intent-filter>
        <action android:name="android.intent.action.MAIN"/>
        <category android:name="android.intent.category.LAUNCHER"/>
    </intent-filter>
    
    <!-- Deep link intent filter -->
    <intent-filter>
        <action android:name="android.intent.action.VIEW"/>
        <category android:name="android.intent.category.DEFAULT"/>
        <category android:name="android.intent.category.BROWSABLE"/>
        <data android:scheme="gekychat"/>
    </intent-filter>
</activity>
```

#### iOS (`ios/Runner/Info.plist`):

Add this inside the `<dict>` tag:

```xml
<key>CFBundleURLTypes</key>
<array>
    <dict>
        <key>CFBundleTypeRole</key>
        <string>Editor</string>
        <key>CFBundleURLName</key>
        <string>com.gekychat.app</string>
        <key>CFBundleURLSchemes</key>
        <array>
            <string>gekychat</string>
        </array>
    </dict>
</array>
```

### 3. Handle Deep Links in Flutter App

In your Flutter app, you can handle the deep link using packages like:
- `uni_links` or `app_links` for handling deep links
- Handle the `gekychat://open` URL when app opens

Example:
```dart
// In your main.dart or router
AppLinks().uriLinkStream.listen((uri) {
  if (uri.scheme == 'gekychat' && uri.host == 'open') {
    // Navigate to appropriate screen
    navigatorKey.currentState?.pushNamed('/home');
  }
});
```

## 🎨 Customization

### Change App Scheme

If you want to use a different URL scheme (not `gekychat://`), update:

1. **Controller** (`app/Http/Controllers/ApiLandingController.php`):
   ```php
   $appScheme = 'your-scheme://';
   ```

2. **View** (`resources/views/api/landing.blade.php`):
   Update the deep link URL

3. **Mobile App**: Update AndroidManifest.xml and Info.plist accordingly

### Customize Landing Page Design

Edit `resources/views/api/landing.blade.php` to customize:
- Colors (currently uses GekyChat green theme)
- Logo/Icon
- Text content
- Button styles
- Layout

## 🧪 Testing

### Test in Browser

1. Visit `http://api.gekychat.test` (local) or `https://api.gekychat.com` (production)
2. You should see the landing page
3. On mobile, it will attempt to open the app

### Test API Endpoints

API endpoints still work:

```bash
# JSON response
curl -H "Accept: application/json" http://api.gekychat.test

# Or with /api/ prefix
curl http://api.gekychat.test/api/ping
```

### Test Deep Links

#### Android:
```
adb shell am start -W -a android.intent.action.VIEW -d "gekychat://open" com.gekychat.app
```

#### iOS (Simulator):
Open Safari and navigate to: `gekychat://open`

#### Browser:
Visit: `gekychat://open` (should prompt to open app)

## 📋 URL Behavior

| URL | Browser Request | API Request (JSON header) | Mobile Device |
|-----|----------------|--------------------------|---------------|
| `api.gekychat.com` | Landing Page | JSON Info | Landing Page + App Prompt |
| `api.gekychat.com/api/ping` | JSON Response | JSON Response | JSON Response |
| `api.gekychat.com/api/v1/*` | API Endpoints | API Endpoints | API Endpoints |

## 🔧 Troubleshooting

### Landing Page Not Showing

- Clear Laravel cache: `php artisan config:clear && php artisan route:clear`
- Check route registration: `php artisan route:list | grep api`

### App Not Opening

- Verify deep link scheme is configured in mobile app
- Check AndroidManifest.xml / Info.plist
- Test deep link manually: `gekychat://open`

### API Endpoints Not Working

- API endpoints should still work normally
- If JSON requests are showing HTML, check `wantsJson()` detection
- Make sure API requests include `Accept: application/json` header

## 🚀 Next Steps

1. ✅ Update app store URLs in `.env`
2. ✅ Configure deep linking in mobile app
3. ✅ Test landing page in browser
4. ✅ Test deep links on mobile devices
5. ✅ Customize landing page design if needed

---

**Status**: ✅ Complete - Ready to use!

