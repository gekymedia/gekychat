# Nakrotek → Netcup migration (Hestia + sites first)

Automated helpers for the approved plan:

1. **Wave 0** — Backup GekyChat on netcup, install Hestia, rebind GekyChat / LiveKit / monitor  
2. **Wave 1** — Free Nakrotek disk; migrate websites + MySQL (mail MX stays on Nakrotek)  
3. **Wave 2** — Mail / MX cutover  
4. **Decommission** — DNS verify; retire Nakrotek; update deploy docs  

## Hosts

| Role | SSH | Notes |
|------|-----|--------|
| Netcup (destination) | `root@159.195.249.203` | GekyChat already live; Hestia to be installed |
| Nakrotek (source) | `root@gekymedia.com` | Hestia; disk often full — free space before backups |

## Auth

**Cloud agent has no SSH yet.** From your PC (where `deploy.ps1` already works), install the agent pubkey once:

```powershell
cd D:\projects\gekychat   # or your repo path
git fetch origin cursor/nakrotek-netcup-migrate-9d01
git checkout cursor/nakrotek-netcup-migrate-9d01
.\deploy\nakrotek-to-netcup\scripts\authorize-cloud-agent.ps1
```

Or one-liners with your existing SSH:

```powershell
$key = "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIIwMtzw22+ZiboOS7fkXEyBM/NhJ+WmBT9TPR98K4ndh cursor-cloud-nakrotek-netcup-migration"
ssh root@159.195.249.203 "mkdir -p /root/.ssh && chmod 700 /root/.ssh && echo '$key' >> /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys"
ssh root@gekymedia.com   "mkdir -p /root/.ssh && chmod 700 /root/.ssh && echo '$key' >> /root/.ssh/authorized_keys && chmod 600 /root/.ssh/authorized_keys"
```

Put a key that can reach **both** hosts as `~/.ssh/migration_ed25519` (or set `MIGRATION_SSH_KEY`).
Optional Nakrotek-only key: `~/.ssh/nakrotek_ed25519`.

From Cursor secrets:

```bash
# Expects NETCUP_SSH_PRIVATE_KEY (and optional NAKROTEK_SSH_PRIVATE_KEY)
./scripts/install-ssh-from-secrets.sh
```

```bash
export NETCUP=root@159.195.249.203
export NAKROTEK=root@gekymedia.com
export MIGRATION_SSH_KEY=~/.ssh/migration_ed25519
```

## Run order

```bash
cd deploy/nakrotek-to-netcup/scripts
./00-check-access.sh
./01-wave0-backup-netcup.sh          # snapshot GekyChat + nginx + LiveKit configs
./02-wave0-install-hestia-netcup.sh  # install Hestia (short GekyChat HTTP window)
./03-wave0-rebind-gekychat.sh        # recreate GekyChat domains under Hestia
./04-wave1-free-nakrotek-disk.sh
./05-wave1-migrate-batch.sh A        # gekymedia + cug.psa + PSA family
./05-wave1-migrate-batch.sh B        # CUG + SchoolsGH
./05-wave1-migrate-batch.sh C        # remaining clients
./05-wave1-migrate-batch.sh D        # rickynkansah leftovers
# DNS A/AAAA → 159.195.249.203 per batch after hosts-file smoke tests (MX unchanged)
./06-wave2-mail.sh                   # after web stable
./07-decommission-checklist.sh
```

GekyChat domains on Nakrotek are **not** restored as live sites on netcup (already primary on netcup).
