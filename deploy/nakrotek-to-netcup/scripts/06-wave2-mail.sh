#!/usr/bin/env bash
# Wave 2 — create mail on netcup Hestia, rsync maildirs, prepare MX cutover.
# Run ONLY after Wave 1 web is stable. Does not flip MX automatically.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=_common.sh
source "$SCRIPT_DIR/_common.sh"

require_access
DEST_USER="${HESTIA_MIG_USER}"

log "Inventory mail domains on Nakrotek"
ssh_k "ls /home/${HESTIA_MIG_USER}/mail; du -sh /home/${HESTIA_MIG_USER}/mail/* 2>/dev/null | sort -hr"

log "Ensuring mail domains + accounts on netcup"
# List mail domains and accounts from source, recreate on dest
ssh_k "v-list-mail-domains ${HESTIA_MIG_USER} plain 2>/dev/null" | while read -r mdom _rest; do
  [[ -z "${mdom:-}" ]] && continue
  if [[ "$mdom" =~ $SKIP_DOMAINS_REGEX ]]; then
    log "SKIP mail domain (GekyChat): $mdom"
    continue
  fi
  log "Mail domain: $mdom"
  ssh_n "v-list-mail-domain '$DEST_USER' '$mdom' >/dev/null 2>&1 || v-add-mail-domain '$DEST_USER' '$mdom'"

  ssh_k "v-list-mail-accounts ${HESTIA_MIG_USER} ${mdom} plain 2>/dev/null" | while read -r acct _r; do
    [[ -z "${acct:-}" ]] && continue
    # Random temp password — real auth is via synced maildir + later password reset / hash copy
    tmp_pass="$(openssl rand -base64 16 | tr -d '/+=' | head -c 16)"
    ssh_n "v-list-mail-account '$DEST_USER' '$mdom' '$acct' >/dev/null 2>&1 \
      || v-add-mail-account '$DEST_USER' '$mdom' '$acct' '$tmp_pass'" || true
  done
done

log "Rsync maildirs (MX still on Nakrotek — safe incremental sync)"
ssh_k "tar -C /home/${HESTIA_MIG_USER} -cf - mail 2>/dev/null" \
  | ssh_n "tar -C /home/${DEST_USER} -xf - && chown -R ${DEST_USER}:mail /home/${DEST_USER}/mail 2>/dev/null || chown -R ${DEST_USER}:${DEST_USER} /home/${DEST_USER}/mail"

# Copy DKIM keys if present so cutover can reuse
log "Copying DKIM private keys where present"
ssh_k "tar -C /home/${HESTIA_MIG_USER}/conf -cf - mail 2>/dev/null || true" \
  | ssh_n "mkdir -p /home/${DEST_USER}/conf && tar -C /home/${DEST_USER}/conf -xf - 2>/dev/null || true"

log "Wave 2 sync complete."
cat <<EOF

MX / SPF / DKIM CUTOVER (manual DNS — do after final sync):
  1. Re-run this script once more for a final delta sync during low mail traffic.
  2. For each mail domain, set:
       MX    →  mail.<domain> or netcup hostname (Hestia default)
       A     →  159.195.249.203 for mail host
       SPF   →  include netcup/Hestia sending IPs; remove Nakrotek
       DKIM  →  publish keys from: v-list-mail-domain-dkim $DEST_USER <domain>
       DMARC →  keep policy; update rua if needed
  3. Keep Nakrotek mail read-only 7–14 days, then decommission.

EOF
