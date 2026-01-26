#!/bin/bash

# Script d'installation serveur pour projet Laravel (Ma_metier_Poker)

set -e  # Stoppe le script au premier échec

### Vérification des droits ###
if [ "$EUID" -ne 0 ]; then
  echo "Veuillez exécuter ce script avec sudo ou en tant que root."
  exit 1
fi

echo "=== Mise à jour du système ==="
apt update -y
apt upgrade -y

echo "=== Installation d'Apache ==="
apt install -y apache2

echo "=== Installation de PHP (version par défaut Ubuntu) et des extensions ==="
apt install -y \
  php \
  php-cli \
  libapache2-mod-php \
  php-mysql \
  php-xml \
  php-mbstring \
  php-gd \
  php-curl \
  php-zip \
  php-bcmath

echo "=== Vérification version PHP ==="
php -v || { echo "Erreur: PHP n'est pas installé correctement"; exit 1; }

echo "=== Activation du module PHP et du module rewrite dans Apache ==="
PHPV=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')

# Activer le module PHP correspondant (php8.1 par exemple)
if a2enmod php$PHPV 2>/dev/null; then
  echo "Module Apache php$PHPV activé."
else
  echo "Attention: impossible d'activer php$PHPV (peut-être déjà actif ou non nécessaire)."
fi

# Activer mod_rewrite (important pour Laravel)
if a2enmod rewrite 2>/dev/null; then
  echo "Module rewrite activé."
else
  echo "Attention: mod_rewrite semble déjà actif."
fi

echo "=== Configuration Apache pour autoriser les .htaccess dans /var/www ==="
# On autorise AllowOverride All uniquement dans le bloc <Directory /var/www/>
sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

systemctl restart apache2

echo "=== Installation de MariaDB ==="
apt install -y mariadb-server

echo "=== Version MariaDB ==="
mariadb --version || mysql --version || echo "MariaDB/MySQL non trouvé, vérifier l'installation"

echo "=== Installation de Composer ==="
if ! command -v composer >/dev/null 2>&1; then
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  php composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm composer-setup.php
else
  echo "Composer déjà installé, étape ignorée."
fi

echo "=== Version Composer ==="
composer --version || echo "Composer non disponible, vérifier l'installation"

echo "=== Installation de Node.js et npm (depots Ubuntu) ==="
apt install -y nodejs npm

echo "=== Version Node.js ==="
node -v || echo "Node.js non disponible"

echo "=== Version npm ==="
npm -v || echo "npm non disponible"

echo "=== Récapitulatif des services ==="
echo "--- Apache2 ---"
systemctl status apache2 --no-pager || echo "Apache2 ne semble pas démarrer correctement"

echo "--- MariaDB ---"
systemctl status mariadb --no-pager || echo "MariaDB ne semble pas démarrer correctement"

echo "=== Serveur prêt à recevoir le code Laravel (Ma_metier_Poker) ==="
echo "Place ensuite ton projet dans /var/www, configure ton VirtualHost si besoin et fais 'composer install'."


