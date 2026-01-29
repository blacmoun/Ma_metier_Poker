#!/usr/bin/env bash
set -euo pipefail

echo "========================================"
echo "  Déploiement automatique Ma_metier_Poker"
echo "========================================"

# Vérification sudo
if [ "$EUID" -ne 0 ]; then
  echo "[ERREUR] Ce script doit être lancé avec sudo"
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

echo "[INFO] Dossier scripts : $SCRIPT_DIR"

# --------------------------------------------------
# 1. Préparation système (VM vierge)
# --------------------------------------------------
echo "[INFO] Mise à jour du système + outils de base..."
apt update -y
apt install -y \
  ca-certificates \
  curl \
  git \
  dos2unix \
  software-properties-common

# --------------------------------------------------
# 2. Correction des fins de ligne Windows
# --------------------------------------------------
echo "[INFO] Correction des fins de ligne Windows (CRLF)..."
dos2unix "$SCRIPT_DIR"/*.sh "$SCRIPT_DIR"/config.env >/dev/null 2>&1 || true

# --------------------------------------------------
# 3. Chargement de la configuration
# --------------------------------------------------
if [ ! -f "$SCRIPT_DIR/config.env" ]; then
  echo "[ERREUR] config.env introuvable"
  exit 1
fi

source "$SCRIPT_DIR/config.env"

# --------------------------------------------------
# 4. Rendre les scripts exécutables
# --------------------------------------------------
echo "[INFO] Attribution des droits d'exécution..."
chmod +x "$SCRIPT_DIR"/*.sh

# --------------------------------------------------
# 5. Lancement des scripts dans l'ordre
# --------------------------------------------------
echo "----------------------------------------"
echo "[1/6] Apache"
echo "----------------------------------------"
"$SCRIPT_DIR/01_install_apache.sh"

echo "----------------------------------------"
echo "[2/6] PHP"
echo "----------------------------------------"
"$SCRIPT_DIR/02_install_php.sh"

echo "----------------------------------------"
echo "[3/6] MariaDB"
echo "----------------------------------------"
"$SCRIPT_DIR/03_install_mariadb.sh"

echo "----------------------------------------"
echo "[4/6] Déploiement Laravel"
echo "----------------------------------------"
"$SCRIPT_DIR/04_deploy_app.sh"

echo "----------------------------------------"
echo "[5/6] Permissions"
echo "----------------------------------------"
"$SCRIPT_DIR/05_permissions.sh"

echo "----------------------------------------"
echo "[6/6] Frontend (Node + build)"
echo "----------------------------------------"
"$SCRIPT_DIR/06_front_build.sh"

# --------------------------------------------------
# 6. Vérifications finales
# --------------------------------------------------
echo "----------------------------------------"
echo "[CHECK] Vérifications finales"
echo "----------------------------------------"

systemctl is-active apache2 >/dev/null && echo "[OK] Apache actif"
systemctl is-active mariadb >/dev/null && echo "[OK] MariaDB actif"

php -v | head -n 1
node -v
npm -v

if [ -d "/var/www/Ma_metier_Poker/public/build" ]; then
  echo "[OK] Frontend compilé"
else
  echo "[ERREUR] Frontend non compilé"
  exit 1
fi

# --------------------------------------------------
# FIN
# --------------------------------------------------
echo "========================================"
echo "  Déploiement terminé avec succès"
echo " URL : http://$(hostname -I | awk '{print $1}')"
echo "========================================"

