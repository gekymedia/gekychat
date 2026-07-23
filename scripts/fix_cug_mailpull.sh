#!/bin/bash
set -e
APP=/home/gekymedia/web/catholicuniversityofghana.com/public_html
CRON_LINE="* * * * * cd $APP && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"

if crontab -u gekymedia -l 2>/dev/null | grep -Fq "catholicuniversityofghana.com/public_html"; then
  echo "CRON_EXISTS"
else
  (crontab -u gekymedia -l 2>/dev/null; echo "$CRON_LINE") | crontab -u gekymedia -
  echo "CRON_ADDED"
fi

echo "=== crontab ==="
crontab -u gekymedia -l | grep -E "catholic|gekychat|schedule" || true

echo "=== mail:pull ==="
cd "$APP"
php artisan mail:pull --since="30 days" --limit=50 2>&1 | tee storage/logs/mail-pull.log | tail -100
