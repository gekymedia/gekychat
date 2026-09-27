#!/usr/bin/env bash
# Install Hestia on netcup. Expects Wave 0 backup already done.
# Short GekyChat HTTP maintenance window — LiveKit Docker left running.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=_common.sh
source "$SCRIPT_DIR/_common.sh"

require_access

ssh_n "test -L ${NETCUP_BACKUP_ROOT}/latest -o -d ${NETCUP_BACKUP_ROOT}/latest" \
  || die "Run 01-wave0-backup-netcup.sh first (no ${NETCUP_BACKUP_ROOT}/latest)"

# Admin email / hostname for Hestia installer
HESTIA_EMAIL="${HESTIA_EMAIL:-admin@gekymedia.com}"
HESTIA_HOSTNAME="${HESTIA_HOSTNAME:-cp.gekychat.com}"
HESTIA_PORT="${HESTIA_PORT:-8083}"

log "Installing Hestia on netcup (hostname=$HESTIA_HOSTNAME port=$HESTIA_PORT)"
log "WARNING: nginx/php/mariadb will be reconfigured — GekyChat HTTP may briefly fail"

ssh_n "set -euo pipefail
export DEBIAN_FRONTEND=noninteractive
if command -v v-list-users >/dev/null 2>&1; then
  echo 'Hestia already installed'
  v-list-sys-info plain || true
  exit 0
fi

# Ensure backup exists
test -e ${NETCUP_BACKUP_ROOT}/latest || { echo 'missing backup'; exit 1; }

# Prefer official installer with noninteractive flags.
# Docs: https://hestiacp.com/docs/introduction/getting-started.html
cd /root
if [[ ! -f hst-install.sh ]]; then
  wget -q https://raw.githubusercontent.com/hestiacp/hestiacp/release/install/hst-install.sh -O hst-install.sh
  chmod +x hst-install.sh
fi

# Generate random admin password if not provided
ADMIN_PASS='${HESTIA_ADMIN_PASSWORD:-}'
if [[ -z \"\$ADMIN_PASS\" ]]; then
  ADMIN_PASS=\$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)
  echo \"\$ADMIN_PASS\" > ${NETCUP_BACKUP_ROOT}/latest/hestia-admin.password
  chmod 600 ${NETCUP_BACKUP_ROOT}/latest/hestia-admin.password
fi

# -f force, -y interactive skip; keep apache off (nginx only like Nakrotek typical)
# -m install mariadb, -w install nginx+php, -b install vsftpd optional off
# Flags differ by version — use common set:
set +e
bash hst-install.sh \
  --interactive no \
  --email '${HESTIA_EMAIL}' \
  --hostname '${HESTIA_HOSTNAME}' \
  --username admin \
  --password \"\$ADMIN_PASS\" \
  --port '${HESTIA_PORT}' \
  --lang en \
  --apache no \
  --phpfpm yes \
  --multiphp yes \
  --vsftpd no \
  --proftpd no \
  --named no \
  --mysql yes \
  --postgresql no \
  --exim yes \
  --dovecot yes \
  --clamav no \
  --spamassassin no \
  --iptables yes \
  --fail2ban yes \
  --quota no \
  --api yes \
  --with-debs no
RC=\$?
set -e
if [[ \$RC -ne 0 ]]; then
  echo \"Installer exit \$RC — check /root/hst_install.log or /var/log/hestia\"
  # Still verify partial install
  command -v v-list-users >/dev/null || exit \$RC
fi
echo HESTIA_INSTALL_OK
v-list-sys-info plain || true
"

log "Hestia install finished. Next: 03-wave0-rebind-gekychat.sh"
log "Admin password stored on netcup at ${NETCUP_BACKUP_ROOT}/latest/hestia-admin.password (if auto-generated)"
