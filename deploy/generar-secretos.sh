#!/usr/bin/env bash
# Crea los archivos de secretos que faltan en deploy/secrets/ (carpeta 700, archivos 600).
# Las claves de base y de restic se generan al azar; SMTP, S3 y el certificado de ARCA se completan a mano.
# Guardá una copia de cada archivo en la bóveda del equipo (Bitwarden, 1Password…).
set -euo pipefail
cd "$(dirname "$0")"
umask 077
mkdir -p secrets
chmod 700 secrets

al_azar() { [ -s "secrets/$1" ] || { openssl rand -base64 32 | tr -d '/+=\n' | cut -c1-32 > "secrets/$1"; echo "creado secrets/$1"; }; }
vacio()   { [ -e "secrets/$1" ] || { : > "secrets/$1"; echo "creado secrets/$1 (VACÍO: completalo)"; }; }

al_azar db_password
al_azar db_app_password
al_azar db_backup_password
al_azar restic_password
vacio smtp_password
vacio s3_secret_key
vacio arca.crt
vacio arca.key
chmod 600 secrets/*
echo "Secretos listos en $(pwd)/secrets"
