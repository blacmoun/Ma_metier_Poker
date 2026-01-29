#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Fix auto des fins de ligne Windows
sed -i 's/\r$//' "$SCRIPT_DIR"/*.sh "$SCRIPT_DIR"/config.env 2>/dev/null || true

# Charge la config
source "$SCRIPT_DIR/config.env"

echo "=== [PERMISSIONS] Ajustement des droits Laravel ==="

if [ ! -d "$PROJECT_DIR" ]; then
  echo "[ERREUR] Le dossier ${PROJECT_DIR} n'existe pas."
  echo "=> Lance d'abord ./04_deploy_app.sh"
  exit 1
fi

echo "[INFO] Application des droits sur le projet..."

# Le user Linux propriétaire du projet
chown -R "${LINUX_USER}:${WEB_GROUP}" "$PROJECT_DIR"

# Les dossiers critiques pour Laravel (logs, cache…) en écriture par Apache
chown -R "${WEB_GROUP}:${WEB_GROUP}" \
  "${PROJECT_DIR}/storage" \
  "${PROJECT_DIR}/bootstrap/cache"

chmod -R ug+rwX \
  "${PROJECT_DIR}/storage" \
  "${PROJECT_DIR}/bootstrap/cache"

echo "[INFO] Vérification rapide :"
ls -ld "${PROJECT_DIR}/storage" "${PROJECT_DIR}/bootstrap/cache"

echo "=== [PERMISSIONS] OK ==="
