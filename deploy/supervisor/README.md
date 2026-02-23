# Supervisor setup for GekyChat (gekymedia.com)

## One-time setup on the server

1. **Install Supervisor** (if not already installed):

   ```bash
   sudo apt-get update
   sudo apt-get install -y supervisor
   ```

2. **Link or copy the config** into Supervisor’s conf.d:

   ```bash
   sudo ln -sf /home/gekymedia/web/chat.gekychat.com/public_html/deploy/supervisor/gekychat-worker.conf /etc/supervisor/conf.d/gekychat-worker.conf
   ```
   Or copy the file:
   ```bash
   sudo cp /home/gekymedia/web/chat.gekychat.com/public_html/deploy/supervisor/gekychat-worker.conf /etc/supervisor/conf.d/
   ```

3. **Ensure the log directory exists** and is writable by `gekymedia`:

   ```bash
   mkdir -p /home/gekymedia/web/chat.gekychat.com/public_html/storage/logs
   chown -R gekymedia:gekymedia /home/gekymedia/web/chat.gekychat.com/public_html/storage
   ```

4. **Load and start the worker**:

   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start gekychat-worker:*
   ```

## After each deploy

`deploy.ps1` runs `php artisan queue:restart`, which signals workers to finish the current job and exit. Supervisor will restart them automatically, so they run the new code.

To reload the Supervisor config after changing `gekychat-worker.conf`:

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

## Useful commands

```bash
# Status
sudo supervisorctl status gekychat-worker:*

# Restart all workers
sudo supervisorctl restart gekychat-worker:*

# Tail worker log
tail -f /home/gekymedia/web/chat.gekychat.com/public_html/storage/logs/worker.log
```

## If the web server user is not `gekymedia`

Edit `gekychat-worker.conf` and set `user=` to the correct user (e.g. `www-data`).
