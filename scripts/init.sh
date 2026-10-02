#!/usr/bin/env bash
# Erzeugt Geheimnisse und .env.docker für den Docker-Stack (idempotent – überschreibt nichts).
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p secrets
umask 077

gen() { # name, Befehl
  if [ ! -s "secrets/$1" ]; then
    eval "$2" > "secrets/$1"
    echo "  + secrets/$1 erzeugt"
  else
    echo "  = secrets/$1 vorhanden"
  fi
}
gen app_key          'printf "base64:%s" "$(openssl rand -base64 32)"'
gen db_password      'openssl rand -hex 24'
gen redis_password   'openssl rand -hex 24'
gen backup_passphrase 'openssl rand -base64 48'
gen admin_password   'openssl rand -base64 18 | tr -d "=+/" | cut -c1-20'
chmod 644 secrets/* 2>/dev/null || true   # Container-Nutzer (www-data, postgres, backup) müssen lesen können; Verzeichnis ist nicht web-erreichbar
chmod 700 secrets

if [ ! -f .env.docker ]; then
  cp .env.docker.example .env.docker
  echo "  + .env.docker erzeugt – bitte APP_DOMAIN, MAIL_* und ADMIN_EMAIL anpassen"
fi

cat <<MSG

Fertig. Wichtig:
  • secrets/backup_passphrase SICHER AUSSERHALB des Servers aufbewahren – ohne sie sind Backups unlesbar.
  • Initiales Admin-Passwort:  $(cat secrets/admin_password)   (muss beim ersten Login geändert werden)
Start:  make up      (oder: docker compose --env-file .env.docker up -d --build)
MSG
