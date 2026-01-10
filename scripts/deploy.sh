#!/bin/bash

# GekyChat Deployment Script
# This script should be run on the server after pulling the latest changes
# 
# To run from your PC:
# ssh root@gekymedia.com "bash -s" < scripts/deploy.sh
# OR
# ssh root@gekymedia.com 'cd /path/to/project && bash scripts/deploy.sh'

set -e  # Exit on any error

echo "🚀 Starting GekyChat deployment..."

# Navigate to project directory (adjust path as needed)
# Try common paths for the project
cd /home/gekymedia/web/chat.gekychat.com/public_html || \
cd /var/www/gekychat || \
cd /var/www/html/gekychat || \
cd ~/gekychat || {
    echo "❌ Error: Could not find project directory"
    echo "Please specify the correct path in this script"
    exit 1
}

echo "📍 Current directory: $(pwd)"

# Pull latest changes (already handled by deploy_from_pc.ps1 if running from PC)
# If running this script directly on the server, uncomment the following:
# echo "📥 Pulling latest changes..."
# git stash || true
# git clean -fd || true
# rm -f DEPLOY.md || true
# git pull origin main || (git fetch origin && git reset --hard origin/main)

# Install/update Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# Run database migrations
echo "🗄️ Running database migrations..."
# Use --force to skip confirmation, but handle errors gracefully
# Some migrations may fail if tables already exist (that's okay)
php artisan migrate --force || {
    echo "⚠️ Some migrations failed (may be due to existing tables)"
    echo "Checking migration status..."
    php artisan migrate:status
    echo ""
    echo "If tables already exist, you may need to manually mark migrations as run"
    echo "or update the migrations to check for existing tables first"
}

# Clear and cache configuration
echo "🧹 Clearing and caching configuration..."
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

# Clear application cache
echo "🗑️ Clearing application cache..."
php artisan cache:clear

# Optimize application
echo "⚡ Optimizing application..."
php artisan optimize

# Set proper permissions
echo "🔐 Setting permissions..."
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || chown -R $USER:$USER storage bootstrap/cache

# Reload PHP-FPM (if applicable)
if command -v systemctl &> /dev/null; then
    echo "🔄 Reloading PHP-FPM..."
    sudo systemctl reload php8.2-fpm || sudo systemctl reload php8.1-fpm || sudo systemctl reload php-fpm || echo "⚠️ Could not reload PHP-FPM"
fi

echo "✅ Deployment completed successfully!"
echo ""
echo "📋 Post-deployment checklist:"
echo "  - [ ] Verify API routes are accessible: /api/v1/auth/phone"
echo "  - [ ] Check admin panel: /admin/upload-settings"
echo "  - [ ] Test video upload limits"
echo "  - [ ] Verify database migrations ran successfully"
echo ""
