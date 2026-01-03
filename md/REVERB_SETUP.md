# Laravel Reverb WebSocket Setup Guide

## Problem
WebSocket connections are failing with errors like:
```
WebSocket connection to 'wss://chat.gekychat.com/app/...' failed
```

## Root Cause
For production with HTTPS, Reverb requires:
1. **Reverb server running** on an internal port (e.g., 8080 or 6001)
2. **Nginx reverse proxy** to forward WebSocket connections from port 443 to the Reverb server

## Solution

### 1. Environment Variables (.env)

For production with HTTPS:
```env
REVERB_APP_ID=your_app_id
REVERB_APP_KEY=your_app_key
REVERB_APP_SECRET=your_app_secret
REVERB_HOST=chat.gekychat.com
REVERB_PORT=8080                    # Internal port where Reverb server runs
REVERB_SCHEME=https
REVERB_SERVER_HOST=0.0.0.0          # Listen on all interfaces
REVERB_SERVER_PORT=8080             # Port where Reverb server process runs

# Frontend (Vite) environment variables
VITE_REVERB_APP_KEY=your_app_key
VITE_REVERB_HOST=chat.gekychat.com
VITE_REVERB_PORT=443                # Client connects to 443 (nginx proxies to 8080)
VITE_REVERB_SCHEME=https
```

### 2. Nginx Configuration

Add this to your Nginx server block to proxy WebSocket connections:

```nginx
server {
    listen 443 ssl http2;
    server_name chat.gekychat.com;

    # SSL configuration (your existing SSL config)
    # ...

    # WebSocket proxy for Reverb
    location /app/ {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_pass http://127.0.0.1:8080;
        proxy_read_timeout 60s;
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
    }

    # Your existing Laravel application location block
    location / {
        # ... your existing config
    }
}
```

**Key points:**
- `/app/` path is where Reverb WebSocket connections are made
- `proxy_pass` forwards to `http://127.0.0.1:8080` (where Reverb server runs)
- `Upgrade` and `Connection` headers are required for WebSocket
- Adjust the port (8080) to match your `REVERB_SERVER_PORT`

### 3. Start Reverb Server

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Or run it as a service (recommended for production):

**Using Supervisor:**
Create `/etc/supervisor/conf.d/reverb.conf`:

```ini
[program:reverb]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/gekychat/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/gekychat/storage/logs/reverb.log
stopwaitsecs=3600
```

Then:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb:*
```

### 4. Verify Configuration

1. **Check Reverb server is running:**
   ```bash
   sudo supervisorctl status reverb:*
   # OR
   ps aux | grep reverb
   ```

2. **Test WebSocket connection:**
   ```bash
   # Test if port 8080 is listening
   sudo netstat -tlnp | grep 8080
   # OR
   sudo ss -tlnp | grep 8080
   ```

3. **Check Nginx configuration:**
   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```

4. **Test from browser console:**
   Open browser DevTools → Network tab → WS filter
   You should see a successful WebSocket connection to `wss://chat.gekychat.com/app/...`

### 5. Common Issues

#### Issue: Connection refused
- **Cause:** Reverb server not running
- **Fix:** Start Reverb server or check supervisor status

#### Issue: 404 Not Found
- **Cause:** Nginx not proxying `/app/` correctly
- **Fix:** Verify nginx config and reload nginx

#### Issue: Connection timeout
- **Cause:** Firewall blocking port 8080 or nginx misconfiguration
- **Fix:** Check firewall rules and nginx proxy_pass URL

#### Issue: SSL/TLS errors
- **Cause:** SSL certificate issues or mixed HTTP/HTTPS
- **Fix:** Ensure SSL is properly configured and `REVERB_SCHEME=https`

### 6. Frontend Configuration (Already Fixed)

The frontend configuration in `resources/js/app.js` has been updated to:
- Use port 443 for WSS (HTTPS)
- Use port 8080 for WS (HTTP)
- Properly handle `forceTLS` flag
- Use correct transport protocols

### 7. Testing

After setup, check browser console for:
- ✅ `🔗 Reverb connected` message
- ✅ No WebSocket connection errors
- ✅ Real-time features working (chat messages, typing indicators, etc.)

## Alternative: Use Pusher Instead

If Reverb continues to be problematic, consider using Pusher (free tier available):
- More reliable for production
- No server setup required
- Better documentation and support
- Free tier: 100 connections, 200k messages/day

See `SETUP_INSTRUCTIONS.md` for Pusher setup.
