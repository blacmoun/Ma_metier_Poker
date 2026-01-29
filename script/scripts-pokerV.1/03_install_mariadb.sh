#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
sed -i 's/\r$//' "$SCRIPT_DIR"/*.sh "$SCRIPT_DIR"/config.env 2>/dev/null || true
source "$SCRIPT_DIR/config.env"

echo "=== [MARIADB] Installation et configuration de la base de données ==="

if ! command -v mariadb >/dev/null 2>&1; then
  echo "[INFO] Installation de MariaDB..."
  apt update
  apt install -y mariadb-server
  systemctl enable mariadb
  systemctl start mariadb
else
  echo "[INFO] MariaDB est déjà installé."
fi

echo "[INFO] Création de la base ${DB_NAME} et de l'utilisateur ${DB_USER}..."

mariadb <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'${DB_HOST}';
FLUSH PRIVILEGES;
EOF

echo "=== [MARIADB] OK ==="

