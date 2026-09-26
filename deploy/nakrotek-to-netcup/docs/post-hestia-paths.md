# Post-migration deploy paths

GekyChat application root stays:

```
/var/www/chat.gekychat.com
```

Served via nginx confs in `/etc/nginx/conf.d/zz-chat.gekychat.com.conf` (and live/monitor).
Hestia panel: `https://159.195.249.203:8083` (hostname `cp.gekychat.com`).

| Concern | Path / value |
|---------|----------------|
| SSH | `root@159.195.249.203` |
| Deploy scripts | `deploy.ps1`, `deploy.sh` (unchanged app path) |
| Supervisor | `/etc/supervisor/conf.d/gekychat-*.conf` (uses `/usr/bin/php8.4`) |
| LiveKit | `/opt/livekit` |
| Monitor | `https://monitor.gekychat.com` → Grafana |
| Sites panel user | `gekymedia` (migrated Nakrotek sites) |
| Pre-Hestia backup | `/root/pre-hestia-backup/latest` |
| Hestia admin password | `/root/pre-hestia-backup/latest/hestia-admin.password` |

Do **not** deploy GekyChat into `/home/gekychat/web/...` as the git root;
keep using `/var/www/chat.gekychat.com` so existing deploy automation works.

## PHP note

Hestia set CLI `disable_functions` including `pcntl_*`. Queue/Reverb need pcntl —
`/etc/php/8.4/cli/php.ini` (and 8.5) were cleared of those disables on netcup.
