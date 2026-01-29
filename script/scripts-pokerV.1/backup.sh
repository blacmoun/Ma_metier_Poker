#!/bin/bash

# Date du backup
DATE=$(date +%Y-%m-%d)

# Dossier de destination des backups
BACKUP_DIR="/backup/$DATE"

# Création du dossier du jour
mkdir -p "$BACKUP_DIR"

# Sauvegarde du site web
rsync -av /var/www "$BACKUP_DIR/"

# Sauvegarde des fichiers de configuration
rsync -av /etc "$BACKUP_DIR/"

# Message de confirmation
echo "Backup terminé le $DATE"
