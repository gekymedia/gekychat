#!/usr/bin/env bash
# Install cloud-agent migration pubkey on netcup + Nakrotek from a machine
# that already has SSH access (your PC / WSL / Git Bash).
set -euo pipefail

NETCUP="${NETCUP:-${GEKYCHAT_SSH_HOST:-root@159.195.249.203}}"
NAKROTEK="${NAKROTEK:-${NAKROTEK_SSH_HOST:-root@gekymedia.com}}"
PUBKEY="${PUBKEY:-ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIIwMtzw22+ZiboOS7fkXEyBM/NhJ+WmBT9TPR98K4ndh cursor-cloud-nakrotek-netcup-migration}"

install_one() {
  local host="$1"
  echo "→ $host"
  ssh "$host" bash -s <<EOF
set -euo pipefail
KEY='$PUBKEY'
mkdir -p /root/.ssh && chmod 700 /root/.ssh
touch /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys
if grep -Fq 'cursor-cloud-nakrotek-netcup-migration' /root/.ssh/authorized_keys \\
   || grep -Fq 'AAAAC3NzaC1lZDI1NTE5AAAAIIwMtzw22+ZiboOS7fkXEyBM/NhJ+WmBT9TPR98K4ndh' /root/.ssh/authorized_keys; then
  echo ALREADY_PRESENT
else
  printf '%s\n' "\$KEY" >> /root/.ssh/authorized_keys
  echo INSTALLED
fi
EOF
}

echo "Installing cloud-agent migration pubkey..."
install_one "$NETCUP"
install_one "$NAKROTEK"
echo "Done. Cloud agent can retry Wave 0."
