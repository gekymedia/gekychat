#!/bin/bash

# GekyChat Deployment Script
# This script should be run on the server after pulling the latest changes

set -e  # Exit on any error

echo "🚀 Starting GekyChat deployment..."

# Navigate to project directory (adjust path as needed)
cd /home/gekymedia/web/chat.gekychat.com/public_html || cd /var/www/gekychat || {
    echo "❌ Error: Could not find project directory"
    exit 1
}

# Pull latest changes (if not already done)
# Uncomment if you want to pull automatically
# echo "📥 Pulling latest changes..."
# git pull origin main

# Install/update Composer dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

# Run database migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

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
