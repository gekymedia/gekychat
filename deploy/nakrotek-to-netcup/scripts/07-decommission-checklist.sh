#!/usr/bin/env bash
# Decommission checklist — verify DNS, document panel paths, retire Nakrotek.
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=_common.sh
source "$SCRIPT_DIR/_common.sh"

NETCUP_IP="${NETCUP_IP:-159.195.249.203}"
DOCS_OUT="${SCRIPT_DIR}/../docs/decommission-$(date -u +%Y%m%d).md"

require_access || true

log "Building decommission report → $DOCS_OUT"

{
  echo "# Nakrotek decommission checklist"
  echo
  echo "Generated: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "Target IP: $NETCUP_IP"
  echo

  echo "## DNS A/AAAA (should point to netcup)"
  echo
  for batch in A B C D; do
    echo "### Batch $batch"
    while read -r d; do
      [[ -z "$d" ]] && continue
      a="$(dig +short A "$d" 2>/dev/null | head -3 | tr '\n' ' ')"
      aaaa="$(dig +short AAAA "$d" 2>/dev/null | head -2 | tr '\n' ' ')"
      mx="$(dig +short MX "$d" 2>/dev/null | head -3 | tr '\n' ' ')"
      ok="FAIL"
      echo "$a" | grep -q "$NETCUP_IP" && ok="OK"
      echo "- \`$d\` A=[$a] AAAA=[$aaaa] MX=[$mx] → **$ok**"
    done < <(batch_domains "$batch")
    echo
  done

  echo "## GekyChat (must stay healthy)"
  for d in chat.gekychat.com api.gekychat.com live.gekychat.com monitor.gekychat.com; do
    a="$(dig +short A "$d" 2>/dev/null | head -2 | tr '\n' ' ')"
    echo "- \`$d\` A=[$a]"
  done
  echo

  echo "## Panel / deploy paths (post-Hestia)"
  echo
  echo "| Item | Value |"
  echo "|------|-------|"
  echo "| Hestia panel | \`https://cp.gekychat.com:8083\` (or netcup IP:8083) |"
  echo "| GekyChat app | \`$GEKYCHAT_APP_PATH\` (unchanged) |"
  echo "| Hestia user (sites) | \`$HESTIA_MIG_USER\` |"
  echo "| Hestia user (chat) | \`gekychat\` |"
  echo "| LiveKit | \`/opt/livekit\` |"
  echo "| SSH | \`root@$NETCUP_IP\` |"
  echo

  echo "## Nakrotek retire steps"
  echo
  echo "1. Confirm all A/AAAA above are OK and HTTPS works."
  echo "2. Confirm MX on netcup for 7–14 days with no delivery gaps."
  echo "3. Set Nakrotek read-only (stop nginx/exim or firewall inbound 80/443/25)."
  echo "4. Final offsite backup of Nakrotek \`/home\` + MySQL if desired."
  echo "5. Cancel Nakrotek hosting / power off."
  echo "6. Update deploy docs: \`deploy.ps1\` / \`deploy.sh\` paths remain \`/var/www/chat.gekychat.com\`."
  echo
} | tee "$DOCS_OUT"

log "Report written. Fix any FAIL DNS rows before powering off Nakrotek."
