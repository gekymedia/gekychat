#!/usr/bin/env bash
# Snapshot GekyChat stack on netcup BEFORE installing Hestia.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=_common.sh
source "$SCRIPT_DIR/_common.sh"

require_access

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
BACKUP="${NETCUP_BACKUP_ROOT}/${STAMP}"

log "Creating pre-Hestia backup at $BACKUP on netcup"
ssh_n "set -euo pipefail
BACKUP='$BACKUP'
mkdir -p \"\$BACKUP\"/{nginx,supervisor,livekit,systemd,apt,ssl,mysql}
# App tree (exclude bulky caches / node_modules / vendor — keep .env + code + storage)
rsync -a --info=stats2 \
  --exclude='node_modules' --exclude='vendor' --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/*' --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' --exclude='.git' \
  $GEKYCHAT_APP_PATH/ \"\$BACKUP/chat.gekychat.com/\" || true
# Full .env always
cp -a $GEKYCHAT_APP_PATH/.env \"\$BACKUP/gekychat.env\" 2>/dev/null || true
# Nginx
cp -a /etc/nginx/sites-enabled \"\$BACKUP/nginx/\" 2>/dev/null || true
cp -a /etc/nginx/sites-available \"\$BACKUP/nginx/\" 2>/dev/null || true
cp -a /etc/nginx/nginx.conf \"\$BACKUP/nginx/\" 2>/dev/null || true
cp -a /etc/nginx/conf.d \"\$BACKUP/nginx/\" 2>/dev/null || true
# Supervisor
cp -a /etc/supervisor/conf.d \"\$BACKUP/supervisor/\" 2>/dev/null || true
# LiveKit
cp -a /opt/livekit \"\$BACKUP/livekit/\" 2>/dev/null || true
# SSL / letsencrypt (if any)
cp -a /etc/letsencrypt \"\$BACKUP/ssl/\" 2>/dev/null || true
# Package state
dpkg --get-selections > \"\$BACKUP/apt/dpkg-selections.txt\"
uname -a > \"\$BACKUP/uname.txt\"
php -v > \"\$BACKUP/php-version.txt\" 2>&1 || true
nginx -v > \"\$BACKUP/nginx-version.txt\" 2>&1 || true
# MySQL/MariaDB dump for GekyChat DB (best-effort from .env)
if [[ -f $GEKYCHAT_APP_PATH/.env ]]; then
  DB_DATABASE=\$(grep -E '^DB_DATABASE=' $GEKYCHAT_APP_PATH/.env | cut -d= -f2- | tr -d '\"' | tr -d \"'\")
  DB_USERNAME=\$(grep -E '^DB_USERNAME=' $GEKYCHAT_APP_PATH/.env | cut -d= -f2- | tr -d '\"' | tr -d \"'\")
  DB_PASSWORD=\$(grep -E '^DB_PASSWORD=' $GEKYCHAT_APP_PATH/.env | cut -d= -f2- | tr -d '\"' | tr -d \"'\")
  DB_HOST=\$(grep -E '^DB_HOST=' $GEKYCHAT_APP_PATH/.env | cut -d= -f2- | tr -d '\"' | tr -d \"'\")
  DB_HOST=\${DB_HOST:-127.0.0.1}
  if [[ -n \"\${DB_DATABASE:-}\" ]]; then
    mysqldump -h \"\$DB_HOST\" -u \"\$DB_USERNAME\" -p\"\$DB_PASSWORD\" \
      --single-transaction --routines --triggers \"\$DB_DATABASE\" \
      | gzip -c > \"\$BACKUP/mysql/\${DB_DATABASE}.sql.gz\" \
      && echo \"dumped \$DB_DATABASE\" || echo 'mysqldump failed (non-fatal)'
  fi
fi
# Manifest
du -sh \"\$BACKUP\"/* > \"\$BACKUP/MANIFEST.txt\" 2>/dev/null || true
ln -sfn \"\$BACKUP\" '${NETCUP_BACKUP_ROOT}/latest'
echo BACKUP_OK=\"\$BACKUP\"
"

# Pull nginx snapshots locally for the repo (non-secret configs)
LOCAL_SNAP="$SCRIPT_DIR/../nginx-snapshots/${STAMP}"
mkdir -p "$LOCAL_SNAP"
scp_n -r "${NETCUP}:$BACKUP/nginx" "$LOCAL_SNAP/" 2>/dev/null || log "Could not scp nginx snapshot (ok if empty)"

log "Wave 0 backup complete: $BACKUP"
