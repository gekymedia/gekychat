#!/usr/bin/env bash
# Verify SSH to netcup + Nakrotek and print inventory snapshots.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=_common.sh
source "$SCRIPT_DIR/_common.sh"

require_access

log "=== Netcup inventory ==="
ssh_n 'set -e
echo "hostname=$(hostname)"
echo "disk=$(df -h / | awk "NR==2{print \$4\" free of \"\$2}")"
echo "hestia=$(command -v v-list-users >/dev/null && echo installed || echo absent)"
echo "nginx=$(nginx -v 2>&1 || true)"
echo "php=$(php -v 2>/dev/null | head -1 || true)"
ls -la /var/www/chat.gekychat.com 2>/dev/null | head -3 || true
ls /etc/nginx/sites-enabled 2>/dev/null || ls /etc/nginx/conf.d 2>/dev/null || true
test -d /opt/livekit && echo livekit_dir=ok || echo livekit_dir=missing
docker ps --format "{{.Names}}" 2>/dev/null | head -20 || true
'

log "=== Nakrotek inventory ==="
ssh_k 'set -e
echo "hostname=$(hostname)"
echo "disk=$(df -h / | awk "NR==2{print \$4\" free of \"\$2\" (\"\$5\" used)\"}")"
v-list-users plain 2>/dev/null || true
echo "--- web domains (gekymedia) ---"
ls /home/gekymedia/web 2>/dev/null | head -80
echo "--- mail domains ---"
ls /home/gekymedia/mail 2>/dev/null || true
echo "--- db count ---"
v-list-databases gekymedia plain 2>/dev/null | wc -l || true
du -sh /home/gekymedia/web/* 2>/dev/null | sort -hr | head -20
'

log "Access OK. Ready for Wave 0."
