# Post-migration deploy paths

GekyChat application root stays:

```
/var/www/chat.gekychat.com
```

Hestia domains `chat.gekychat.com` / `api.gekychat.com` / `web.gekychat.com` /
`gekychat.com` use `public_html` → symlink to that tree’s `public/`.

| Concern | Path / value |
|---------|----------------|
| SSH | `root@159.195.249.203` |
| Deploy scripts | `deploy.ps1`, `deploy.sh` (unchanged app path) |
| Supervisor | `/etc/supervisor/conf.d/gekychat-*.conf` |
| LiveKit | `/opt/livekit` |
| Monitor | `https://monitor.gekychat.com` → Grafana |
| Sites panel user | `gekymedia` (migrated Nakrotek sites) |
| Chat panel user | `gekychat` |
| Hestia UI | `https://cp.gekychat.com:8083` (or `:8083` on server IP) |

Do **not** deploy GekyChat into `/home/gekychat/web/...` as the git root;
keep using `/var/www/chat.gekychat.com` so existing deploy automation works.
