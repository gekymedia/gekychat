#!/usr/bin/env bash
# Per-boot start for gekychat Cloud Agent: MySQL, migrate, then API server.
set -euo pipefail

export PATH="${HOME}/.nvm/versions/node/v24.21.0/bin:/opt/flutter/bin:/usr/local/bin:${PATH}"

find_backend_root() {
  local candidates=(
    "${GEKYCHAT_BACKEND_ROOT:-}"
    "/agent/repos/gekychat"
    "${PWD}"
    "${PWD}/gekychat"
    "${PWD}/repos/gekychat"
  )
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

if command -v mysqladmin >/dev/null 2>&1; then
  if ! mysqladmin --protocol=tcp -h127.0.0.1 -ugekychat -pgekychat ping --silent >/dev/null 2>&1; then
    sudo service mysql start >/dev/null 2>&1 || true
    for _ in 1 2 3 4 5 6 7 8 9 10; do
      if mysqladmin --protocol=tcp -h127.0.0.1 -ugekychat -pgekychat ping --silent >/dev/null 2>&1; then
        break
      fi
      sleep 1
    done
  fi
  sudo mysql -e "CREATE DATABASE IF NOT EXISTS gekychat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'gekychat'@'localhost' IDENTIFIED BY 'gekychat'; GRANT ALL PRIVILEGES ON gekychat.* TO 'gekychat'@'localhost'; FLUSH PRIVILEGES;" >/dev/null 2>&1
fi

BACKEND_ROOT="$(find_backend_root)" || {
  echo "gekychat backend not found"
  exit 1
}

cd "${BACKEND_ROOT}"
php artisan migrate --force --no-interaction
php artisan db:seed --force --no-interaction || true
echo "MySQL ready; starting API on http://127.0.0.1:8000"
exec php artisan serve --host=127.0.0.1 --port=8000
