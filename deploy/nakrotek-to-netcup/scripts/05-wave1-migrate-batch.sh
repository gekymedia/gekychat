#!/usr/bin/env bash
# Migrate one batch (A|B|C|D) of websites + DBs from Nakrotek → netcup Hestia.
# Usage: ./05-wave1-migrate-batch.sh A
# Does NOT change DNS/MX — smoke-test via Host header / /etc/hosts first.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=_common.sh
source "$SCRIPT_DIR/_common.sh"

BATCH="${1:-}"
[[ -n "$BATCH" ]] || die "Usage: $0 A|B|C|D"

require_access
ssh_n "command -v v-add-web-domain >/dev/null" || die "Hestia missing on netcup"
ssh_k "test -d /home/${HESTIA_MIG_USER}/web" || die "Source user ${HESTIA_MIG_USER} missing on Nakrotek"

DEST_USER="${HESTIA_MIG_USER}"
log "Ensuring destination Hestia user $DEST_USER on netcup"
ssh_n "set -euo pipefail
U='$DEST_USER'
if ! v-list-user \"\$U\" >/dev/null 2>&1; then
  PASS=\$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)
  v-add-user \"\$U\" \"\$PASS\" admin@gekymedia.com default
  echo \"\$PASS\" > /root/hestia-\${U}.password
  chmod 600 /root/hestia-\${U}.password
fi
"

mapfile -t DOMAINS < <(batch_domains "$BATCH")
log "Batch $BATCH domains (${#DOMAINS[@]}): ${DOMAINS[*]}"

migrate_one() {
  local domain="$1"
  if [[ "$domain" =~ $SKIP_DOMAINS_REGEX ]]; then
    log "SKIP (GekyChat primary on netcup): $domain"
    return 0
  fi

  log "=== Migrating $domain ==="

  # Ensure domain exists on source
  if ! ssh_k "test -d /home/${HESTIA_MIG_USER}/web/${domain}"; then
    log "WARN: $domain not present under /home/${HESTIA_MIG_USER}/web — skip"
    return 0
  fi

  # Create web domain on dest
  ssh_n "v-list-web-domain '$DEST_USER' '$domain' >/dev/null 2>&1 || v-add-web-domain '$DEST_USER' '$domain'"

  # Rsync public_html (+ private/cgi if present) — streaming, no local backup on Nakrotek
  log "Rsync web files for $domain"
  ssh_n "mkdir -p /home/${DEST_USER}/web/${domain}"
  # Direct host-to-host rsync via ssh from netcup pulling from Nakrotek
  # Requires netcup to have SSH access to Nakrotek OR we tunnel via this agent.
  # Agent-mediated: rsync from nakrotek → local tmp → netcup is too heavy.
  # Prefer: netcup pulls if key is installed there; else agent streams.
  if ssh_n "test -f /root/.ssh/nakrotek_migration 2>/dev/null || test -f /root/.ssh/id_ed25519"; then
    ssh_n "rsync -aH --delete --info=progress2 \
      -e 'ssh -o StrictHostKeyChecking=accept-new -i /root/.ssh/nakrotek_migration' \
      root@gekymedia.com:/home/${HESTIA_MIG_USER}/web/${domain}/public_html/ \
      /home/${DEST_USER}/web/${domain}/public_html/ \
      || rsync -aH --info=progress2 \
      -e 'ssh -o StrictHostKeyChecking=accept-new' \
      ${NAKROTEK#*@}:/home/${HESTIA_MIG_USER}/web/${domain}/public_html/ \
      /home/${DEST_USER}/web/${domain}/public_html/" \
      || true
  fi

  # Agent-mediated rsync (always works if we have keys to both)
  log "Agent-mediated rsync $domain (Nakrotek → netcup)"
  RSYNC_SSH="ssh ${SSH_OPTS[*]}"
  # Use rsync remote-to-remote via this host as pipe when possible:
  # rsync -e ssh src: path | ... simpler: two-hop with temporary exclusion of huge caches
  ssh_k "test -d /home/${HESTIA_MIG_USER}/web/${domain}/public_html"
  # Stream tar over ssh chain: nakrotek tar | ssh netcup tar x
  ssh_k "tar -C /home/${HESTIA_MIG_USER}/web/${domain} -cf - public_html \
    --exclude='public_html/node_modules' \
    --exclude='public_html/.git' \
    --exclude='public_html/storage/logs' \
    --exclude='public_html/storage/framework/cache' \
    2>/dev/null" \
    | ssh_n "tar -C /home/${DEST_USER}/web/${domain} -xf - && chown -R ${DEST_USER}:${DEST_USER} /home/${DEST_USER}/web/${domain}/public_html"

  # Detect and migrate matching DB
  # Hestia DB names often: user_dbname — list DBs and match domain keyword
  log "Looking up DB for $domain"
  local db_line
  db_line="$(ssh_k "v-list-databases ${HESTIA_MIG_USER} plain 2>/dev/null" | grep -i "$(echo "$domain" | tr '.' '_' | cut -c1-32)" | head -1 || true)"
  if [[ -z "$db_line" ]]; then
    # Also try short labels from domain
    local short
    short="$(echo "$domain" | cut -d. -f1)"
    db_line="$(ssh_k "v-list-databases ${HESTIA_MIG_USER} plain 2>/dev/null" | grep -i "$short" | head -1 || true)"
  fi

  if [[ -n "$db_line" ]]; then
    local db_name db_user
    db_name="$(echo "$db_line" | awk '{print $1}')"
    db_user="$(echo "$db_line" | awk '{print $2}')"
    log "Dumping DB $db_name (user $db_user)"
    local dump_pass
    dump_pass="$(openssl rand -hex 8)"
    ssh_k "mysqldump --single-transaction --routines --triggers '${db_name}' | gzip -c" \
      | ssh_n "mkdir -p /root/mig-sql && gunzip -c > /root/mig-sql/${db_name}.sql"

    ssh_n "set -euo pipefail
DB='${db_name}'
U='${DEST_USER}'
DBUSER='${db_user}'
PASS='${dump_pass}'
# Create DB+user if missing
if ! v-list-database \"\$U\" \"\$DB\" >/dev/null 2>&1; then
  # Hestia wants db name without user prefix sometimes — try full name
  SHORT=\${DB#\${U}_}
  v-add-database \"\$U\" \"\$SHORT\" \"\$SHORT\" \"\$PASS\" mysql 2>/dev/null \
    || v-add-database \"\$U\" \"\$DB\" \"\$DBUSER\" \"\$PASS\" mysql 2>/dev/null \
    || true
fi
# Import into whatever DB exists matching
TARGET=\$(v-list-databases \"\$U\" plain | awk -v d=\"\$DB\" '\$1==d{print \$1}')
if [[ -z \"\$TARGET\" ]]; then
  TARGET=\$(v-list-databases \"\$U\" plain | awk -v s=\"\${DB#\${U}_}\" '\$1~s{print \$1; exit}')
fi
if [[ -n \"\$TARGET\" ]]; then
  mysql \"\$TARGET\" < /root/mig-sql/${db_name}.sql && echo IMPORTED_\$TARGET
else
  # Fallback raw mysql create
  mysql -e \"CREATE DATABASE IF NOT EXISTS \\\`\$DB\\\`\"
  mysql \"\$DB\" < /root/mig-sql/${db_name}.sql && echo IMPORTED_RAW_\$DB
fi
"
  else
    log "No DB auto-match for $domain — files only (check manually)"
  fi

  # Ownership + rebuild
  ssh_n "chown -R ${DEST_USER}:${DEST_USER} /home/${DEST_USER}/web/${domain} ; v-rebuild-web-domain ${DEST_USER} ${domain} || true"

  # Local smoke via Host header
  code="$(ssh_n "curl -s -o /dev/null -w '%{http_code}' -H 'Host: ${domain}' http://127.0.0.1/" || echo err)"
  log "Smoke HTTP $domain → $code (via Host header on netcup)"
}

for d in "${DOMAINS[@]}"; do
  migrate_one "$d"
done

log "Batch $BATCH file/DB migration finished."
log "NEXT: lower TTL; test via /etc/hosts → 159.195.249.203; flip A/AAAA only (leave MX on Nakrotek)."
log "DNS cutover helper notes in ../docs/dns-cutover.md"
