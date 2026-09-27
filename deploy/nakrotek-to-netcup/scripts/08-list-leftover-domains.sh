#!/usr/bin/env bash
# List Nakrotek web domains not covered by batches A–D (catch leftovers).
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=_common.sh
source "$SCRIPT_DIR/_common.sh"

require_access

tmp_planned="$(mktemp)"
tmp_live="$(mktemp)"
trap 'rm -f "$tmp_planned" "$tmp_live"' EXIT

all_planned_domains | sort -u > "$tmp_planned"
ssh_k "ls /home/${HESTIA_MIG_USER}/web" | sort -u > "$tmp_live"

log "Planned (A–D): $(wc -l < "$tmp_planned")"
log "Live on Nakrotek: $(wc -l < "$tmp_live")"
log "--- Leftovers (live minus planned / skip) ---"
comm -23 "$tmp_live" "$tmp_planned" | while read -r d; do
  if [[ "$d" =~ $SKIP_DOMAINS_REGEX ]]; then
    echo "SKIP $d"
  else
    echo "LEFTOVER $d"
  fi
done
