#!/usr/bin/env bash
# Idempotent Cloud Agent install for gekychat (Laravel API) + gekychat_desktop (Flutter).
set -euo pipefail

export PATH="${HOME}/.nvm/versions/node/v24.21.0/bin:/opt/flutter/bin:/usr/local/bin:${PATH}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

find_backend_root() {
  local candidates=(
    "${GEKYCHAT_BACKEND_ROOT:-}"
    "/agent/repos/gekychat"
    "${PWD}"
    "${PWD}/gekychat"
    "${PWD}/repos/gekychat"
  )
  # When invoked from the repo copy: scripts/cloud-agent -> repo root
  if [[ "${SCRIPT_DIR}" == */scripts/cloud-agent ]]; then
    candidates+=("$(cd "${SCRIPT_DIR}/../.." && pwd)")
  fi
  local c
  for c in "${candidates[@]}"; do
    [[ -n "${c}" ]] || continue
    if [[ -f "${c}/artisan" && -f "${c}/composer.json" ]]; then
      cd "${c}" && pwd
      return 0
    fi
  done
  return 1
}

find_desktop_root() {
  local candidates=(
    "${GEKYCHAT_DESKTOP_ROOT:-}"
    "${BACKEND_ROOT}/../gekychat_desktop"
    "/agent/repos/gekychat_desktop"
    "${PWD}/../gekychat_desktop"
    "${PWD}/gekychat_desktop"
    "${PWD}/repos/gekychat_desktop"
  )
  local c
  for c in "${candidates[@]}"; do
    [[ -n "${c}" ]] || continue
    if [[ -f "${c}/pubspec.yaml" ]]; then
      cd "${c}" && pwd
      return 0
    fi
  done
  return 1
}

BACKEND_ROOT="$(find_backend_root)" || {
  echo "gekychat backend not found"
  exit 1
}

ensure_mysql_ready() {
  if ! command -v mysql >/dev/null 2>&1; then
    echo "mysql client not found; expected in snapshot base image"
    return 1
  fi
  if ! mysqladmin --protocol=tcp -h127.0.0.1 -ugekychat -pgekychat ping --silent 2>/dev/null; then
    sudo service mysql start || true
    sleep 2
  fi
  sudo mysql -e "CREATE DATABASE IF NOT EXISTS gekychat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'gekychat'@'localhost' IDENTIFIED BY 'gekychat'; GRANT ALL PRIVILEGES ON gekychat.* TO 'gekychat'@'localhost'; FLUSH PRIVILEGES;" >/dev/null
}

configure_backend_env() {
  cd "${BACKEND_ROOT}"
  if [[ ! -f .env ]]; then
    cp .env.example .env
  fi
  php -r '
$env = file_get_contents(".env");
$replacements = [
  "/^DB_CONNECTION=.*/m" => "DB_CONNECTION=mysql",
  "/^#? ?DB_HOST=.*/m" => "DB_HOST=127.0.0.1",
  "/^#? ?DB_PORT=.*/m" => "DB_PORT=3306",
  "/^#? ?DB_DATABASE=.*/m" => "DB_DATABASE=gekychat",
  "/^#? ?DB_USERNAME=.*/m" => "DB_USERNAME=gekychat",
  "/^#? ?DB_PASSWORD=.*/m" => "DB_PASSWORD=gekychat",
  "/^APP_URL=.*/m" => "APP_URL=http://127.0.0.1:8000",
  "/^APP_ENV=.*/m" => "APP_ENV=local",
  "/^APP_DEBUG=.*/m" => "APP_DEBUG=true",
  "/^REVERB_HOST=.*/m" => "REVERB_HOST=127.0.0.1",
  "/^REVERB_PORT=.*/m" => "REVERB_PORT=8080",
  "/^REVERB_SCHEME=.*/m" => "REVERB_SCHEME=http",
  "/^REVERB_SERVER_HOST=.*/m" => "REVERB_SERVER_HOST=127.0.0.1",
  "/^REVERB_SERVER_PORT=.*/m" => "REVERB_SERVER_PORT=8080",
  "/^REVERB_SERVER_SCHEME=.*/m" => "REVERB_SERVER_SCHEME=http",
];
foreach ($replacements as $pattern => $value) {
  $env = preg_replace($pattern, $value, $env, 1, $count);
  if ($count === 0) {
    $env .= "\n".$value;
  }
}
if (!preg_match("/^PHONE_ALLOW_TEST_NUMBERS=/m", $env)) {
  $env .= "\nPHONE_ALLOW_TEST_NUMBERS=true\n";
} else {
  $env = preg_replace("/^PHONE_ALLOW_TEST_NUMBERS=.*/m", "PHONE_ALLOW_TEST_NUMBERS=true", $env);
}
file_put_contents(".env", $env);
'
}

# Desktop Ghana normalization prefixes a leading 0; accept both test forms.
harden_phone_test_numbers() {
  local phone_cfg="${BACKEND_ROOT}/config/phone.php"
  [[ -f "${phone_cfg}" ]] || return 0
  if ! grep -q "'0111111111'" "${phone_cfg}"; then
    python3 - "${phone_cfg}" <<'PY'
from pathlib import Path
import sys
path = Path(sys.argv[1])
text = path.read_text()
needle = "        '1111111111' => '123456',"
insert = "        '1111111111' => '123456',\n        '0111111111' => '123456',"
if needle in text and "'0111111111'" not in text:
    path.write_text(text.replace(needle, insert, 1))
    print(f"patched {path}")
PY
  fi
}

# MySQL 8 does not support CREATE INDEX IF NOT EXISTS used in an older migration.
harden_mysql_migrations() {
  local mig="${BACKEND_ROOT}/database/migrations/2025_08_26_103806_add_client_uuid_and_paging_indexes_to_group_messages.php"
  [[ -f "${mig}" ]] || return 0
  if grep -q 'CREATE INDEX IF NOT EXISTS' "${mig}"; then
    python3 - "${mig}" <<'PY'
import pathlib, sys
path = pathlib.Path(sys.argv[1])
text = path.read_text()
old = """        // Composite index for cursor paging
        DB::statement('CREATE INDEX IF NOT EXISTS group_messages_group_created_id ON group_messages (group_id, created_at, id)');

        // Optional: unique-ish safety on client_uuid (allow nulls)
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS group_messages_client_uuid_unique ON group_messages (client_uuid)');"""
new = """        // Composite index for cursor paging (MySQL 8 has no CREATE INDEX IF NOT EXISTS)
        try {
            DB::statement('CREATE INDEX group_messages_group_created_id ON group_messages (group_id, created_at, id)');
        } catch (\\Throwable $e) {
        }

        // Optional: unique-ish safety on client_uuid (allow nulls)
        try {
            DB::statement('CREATE UNIQUE INDEX group_messages_client_uuid_unique ON group_messages (client_uuid)');
        } catch (\\Throwable $e) {
        }"""
if old in text:
    path.write_text(text.replace(old, new))
    print(f"patched {path}")
else:
    print(f"skip patch (already updated or unexpected content): {path}")
PY
  fi
}

install_backend() {
  cd "${BACKEND_ROOT}"
  configure_backend_env
  harden_mysql_migrations
  harden_phone_test_numbers
  composer install --no-interaction --prefer-dist
  php artisan key:generate --force --no-interaction >/dev/null
  php artisan storage:link --force --no-interaction >/dev/null 2>&1 || true
  if [[ -f package-lock.json ]]; then
    npm ci
  else
    npm install
  fi
  npm run build
}

configure_desktop_env() {
  local desktop_root="$1"
  cat > "${desktop_root}/.env" <<'EOF'
API_BASE_URL=http://127.0.0.1:8000/api/v1
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
PUSHER_APP_KEY=gk-reverb-key-2024
PUSHER_HOST=127.0.0.1
PUSHER_WSS_PORT=8080
PUSHER_FORCE_TLS=false
PUSHER_AUTH_ENDPOINT=http://127.0.0.1:8000/api/v1/broadcasting/auth
EOF
}

install_desktop() {
  local desktop_root
  if ! desktop_root="$(find_desktop_root)"; then
    echo "gekychat_desktop not found; skipping Flutter install"
    return 0
  fi
  configure_desktop_env "${desktop_root}"
  cd "${desktop_root}"
  flutter config --enable-linux-desktop --no-analytics >/dev/null || true
  flutter pub get
}

echo "==> Cloud Agent install (gekychat + gekychat_desktop)"
ensure_mysql_ready
install_backend
install_desktop
echo "==> Install complete"
