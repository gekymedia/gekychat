#!/bin/bash

# GekyChat Production Deployment Script
# Server: gekymedia.com
# Path: /home/gekymedia/web/chat.gekychat.com/public_html

echo "📦 Committing and pushing local changes..."
git add .
git commit -m "Deploy: $(date +"%Y-%m-%d %H:%M:%S")" || echo "No changes to commit"
git push origin main

echo "🚀 Deploying to production..."
ssh root@gekymedia.com "cd /home/gekymedia/web/chat.gekychat.com/public_html && \
  git pull origin main && \
  composer install --no-dev --optimize-autoloader && \
  php artisan migrate --force && \
  php artisan optimize:clear && \
  php artisan config:cache && \
  php artisan route:cache && \
  php artisan view:cache && \
  php artisan optimize && \
  php artisan queue:restart"
