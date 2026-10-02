#!/bin/sh
# Verschlüsselte Sicherung von PostgreSQL und Dateien (Anhänge, Modellbilder).
#   backup loop   → täglich zur Uhrzeit BACKUP_HOUR (Standard 02)
#   backup now    → sofort eine Sicherung erstellen
set -eu

export PGPASSWORD="$(cat "${DB_PASSWORD_FILE:-/run/secrets/db_password}")"
PASS_FILE="${BACKUP_PASSPHRASE_FILE:-/run/secrets/backup_passphrase}"
DEST=/backups
KEEP_DAYS="${BACKUP_KEEP_DAYS:-30}"

run_backup() {
  ts="$(date +%Y%m%d-%H%M%S)"
  tmp="$(mktemp -d)"
  trap 'rm -rf "$tmp"' EXIT

  echo "[backup] $ts: Datenbank …"
  pg_dump -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" --no-owner --format=custom --compress=6 -f "$tmp/db.dump"

  echo "[backup] $ts: Dateien …"
  tar -C /data -czf "$tmp/files.tar.gz" . 2>/dev/null || true

  tar -C "$tmp" -cf "$tmp/bundle.tar" db.dump files.tar.gz
  out="$DEST/vtssg_inv02-$ts.tar.enc"
  openssl enc -aes-256-cbc -pbkdf2 -iter 600000 -salt -in "$tmp/bundle.tar" -out "$out.part" -pass "file:$PASS_FILE"
  mv "$out.part" "$out"
  ( cd "$DEST" && sha256sum "$(basename "$out")" > "$(basename "$out").sha256" )
  echo "[backup] $ts: fertig → $out ($(du -h "$out" | cut -f1))"

  find "$DEST" -name 'vtssg_inv02-*' -mtime +"$KEEP_DAYS" -delete
  rm -rf "$tmp"; trap - EXIT
}

case "${1:-loop}" in
  now) run_backup ;;
  loop)
    echo "[backup] Dienst gestartet (täglich ${BACKUP_HOUR:-02}:00, Aufbewahrung ${KEEP_DAYS} Tage)"
    while true; do
      now="$(date +%s)"
      target="$(date -d "$(date +%Y-%m-%d) ${BACKUP_HOUR:-02}:00:00" +%s 2>/dev/null || echo $((now + 86400)))"
      [ "$target" -le "$now" ] && target=$((target + 86400))
      sleep $((target - now))
      run_backup || echo "[backup] FEHLER bei der Sicherung" >&2
    done
    ;;
  *) echo "Aufruf: backup [loop|now]" >&2; exit 2 ;;
esac
