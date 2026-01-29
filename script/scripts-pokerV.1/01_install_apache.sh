#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
sed -i 's/\r$//' "$SCRIPT_DIR"/*.sh "$SCRIPT_DIR"/config.env 2>/dev/null || true
source "$SCRIPT_DIR/config.env"

echo "=== [APACHE] Installation et configuration de base ==="

if ! command -v apache2 >/dev/null 2>&1; then
  echo "[INFO] Installation d'Apache..."
  apt update
  apt install -y apache2
else
  echo "[INFO] Apache est déjà installé."
fi

echo "[INFO] Activation des modules nécessaires..."
a2enmod rewrite ssl headers proxy proxy_http >/dev/null 2>&1 || true

echo "[INFO] Création du VirtualHost ${APACHE_SITE_NAME}.conf"

VHOST_CONF="/etc/apache2/sites-available/${APACHE_SITE_NAME}.conf"

cat > "$VHOST_CONF" <<EOF
<VirtualHost *:80>
    ServerName ${SERVER_NAME}

    DocumentRoot ${PROJECT_DIR}/public

    <Directory ${PROJECT_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/${APACHE_SITE_NAME}_error.log
    CustomLog \${APACHE_LOG_DIR}/${APACHE_SITE_NAME}_access.log combined
</VirtualHost>
EOF

echo "[INFO] Activation du site ${APACHE_SITE_NAME}..."
a2ensite "${APACHE_SITE_NAME}" >/dev/null 2>&1 || true
a2dissite 000-default >/dev/null 2>&1 || true

echo "[INFO] Vérification de la config Apache..."
apache2ctl configtest || {
  echo "[ERREUR] La configuration Apache est invalide."
  echo "=> Corrige /etc/apache2/sites-available/${APACHE_SITE_NAME}.conf puis relance :"
  echo "   sudo apache2ctl configtest"
  exit 1
}

echo "[INFO] Redémarrage d'Apache..."
systemctl reload apache2

echo "=== [APACHE] OK ==="
