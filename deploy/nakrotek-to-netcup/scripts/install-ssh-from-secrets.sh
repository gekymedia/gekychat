#!/usr/bin/env bash
# Install SSH keys from Cursor secrets / env into ~/.ssh/migration_ed25519
set -euo pipefail

KEY_PATH="${MIGRATION_SSH_KEY:-$HOME/.ssh/migration_ed25519}"
mkdir -p "$(dirname "$KEY_PATH")"

install_key() {
  local content="$1"
  local path="$2"
  [[ -n "$content" ]] || return 1
  # Support literal \n in env-stored keys
  printf '%s\n' "$content" | sed 's/\\n/\n/g' > "$path"
  chmod 600 "$path"
  ssh-keygen -y -f "$path" >/dev/null
  echo "Installed key → $path"
}

if [[ -n "${NETCUP_SSH_PRIVATE_KEY:-}" ]]; then
  install_key "$NETCUP_SSH_PRIVATE_KEY" "$KEY_PATH"
elif [[ -n "${SSH_PRIVATE_KEY:-}" ]]; then
  install_key "$SSH_PRIVATE_KEY" "$KEY_PATH"
elif [[ -f "$KEY_PATH" ]]; then
  echo "Using existing $KEY_PATH"
else
  echo "No NETCUP_SSH_PRIVATE_KEY / SSH_PRIVATE_KEY and no $KEY_PATH" >&2
  exit 1
fi

if [[ -n "${NAKROTEK_SSH_PRIVATE_KEY:-}" ]]; then
  install_key "$NAKROTEK_SSH_PRIVATE_KEY" "$HOME/.ssh/nakrotek_ed25519"
  echo "Nakrotek-specific key installed; export MIGRATION_SSH_KEY if same key unused"
fi

# Quick probe
ssh -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=accept-new -i "$KEY_PATH" \
  root@159.195.249.203 'echo netcup_ok' || echo "netcup probe failed"
ssh -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=accept-new -i "$KEY_PATH" \
  root@gekymedia.com 'echo nakrotek_ok' || \
ssh -o BatchMode=yes -o ConnectTimeout=15 -o StrictHostKeyChecking=accept-new -i "${HOME}/.ssh/nakrotek_ed25519" \
  root@gekymedia.com 'echo nakrotek_ok' || echo "nakrotek probe failed"
