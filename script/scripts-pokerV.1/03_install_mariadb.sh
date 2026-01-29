#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
source "$SCRIPT_DIR/config.env"
echo "=== [MARIADB] Installation et configuration de la base de données ==="
# 1) Installation MariaDB si pas présent
if ! command -v mariadb >/dev/null 2>&1; then
 echo "[INFO] MariaDB non installé, installation..."
 apt update
 apt install -y mariadb-server
else
 echo "[INFO] MariaDB est déjà installé."
fi
# 2) S'assurer que le service tourne
echo "[INFO] Vérification / démarrage du service MariaDB..."
systemctl enable mariadb >/dev/null 2>&1 || true
systemctl start mariadb
if ! systemctl is-active --quiet mariadb; then
 echo "[ERREUR] Le service MariaDB ne démarre pas."
 echo "=> Vérifie avec : sudo systemctl status mariadb"
 exit 1
fi
# 3) Création base + user applicatif
echo "[INFO] Création de la base ${DB_NAME} et de l'utilisateur ${DB_USER}..."
mariadb <<EOF
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
 CHARACTER SET utf8mb4
 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}'
 IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.*
 TO '${DB_USER}'@'${DB_HOST}';
FLUSH PRIVILEGES;
EOF
echo "=== [MARIADB] OK ==="
