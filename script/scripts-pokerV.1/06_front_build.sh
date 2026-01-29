#!/usr/bin/env bash
set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

sed -i 's/\r$//' "$SCRIPT_DIR"/*.sh "$SCRIPT_DIR"/config.env 2>/dev/null || true
source "$SCRIPT_DIR/config.env"
set -euo pipefail

echo "=== [FRONT] Installation Node + build frontend ==="

cd "$PROJECT_DIR"

if ! command -v curl >/dev/null 2>&1; then
  echo "[INFO] curl non installé, installation..."
  apt update
  apt install -y curl
fi

if ! command -v node >/dev/null 2>&1; then
  echo "[INFO] Node.js non installé, installation de Node 20..."
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt install -y nodejs
else
  echo "[INFO] Node.js déjà installé : $(node -v)"
fi

echo "[INFO] Installation des dépendances NPM..."
npm install

echo "[INFO] Build frontend..."
npm run build

echo "=== [FRONT] OK ==="
