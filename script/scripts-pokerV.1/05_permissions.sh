#!/usr/bin/env bash
set -euo pipefail
# Détection automatique de l'utilisateur Linux
# - si lancé avec sudo → utilisateur réel
# - sinon → utilisateur courant
LINUX_USER="${SUDO_USER:-$(whoami)}"
WEB_GROUP="www-data"
# Dossier du projet Laravel
PROJECT_DIR="/var/www/Ma_metier_Poker"
echo "=== [PERMISSIONS] Ajustement des droits Laravel ==="
echo "[INFO] Utilisateur Linux : $LINUX_USER"
echo "[INFO] Groupe web : $WEB_GROUP"
echo "[INFO] Projet : $PROJECT_DIR"
# Vérification que le projet existe
if [ ! -d "$PROJECT_DIR" ]; then
 echo "[ERREUR] Le dossier du projet n'existe pas : $PROJECT_DIR"
 exit 1
fi
echo "[INFO] Application des droits sur le projet..."
# Propriétaire = utilisateur détecté, groupe = www-data
chown -R "$LINUX_USER:$WEB_GROUP" "$PROJECT_DIR"
# Droits standards Laravel
find "$PROJECT_DIR" -type d -exec chmod 755 {} \;
find "$PROJECT_DIR" -type f -exec chmod 644 {} \;
# Dossiers nécessitant écriture par Apache
chmod -R 775 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"
echo "=== [PERMISSIONS] OK ==="
