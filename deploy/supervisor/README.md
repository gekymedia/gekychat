# Supervisor setup for GekyChat (netcup)

App path: `/var/www/chat.gekychat.com`  
Process user: `gekychat`

## One-time setup on the server

1. Install Supervisor (already done on netcup):

   ```bash
   apt-get install -y supervisor
   ```

2. Install configs:

   ```bash
   cp /var/www/chat.gekychat.com/deploy/supervisor/gekychat-worker.conf /etc/supervisor/conf.d/
   cp /var/www/chat.gekychat.com/deploy/supervisor/gekychat-reverb.conf /etc/supervisor/conf.d/
   ```

   Or use the netcup-ready copies if present (`*.netcup.conf`). Paths inside the conf files must be `/var/www/chat.gekychat.com` and `user=gekychat`.

3. Ensure logs are writable:

   ```bash
   mkdir -p /var/www/chat.gekychat.com/storage/logs
   chown -R gekychat:gekychat /var/www/chat.gekychat.com/storage
   ```

4. Load and start:

   ```bash
   supervisorctl reread
   supervisorctl update
   supervisorctl start gekychat-worker:*
   supervisorctl start gekychat-reverb
   ```

## After each deploy

`deploy.ps1` / `deploy.sh` run `php artisan queue:restart`. Supervisor restarts workers on the new code.

## Useful commands

```bash
supervisorctl status
supervisorctl restart gekychat-worker:*
supervisorctl restart gekychat-reverb
tail -f /var/www/chat.gekychat.com/storage/logs/worker.log
tail -f /var/www/chat.gekychat.com/storage/logs/reverb.log
```
