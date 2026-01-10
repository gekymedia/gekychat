# 🚀 Deployment Instructions

## Quick Deploy from Your PC

### Windows (PowerShell)
```powershell
cd d:\projects\gekychat
pwsh scripts\deploy_from_pc.ps1
```

Or manually:
```powershell
ssh root@gekymedia.com "cd /home/gekymedia/web/chat.gekychat.com/public_html && git pull origin main && bash scripts/deploy.sh"
```

### Linux/Mac (Bash)
```bash
cd ~/projects/gekychat
bash scripts/deploy_from_pc.sh
```

Or manually:
```bash
ssh root@gekymedia.com "cd /home/gekymedia/web/chat.gekychat.com/public_html && git pull origin main && bash scripts/deploy.sh"
```

## What the Deployment Script Does

1. ✅ Connects to server: `ssh root@gekymedia.com`
2. ✅ Navigates to project: `/home/gekymedia/web/chat.gekychat.com/public_html`
3. ✅ Pulls latest changes: `git pull origin main`
4. ✅ Installs dependencies: `composer install --no-dev --optimize-autoloader`
5. ✅ Runs migrations: `php artisan migrate --force`
6. ✅ Clears and caches config: `php artisan config:cache`, `route:cache`, `view:cache`
7. ✅ Optimizes application: `php artisan optimize`
8. ✅ Sets permissions: `chmod` and `chown` for storage/cache
9. ✅ Reloads PHP-FPM (if applicable)

## Post-Deployment Checklist

- [ ] Verify API routes are accessible: `POST /api/v1/auth/phone`
- [ ] Check admin panel: `/admin/upload-settings`
- [ ] Test video upload limits (World Feed, Status, Chat)
- [ ] Verify database migrations ran successfully (check `upload_settings` and `user_upload_limits` tables)
- [ ] Test login flow on mobile/desktop apps

## Manual Deployment (on server)

If you need to deploy manually on the server:

```bash
# SSH into server
ssh root@gekymedia.com

# Navigate to project
cd /home/gekymedia/web/chat.gekychat.com/public_html

# Pull latest changes
git pull origin main

# Run deployment script
bash scripts/deploy.sh
```

## Troubleshooting

### SSH Connection Issues
- Make sure you have SSH key set up or password authentication enabled
- Test connection: `ssh root@gekymedia.com`

### Permission Issues
- Check file permissions: `ls -la storage bootstrap/cache`
- Fix permissions: `chmod -R 755 storage bootstrap/cache`

### Migration Issues
- Check migration status: `php artisan migrate:status`
- Rollback if needed: `php artisan migrate:rollback`

### Cache Issues
- Clear all caches: `php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan view:clear`
