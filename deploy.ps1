# GekyChat Production Deployment Script (PowerShell)
# Server: gekymedia.com
# Path: /home/gekymedia/web/chat.gekychat.com/public_html

ssh root@gekymedia.com "cd /home/gekymedia/web/chat.gekychat.com/public_html && git pull origin main && composer install --no-dev --optimize-autoloader && php artisan migrate --force && php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan optimize && php artisan queue:restart"
