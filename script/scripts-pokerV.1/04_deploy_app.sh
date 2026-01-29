#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
sed -i 's/\r$//' "$SCRIPT_DIR"/*.sh "$SCRIPT_DIR"/config.env 2>/dev/null || true
source "$SCRIPT_DIR/config.env"

echo "=== [APP] Déploiement de l'application Laravel ==="

# Git auto-install
if ! command -v git >/dev/null 2>&1; then
  echo "[INFO] Installation de Git..."
  apt update
  apt install -y git
fi

# Clone ou mise à jour du repo
if [ ! -d "$PROJECT_DIR/.git" ]; then
  echo "[INFO] Clonage du dépôt dans ${PROJECT_DIR}..."
  git clone "$GIT_URL" "$PROJECT_DIR"
  cd "$PROJECT_DIR"
  git checkout "$GIT_BRANCH"
else
  echo "[INFO] Projet déjà présent, mise à jour..."
  cd "$PROJECT_DIR"
  git fetch origin
  git checkout "$GIT_BRANCH"
  git pull origin "$GIT_BRANCH"
fi

# Composer auto-install si besoin
if ! command -v composer >/dev/null 2>&1; then
  echo "[INFO] Installation de Composer..."
  apt update
  apt install -y composer
fi

echo "[INFO] Installation des dépendances Composer..."
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader || {
  echo "[ERREUR] composer install a échoué."
  echo "=> Vérifie ta connexion, la version de PHP et relance :"
  echo "   cd ${PROJECT_DIR} && composer install --no-dev --optimize-autoloader"
  exit 1
}

if [ ! -f ".env" ]; then
  echo "[INFO] Création du fichier .env à partir de .env.example..."
  cp .env.example .env
fi

echo "[INFO] Mise à jour des paramètres DB dans .env..."
sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=mysql/" .env
sed -i "s/^DB_HOST=.*/DB_HOST=${DB_HOST}/" .env
sed -i "s/^DB_PORT=.*/DB_PORT=${DB_PORT}/" .env
sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env

echo "[INFO] Génération de la clé d'application Laravel (si nécessaire)..."
php artisan key:generate --force || true

echo "[INFO] Nettoyage et migration de la base..."
php artisan optimize:clear || true
php artisan migrate --force || {
  echo "[ERREUR] Les migrations Laravel ont échoué."
  echo "=> Vérifie la connexion DB et relance :"
  echo "   php artisan migrate --force"
  exit 1
}

echo "=== [APP] Déploiement OK ==="
