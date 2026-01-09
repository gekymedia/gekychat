# Deep Link Setup Guide

This guide explains how to configure Android App Links and iOS Universal Links for GekyChat mobile app.

## Android App Links

Android App Links require a Digital Asset Links file that verifies your app's ownership of the domain.

### Step 1: Get Your App's SHA256 Fingerprint

#### For Debug Builds:

**On Windows (PowerShell or Command Prompt):**

If keytool is in your PATH:
```powershell
keytool -list -v -keystore "$env:USERPROFILE\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android
```

If keytool is at `D:\Program Files\jbr\bin\keytool.exe`:
```powershell
& "D:\Program Files\jbr\bin\keytool.exe" -list -v -keystore "$env:USERPROFILE\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android
```

**On Mac/Linux:**
```bash
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android
```

**Alternative Windows path (if above doesn't work):**
```powershell
keytool -list -v -keystore "C:\Users\$env:USERNAME\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android
```

#### For Release Builds:

**On Windows:**
```powershell
keytool -list -v -keystore "C:\path\to\your\release.keystore" -alias your-alias-name
```

**On Mac/Linux:**
```bash
keytool -list -v -keystore /path/to/your/release.keystore -alias your-alias-name
```

**Note:** If `keytool` is not found, you need to add Java to your PATH or use the full path:
- Windows: `"C:\Program Files\Java\jdk-XX\bin\keytool.exe"` (replace XX with your JDK version)
- Mac: Usually at `/usr/libexec/java_home -v XX/bin/keytool` or `/Library/Java/JavaVirtualMachines/jdk-XX.jdk/Contents/Home/bin/keytool`
- Linux: Usually at `/usr/bin/keytool` or in your JDK installation directory

Look for the `SHA256:` line in the output. Copy the fingerprint (it should look like: `AA:BB:CC:DD:EE:FF:...`).

### Step 2: Update DeepLinkController

Edit `app/Http/Controllers/DeepLinkController.php` and add your SHA256 fingerprint(s) to the `$sha256Fingerprints` array:

```php
$sha256Fingerprints = [
    'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99', // Debug keystore
    // Add your release keystore fingerprint here too
];
```

### Step 3: Verify the Configuration

1. Deploy the changes to your server
2. Visit `https://chat.gekychat.com/.well-known/assetlinks.json` in a browser
3. You should see a JSON response with your app's package name and fingerprints

### Step 4: Test on Android

1. **IMPORTANT:** Uninstall the app completely from your device (if already installed)
2. Rebuild and install the app: `flutter build apk --debug` then install on device
3. Click a group invite link (e.g., `https://chat.gekychat.com/groups/join/ABC123`)
4. The app should open instead of the browser

**Note:** Android verifies App Links when the app is first installed. If you change the fingerprints or the verification file, you MUST uninstall and reinstall the app for the verification to take effect. Android caches the verification result.

**Troubleshooting if links still open in browser:**
- Verify the assetlinks.json file is accessible: `https://chat.gekychat.com/.well-known/assetlinks.json`
- Check that the SHA256 fingerprint matches exactly (including colons)
- Ensure the package name matches: `com.example.gekychat_mobile`
- Uninstall and reinstall the app
- Use `adb shell pm get-app-links com.example.gekychat_mobile` to check verification status
- Clear app data: Settings → Apps → GekyChat → Storage → Clear Data

## iOS Universal Links

iOS Universal Links require an Apple App Site Association file.

### Step 1: Get Your Apple Developer Team ID

1. Log in to [Apple Developer Portal](https://developer.apple.com/account)
2. Your Team ID is displayed in the top-right corner (e.g., `ABC123DEF4`)
3. Or check in Xcode: Preferences → Accounts → Select your team → Team ID

### Step 2: Update DeepLinkController

Edit `app/Http/Controllers/DeepLinkController.php` and replace `YOUR_TEAM_ID` with your actual Team ID:

```php
$teamId = 'ABC123DEF4'; // Your actual Team ID
```

### Step 3: Verify the Configuration

1. Deploy the changes to your server
2. Visit `https://chat.gekychat.com/.well-known/apple-app-site-association` in a browser
3. You should see a JSON response with your app ID and paths

### Step 4: Test on iOS

1. Install the app on an iOS device
2. Click a group invite link (e.g., `https://chat.gekychat.com/groups/join/ABC123`)
3. The app should open instead of Safari

**Note:** iOS caches the association file. If you make changes, you may need to wait a few hours or reinstall the app.

## Troubleshooting

### Android: Links still open in browser

1. Verify the SHA256 fingerprint is correct
2. Ensure the package name matches exactly (`com.example.gekychat_mobile`)
3. Check that `android:autoVerify="true"` is set in AndroidManifest.xml (already configured)
4. Try uninstalling and reinstalling the app
5. Use `adb shell pm get-app-links com.example.gekychat_mobile` to check verification status

### iOS: Links still open in Safari

1. Verify the Team ID and Bundle ID are correct
2. Ensure the association file is served with `Content-Type: application/json`
3. Check that the file is accessible without authentication
4. Verify Associated Domains are configured in Xcode (already configured in Info.plist)
5. Try deleting and reinstalling the app

## Current Configuration

- **Android Package:** `com.example.gekychat_mobile`
- **iOS Bundle ID:** `com.example.gekychatMobile`
- **Domain:** `chat.gekychat.com`
- **Supported Paths:**
  - `/g/*` - Group links
  - `/c/*` - Conversation links
  - `/chat/*` - Alternative conversation links
  - `/groups/join/*` - Group invite links
