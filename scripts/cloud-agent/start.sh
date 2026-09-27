#!/usr/bin/env bash
# Per-boot start for gekychat Cloud Agent: ensure MySQL is up and reachable.
set -euo pipefail

export PATH="${HOME}/.nvm/versions/node/v24.21.0/bin:/opt/flutter/bin:/usr/local/bin:${PATH}"

if command -v mysqladmin >/dev/null 2>&1; then
  if ! mysqladmin --protocol=tcp -h127.0.0.1 -ugekychat -pgekychat ping --silent 2>/dev/null; then
    sudo service mysql start || true
    for _ in 1 2 3 4 5 6 7 8 9 10; do
      if mysqladmin --protocol=tcp -h127.0.0.1 -ugekychat -pgekychat ping --silent 2>/dev/null; then
        break
      fi
      sleep 1
    done
  fi
  sudo mysql -e "CREATE DATABASE IF NOT EXISTS gekychat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS 'gekychat'@'localhost' IDENTIFIED BY 'gekychat'; GRANT ALL PRIVILEGES ON gekychat.* TO 'gekychat'@'localhost'; FLUSH PRIVILEGES;" >/dev/null
fi

echo "MySQL ready on 127.0.0.1:3306 (db/user gekychat)"
