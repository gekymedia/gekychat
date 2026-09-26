#!/usr/bin/env bash
# Shared env for Nakrotek → Netcup migration scripts.
set -euo pipefail

NETCUP="${NETCUP:-root@159.195.249.203}"
NAKROTEK="${NAKROTEK:-root@gekymedia.com}"
MIGRATION_SSH_KEY="${MIGRATION_SSH_KEY:-$HOME/.ssh/migration_ed25519}"
NETCUP_BACKUP_ROOT="${NETCUP_BACKUP_ROOT:-/root/pre-hestia-backup}"
HESTIA_MIG_USER="${HESTIA_MIG_USER:-gekymedia}"
GEKYCHAT_APP_PATH="${GEKYCHAT_APP_PATH:-/var/www/chat.gekychat.com}"

# Prefer dedicated Nakrotek key when present
NAKROTEK_SSH_KEY="${NAKROTEK_SSH_KEY:-$HOME/.ssh/nakrotek_ed25519}"

SSH_OPTS=(-o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=30)
SSH_OPTS_N=("${SSH_OPTS[@]}")
SSH_OPTS_K=("${SSH_OPTS[@]}")
if [[ -f "$MIGRATION_SSH_KEY" ]]; then
  SSH_OPTS_N+=(-i "$MIGRATION_SSH_KEY")
  SSH_OPTS_K+=(-i "$MIGRATION_SSH_KEY")
fi
if [[ -f "$NAKROTEK_SSH_KEY" ]]; then
  SSH_OPTS_K=(-o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=30 -i "$NAKROTEK_SSH_KEY")
fi

ssh_n()  { ssh "${SSH_OPTS_N[@]}" "$NETCUP" "$@"; }
ssh_k()  { ssh "${SSH_OPTS_K[@]}" "$NAKROTEK" "$@"; }
scp_n()  { scp "${SSH_OPTS_N[@]}" "$@"; }
scp_k()  { scp "${SSH_OPTS_K[@]}" "$@"; }

log() { printf '[%s] %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*"; }
die() { log "ERROR: $*"; exit 1; }

require_access() {
  log "Checking SSH → netcup ($NETCUP)"
  ssh_n 'hostname; test -d '"$GEKYCHAT_APP_PATH"' && echo gekychat_ok' \
    || die "Cannot SSH to netcup or GekyChat path missing"
  log "Checking SSH → Nakrotek ($NAKROTEK)"
  ssh_k 'hostname; test -d /home/gekymedia/web && echo hestia_ok' \
    || die "Cannot SSH to Nakrotek"
}

# Domains already primary on netcup — never restore as live from Nakrotek.
SKIP_DOMAINS_REGEX='^(gekychat\.com|chat\.gekychat\.com|api\.gekychat\.com|web\.gekychat\.com|live\.gekychat\.com)$'

batch_domains() {
  case "$1" in
    A)
      cat <<'EOF'
gekymedia.com
cug.prioritysolutionsagency.com
prioritysolutionsagency.com
accommodations.prioritysolutionsagency.com
agribusiness.prioritysolutionsagency.com
angutech.prioritysolutionsagency.com
bank.prioritysolutionsagency.com
EOF
      ;;
    B)
      cat <<'EOF'
catholicuniversityofghana.com
schoolsgh.com
admin.schoolsgh.com
bkec.schoolsgh.com
brillianttest.schoolsgh.com
st-marys.schoolsgh.com
st-theresas.schoolsgh.com
studyland.schoolsgh.com
testing.schoolsgh.com
EOF
      ;;
    C)
      cat <<'EOF'
patriksolutions.com
ai.patriksolutions.com
audit.patriksolutions.com
blacktask.gekymedia.com
barffoods.com
hopebridgecs.com
hopespringfoundation.gekymedia.com
fabamall.gekymedia.com
fywoodworks.gekymedia.com
agyei-hardware.gekymedia.com
churchgh.gekymedia.com
ikagyei.gekymedia.com
scp.gekymedia.com
streamvault.gekymedia.com
streetversity.gekymedia.com
streetversity.org
stualumni.gekymedia.com
stuirmts.gekymedia.com
stukeylog.gekymedia.com
stuptm.gekymedia.com
EOF
      ;;
    D)
      cat <<'EOF'
cp.gekymedia.com
EOF
      ;;
    *)
      die "Unknown batch: $1 (use A|B|C|D)"
      ;;
  esac
}
