#Requires -Version 5.1
<#
.SYNOPSIS
  From your Windows PC (where you already SSH to netcup / Nakrotek), install the
  Cursor cloud-agent migration public key on both servers so Wave 0+ can run remotely.

.EXAMPLE
  cd D:\projects\gekychat
  git fetch origin cursor/nakrotek-netcup-migrate-9d01
  git checkout cursor/nakrotek-netcup-migrate-9d01
  .\deploy\nakrotek-to-netcup\scripts\authorize-cloud-agent.ps1
#>

param(
    [string]$NetcupHost = $(if ($env:GEKYCHAT_SSH_HOST) { $env:GEKYCHAT_SSH_HOST } else { "root@159.195.249.203" }),
    [string]$NakrotekHost = $(if ($env:NAKROTEK_SSH_HOST) { $env:NAKROTEK_SSH_HOST } else { "root@gekymedia.com" }),
    [string]$PublicKey = "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIIwMtzw22+ZiboOS7fkXEyBM/NhJ+WmBT9TPR98K4ndh cursor-cloud-nakrotek-netcup-migration"
)

$ErrorActionPreference = "Stop"

function Install-CloudAgentKey {
    param(
        [Parameter(Mandatory = $true)][string]$SshTarget,
        [Parameter(Mandatory = $true)][string]$KeyLine
    )

    Write-Host "→ $SshTarget" -ForegroundColor Cyan

    # Single-quoted remote script; inject key via env-safe printf from local
    $remote = @'
set -euo pipefail
mkdir -p /root/.ssh
chmod 700 /root/.ssh
touch /root/.ssh/authorized_keys
chmod 600 /root/.ssh/authorized_keys
MARKER='cursor-cloud-nakrotek-netcup-migration'
FP='AAAAC3NzaC1lZDI1NTE5AAAAIIwMtzw22+ZiboOS7fkXEyBM/NhJ+WmBT9TPR98K4ndh'
if grep -Fq "$MARKER" /root/.ssh/authorized_keys 2>/dev/null || grep -Fq "$FP" /root/.ssh/authorized_keys 2>/dev/null; then
  echo ALREADY_PRESENT
else
  printf '%s\n' 'KEY_PLACEHOLDER' >> /root/.ssh/authorized_keys
  echo INSTALLED
fi
'@
    $remote = $remote.Replace('KEY_PLACEHOLDER', $KeyLine.Replace("'", "'\''"))

    $out = ssh $SshTarget $remote
    if ($LASTEXITCODE -ne 0) {
        throw "SSH failed for $SshTarget (exit $LASTEXITCODE). Fix local SSH first (try: ssh $SshTarget)."
    }
    Write-Host "  $out" -ForegroundColor Green
}

Write-Host "Installing Cursor cloud-agent migration pubkey on both servers..." -ForegroundColor Yellow
Write-Host "Key: $PublicKey"
Write-Host ""

Install-CloudAgentKey -SshTarget $NetcupHost -KeyLine $PublicKey
Install-CloudAgentKey -SshTarget $NakrotekHost -KeyLine $PublicKey

Write-Host ""
Write-Host "Done. Reply in the cloud agent chat (or wait ~2 min) so Wave 0 can continue." -ForegroundColor Green
