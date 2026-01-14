# Installing PHP IMAP Extension on Windows with Laravel Herd

## Problem
Laravel Herd on Windows uses PHP 8.4, but the IMAP extension DLL is not included by default and pre-compiled DLLs for PHP 8.4 are not readily available.

## Solutions

### Option 1: Download PHP 8.4 IMAP DLL (Recommended if available)

1. **Check PECL Windows Builds:**
   - Visit: https://windows.php.net/downloads/pecl/releases/imap/
   - Look for PHP 8.4 compatible version
   - Download the `php_imap.dll` file for:
     - Thread Safety: NTS (Non-Thread Safe)
     - Architecture: x64
     - PHP Version: 8.4

2. **Place the DLL:**
   ```
   Copy the downloaded php_imap.dll to:
   C:\Users\Admin\.config\herd\bin\php84\ext\php_imap.dll
   ```

3. **Enable in php.ini:**
   - Open: `C:\Users\Admin\.config\herd\bin\php84\php.ini`
   - Find the extensions section (around line 924)
   - Add: `extension=imap`
   - Save the file

4. **Restart Herd:**
   ```bash
   herd restart
   ```

5. **Verify:**
   ```bash
   herd php -m | findstr /i imap
   ```

### Option 2: Use XAMPP PHP for Local Development

If you have XAMPP installed with PHP 8.2 (which has IMAP), you can:

1. Use XAMPP's PHP for running artisan commands:
   ```bash
   C:\xampp\php\php.exe artisan email:fetch
   ```

2. Or configure your IDE to use XAMPP's PHP for this project

### Option 3: Compile IMAP Extension (Advanced)

If you need IMAP for PHP 8.4, you may need to compile it from source:

1. **Requirements:**
   - Visual Studio 2022
   - PHP 8.4 source code
   - IMAP library (libc-client)

2. **This is complex and not recommended unless necessary**

### Option 4: Use Docker/VM (Alternative)

Run the application in a Docker container or Linux VM where IMAP extension is easier to install.

## Current Status

- ✅ IMAP extension line added to php.ini (commented out)
- ❌ php_imap.dll for PHP 8.4 not available
- ✅ XAMPP has PHP 8.2 with IMAP (can be used for CLI commands)

## Quick Test

After installing, test with:
```bash
herd php -r "echo extension_loaded('imap') ? 'IMAP loaded' : 'IMAP not loaded';"
```
