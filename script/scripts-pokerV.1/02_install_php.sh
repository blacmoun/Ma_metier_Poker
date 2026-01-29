#!/usr/bin/env bash

set -e
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

# Auto-fix Windows line endings if needed
sed -i 's/\r$//' "$SCRIPT_DIR"/*.sh "$SCRIPT_DIR"/config.env 2>/dev/null || true

source "$SCRIPT_DIR/config.env"

set -euo pipefail

source "$(dirname "$0")/config.env"

echo "=== [PHP] Installation PHP ${PHP_VERSION} et extensions ==="

if ! command -v php${PHP_VERSION} >/dev/null 2>&1; then
  echo "[INFO] Ajout du dépôt PPA Ondrej pour PHP..."
  apt update
  apt install -y software-properties-common
  add-apt-repository -y ppa:ondrej/php

  apt update
  echo "[INFO] Installation de PHP ${PHP_VERSION}..."
  apt install -y \
    php${PHP_VERSION} \
    php${PHP_VERSION}-common \
    php${PHP_VERSION}-mysql \
    php${PHP_VERSION}-xml \
    php${PHP_VERSION}-curl \
    php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-zip \
    php${PHP_VERSION}-gd
else
  echo "[INFO] PHP ${PHP_VERSION} est déjà installé."
fi

echo "[INFO] Configuration Apache pour utiliser PHP ${PHP_VERSION}..."

# On désactive d'anciens modules PHP Apache si besoin
a2dismod php8.1 php8.2 php8.3 >/dev/null 2>&1 || true
a2enmod "php${PHP_VERSION}" >/dev/null 2>&1 || true

systemctl reload apache2

echo "=== [PHP] OK ==="
