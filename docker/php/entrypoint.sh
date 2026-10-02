#!/bin/sh
# Entrypoint für App-, Queue- und Scheduler-Container.
set -eu

# Docker-Secrets (*_FILE) in Umgebungsvariablen übersetzen – Geheimnisse stehen nie in Compose/ENV-Dateien.
for v in DB_PASSWORD REDIS_PASSWORD APP_KEY ADMIN_PASSWORD MAIL_PASSWORD; do
  f="$(printenv "${v}_FILE" 2>/dev/null || true)"
  if [ -n "$f" ]; then
    if [ -r "$f" ]; then
      export "$v=$(cat "$f")"
    else
      echo "FEHLER: Secret-Datei $f für $v nicht lesbar" >&2
      exit 1
    fi
  fi
done

cd /var/www/html
mkdir -p storage/app/private storage/fonts storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

if [ "${WAIT_FOR_DB:-true}" = "true" ]; then
  i=0
  until php -r 'try { new PDO(sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "5432", getenv("DB_DATABASE")), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }'; do
    i=$((i + 1))
    if [ "$i" -gt 60 ]; then echo "FEHLER: Datenbank nicht erreichbar" >&2; exit 1; fi
    sleep 2
  done
fi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force --no-interaction
  php artisan inv:install --no-interaction
fi

php artisan config:cache --no-interaction
php artisan route:cache --no-interaction
php artisan view:cache --no-interaction
php artisan event:cache --no-interaction

exec "$@"
