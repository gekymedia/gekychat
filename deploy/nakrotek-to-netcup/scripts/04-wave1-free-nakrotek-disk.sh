#!/usr/bin/env bash
# Free disk on Nakrotek so backups / rsync staging can succeed.
# Prefers streaming rsync for large trees when free space stays tight.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=_common.sh
source "$SCRIPT_DIR/_common.sh"

require_access

log "Nakrotek disk before cleanup:"
ssh_k 'df -h / ; du -sh /backup /home/*/backup /var/log 2>/dev/null || true'

log "Cleaning safe reclaimable space on Nakrotek"
ssh_k 'set -euo pipefail
# Apt caches
apt-get clean 2>/dev/null || true
rm -rf /var/cache/apt/archives/*.deb 2>/dev/null || true

# Old rotated logs
find /var/log -type f -name "*.gz" -mtime +7 -delete 2>/dev/null || true
find /var/log -type f -name "*.1" -mtime +3 -delete 2>/dev/null || true
journalctl --vacuum-size=200M 2>/dev/null || true

# Hestia old backups (keep newest 1 per user if any)
if [[ -d /backup ]]; then
  # List size first
  du -sh /backup/* 2>/dev/null | sort -hr | head -20 || true
  # Remove backups older than 14 days
  find /backup -maxdepth 1 -type f -name "*.tar" -mtime +14 -print -delete 2>/dev/null || true
  find /backup -maxdepth 1 -type f -name "*.tar" -mtime +14 -print -delete 2>/dev/null || true
fi
# Per-user backup dirs
find /home -path "*/backup/*.tar" -mtime +7 -print -delete 2>/dev/null || true

# Temp
rm -rf /tmp/hestia* /tmp/*.sql /tmp/*.sql.gz 2>/dev/null || true
find /tmp -type f -mtime +2 -size +50M -delete 2>/dev/null || true

# Orphaned GekyChat media already primary on netcup — archive only if huge
# DO NOT delete without confirmation flag; just report size
if [[ -d /home/gekymedia/web/chat.gekychat.com ]]; then
  du -sh /home/gekymedia/web/chat.gekychat.com || true
fi
if [[ "${PURGE_NAKROTEK_GEKYCHAT_WEB:-0}" == "1" ]]; then
  echo "PURGE_NAKROTEK_GEKYCHAT_WEB=1 — removing Nakrotek chat.gekychat.com web tree"
  rm -rf /home/gekymedia/web/chat.gekychat.com/public_html/storage/app/public/* 2>/dev/null || true
fi

# Composer / npm caches for system users
rm -rf /root/.composer/cache /root/.npm/_cacache 2>/dev/null || true

df -h /
FREE_KB=$(df -Pk / | awk 'NR==2{print $4}')
echo "FREE_KB=$FREE_KB"
# Recommend strategy (<2G free → streaming only)
if [ "$FREE_KB" -lt 2097152 ]; then
  echo "STRATEGY=streaming_rsync  # <2G free — do not run v-backup-user locally"
else
  echo "STRATEGY=hestia_backup_ok_or_rsync"
fi
'

log "Disk cleanup done. Prefer STREAMING rsync for CUG (~11G) / Fabamall (~3.5G)."
log "Set PURGE_NAKROTEK_GEKYCHAT_WEB=1 to reclaim old GekyChat media on Nakrotek after verifying netcup."
