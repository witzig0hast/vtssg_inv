#!/bin/sh
# Stellt eine Sicherung wieder her:  restore /backups/vtssg_inv02-YYYYmmdd-HHMMSS.tar.enc
# ACHTUNG: überschreibt die Datenbank! Anwendung vorher stoppen (make restore erledigt das).
set -eu
f="${1:?Sicherungsdatei angeben}"
export PGPASSWORD="$(cat "${DB_PASSWORD_FILE:-/run/secrets/db_password}")"
PASS_FILE="${BACKUP_PASSPHRASE_FILE:-/run/secrets/backup_passphrase}"
[ -f "$f.sha256" ] && ( cd "$(dirname "$f")" && sha256sum -c "$(basename "$f").sha256" )
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
openssl enc -d -aes-256-cbc -pbkdf2 -iter 600000 -in "$f" -out "$tmp/bundle.tar" -pass "file:$PASS_FILE"
tar -C "$tmp" -xf "$tmp/bundle.tar"
pg_restore -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists --no-owner "$tmp/db.dump"
if [ -w /data ]; then tar -C /data -xzf "$tmp/files.tar.gz"; else echo "Hinweis: /data schreibgeschützt – Dateien nicht zurückgespielt" >&2; fi
echo "Wiederherstellung abgeschlossen."
