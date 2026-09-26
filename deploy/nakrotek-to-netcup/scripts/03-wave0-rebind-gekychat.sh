#!/usr/bin/env bash
# Re-home GekyChat under Hestia without changing public URLs.
# Keeps app at /var/www/chat.gekychat.com (symlink from Hestia docroot).
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=_common.sh
source "$SCRIPT_DIR/_common.sh"

require_access

HESTIA_USER="${HESTIA_GEKY_USER:-gekychat}"
APP_PATH="$GEKYCHAT_APP_PATH"

ssh_n "command -v v-add-user >/dev/null" || die "Hestia not installed — run 02 first"

log "Creating Hestia user $HESTIA_USER and GekyChat web domains"

# Upload nginx templates for Reverb if present
if [[ -f "$SCRIPT_DIR/../nginx/laravel-reverb.tpl" ]] || [[ -f "$SCRIPT_DIR/../../nginx/laravel-reverb.tpl" ]]; then
  TPL_SRC="$SCRIPT_DIR/../../nginx"
  scp_n "$TPL_SRC/laravel-reverb.tpl" "$TPL_SRC/laravel-reverb.stpl" \
    "${NETCUP}:/usr/local/hestia/data/templates/web/nginx/" 2>/dev/null || true
fi

ssh_n "set -euo pipefail
HESTIA_USER='$HESTIA_USER'
APP_PATH='$APP_PATH'
PASS=\$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)

# Create panel user if missing
if ! v-list-user \"\$HESTIA_USER\" >/dev/null 2>&1; then
  v-add-user \"\$HESTIA_USER\" \"\$PASS\" admin@gekychat.com default
  echo \"\$PASS\" > /root/pre-hestia-backup/latest/hestia-\${HESTIA_USER}.password 2>/dev/null || \
    (mkdir -p /root/pre-hestia-backup && echo \"\$PASS\" > /root/pre-hestia-backup/hestia-\${HESTIA_USER}.password)
  chmod 600 /root/pre-hestia-backup/*/hestia-\${HESTIA_USER}.password 2>/dev/null || true
fi

ensure_domain() {
  local d=\"\$1\"
  if ! v-list-web-domain \"\$HESTIA_USER\" \"\$d\" >/dev/null 2>&1; then
    v-add-web-domain \"\$HESTIA_USER\" \"\$d\" || true
  fi
}

ensure_domain gekychat.com
ensure_domain chat.gekychat.com
ensure_domain api.gekychat.com
ensure_domain web.gekychat.com
ensure_domain live.gekychat.com
ensure_domain monitor.gekychat.com

# Point chat.gekychat.com docroot at existing app public/
# Hestia layout: /home/\$user/web/\$domain/public_html
CHAT_ROOT=/home/\$HESTIA_USER/web/chat.gekychat.com/public_html
if [[ -d \"\$APP_PATH/public\" ]]; then
  # Replace empty public_html with symlink to Laravel public
  if [[ ! -L \"\$CHAT_ROOT\" ]]; then
    mv \"\$CHAT_ROOT\" \"\${CHAT_ROOT}.hestia-empty.\$(date +%s)\" 2>/dev/null || rm -rf \"\$CHAT_ROOT\"
    ln -sfn \"\$APP_PATH/public\" \"\$CHAT_ROOT\"
  fi
  # Also alias api → same public (Laravel routes)
  for d in api.gekychat.com web.gekychat.com gekychat.com; do
    R=/home/\$HESTIA_USER/web/\$d/public_html
    if [[ -d \"\$R\" && ! -L \"\$R\" ]]; then
      mv \"\$R\" \"\${R}.hestia-empty.\$(date +%s)\" 2>/dev/null || true
      ln -sfn \"\$APP_PATH/public\" \"\$R\"
    elif [[ ! -e \"\$R\" ]]; then
      ln -sfn \"\$APP_PATH/public\" \"\$R\"
    fi
  done
fi

# PHP version — prefer 8.3/8.4 if available
for ver in 8.4 8.3 8.2; do
  if [[ -x /usr/bin/php\$ver ]]; then
    v-change-web-domain-phpcli \"\$HESTIA_USER\" chat.gekychat.com \"\$ver\" 2>/dev/null || true
    break
  fi
done

# Try Reverb nginx template on chat domain
if [[ -f /usr/local/hestia/data/templates/web/nginx/laravel-reverb.tpl ]]; then
  v-change-web-domain-proxy-tpl \"\$HESTIA_USER\" chat.gekychat.com laravel-reverb 2>/dev/null || true
fi

# live.gekychat.com → LiveKit (custom nginx snippet)
LIVE_CONF=/home/\$HESTIA_USER/conf/web/live.gekychat.com/nginx.conf_livekit
mkdir -p \"\$(dirname \"\$LIVE_CONF\")\"
cat > \"\$LIVE_CONF\" <<'NGX'
location / {
    proxy_pass http://127.0.0.1:7880;
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
    proxy_set_header Upgrade \$http_upgrade;
    proxy_set_header Connection \"upgrade\";
    proxy_read_timeout 86400;
}
NGX

# monitor.gekychat.com → Grafana :3000
MON_CONF=/home/\$HESTIA_USER/conf/web/monitor.gekychat.com/nginx.conf_grafana
mkdir -p \"\$(dirname \"\$MON_CONF\")\"
cat > \"\$MON_CONF\" <<'NGX'
location / {
    proxy_pass http://127.0.0.1:3000;
    proxy_http_version 1.1;
    proxy_set_header Host \$host;
    proxy_set_header X-Real-IP \$remote_addr;
    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto \$scheme;
}
NGX

v-rebuild-web-domains \"\$HESTIA_USER\" || true
systemctl reload nginx || service nginx reload || true

# Ensure supervisor workers still point at APP_PATH
if [[ -d /etc/supervisor/conf.d ]]; then
  supervisorctl reread || true
  supervisorctl update || true
  supervisorctl restart gekychat-worker:* 2>/dev/null || true
  supervisorctl restart gekychat-reverb 2>/dev/null || true
fi

# LiveKit docker should still be up
if [[ -d /opt/livekit ]]; then
  cd /opt/livekit && docker compose ps || true
fi

echo REBIND_OK
"

log "Smoke-testing GekyChat HTTP endpoints from netcup"
ssh_n "set -e
curl -sI -H 'Host: chat.gekychat.com' http://127.0.0.1/ | head -5 || true
curl -sI -H 'Host: api.gekychat.com' http://127.0.0.1/ | head -5 || true
curl -skI https://chat.gekychat.com/ 2>/dev/null | head -5 || true
"

log "Wave 0 rebind complete. Verify web/API/WS/calls manually, then proceed to Wave 1."
