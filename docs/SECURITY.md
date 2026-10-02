# Sicherheitskonzept

## Authentifizierung & Sitzungen
* Passwörter: bcrypt (12 Runden), Mindestlänge 12, Groß/Klein/Zahl, Prüfung gegen bekannte Datenlecks (HIBP, k-Anonymität).
* Erstanmeldung/Admin-Reset erzwingt Passwortwechsel.
* **2FA (TOTP)** mit 8 Einmal-Recovery-Codes; Secret und Codes verschlüsselt gespeichert (`APP_KEY`). Pflicht für alle
  Konten mit `INV_REQUIRE_2FA=true`. Admin kann 2FA zurücksetzen.
* Schutz gegen Brute-Force: Rate-Limit je E-Mail+IP, Konto-Sperre nach 5 Fehlversuchen (15 min), konstantes Antwortverhalten
  (keine User-Enumeration), eigenes Limit für 2FA-Codes.
* Sessions: in der Datenbank, verschlüsselt, `HttpOnly`, `Secure`, `SameSite=Lax`, ID-Regeneration bei Login/2FA/Passwortwechsel.
  Deaktivieren/Löschen eines Benutzers beendet sofort alle Sitzungen und löscht API-Tokens.
* API: Sanctum-Bearer-Tokens (365 Tage), Rate-Limit 120/min, Rechte = Rechte des Benutzers.

## Autorisierung
* Rollen & Rechte (spatie/laravel-permission); **jede** Route trägt eine `can:`-Prüfung. Administrator hat via `Gate::before` alle Rechte.
* Dateizugriff (Anhänge) wird je Objekttyp geprüft. Letzter aktiver Administrator kann nicht entfernt werden.

## Anwendungsschutz
* **CSP** `default-src 'self'; script-src 'self'` (kein Inline-JS), `frame-ancestors 'none'`, `object-src 'none'`, `form-action 'self'`;
  zusätzlich HSTS, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` (Kamera nur für die eigene Seite), COOP/CORP.
* Authentifizierte Seiten: `Cache-Control: no-store`. Der Service-Worker cached ausschließlich statische Build-Assets.
* CSRF-Schutz für alle Web-Routen; Eloquent/Query-Builder mit Bindings (keine SQL-Konkatenation), LIKE-Wildcards escaped.
* **Uploads**: Whitelist (pdf, jpg, png, webp, txt, csv, xlsx; kein SVG/HTML/PHP), Größenlimit, MIME-Prüfung, zufälliger Dateiname,
  private Ablage (nicht im Web-Root), Auslieferung als Download mit `nosniff` und `sandbox`-CSP, SHA-256 gespeichert.
* CSV-Export neutralisiert Formel-Injection (`= + - @`); CSV-Import ist atomar mit Zeilenlimit.

## Zusatzfunktionen
* **Webhooks**: nur `https`, Ziel wird aufgelöst und gegen private/reservierte/Link-Local-Adressen geprüft (SSRF), keine Redirects,
  Nutzdaten per HMAC-SHA256 signiert (`X-Inventory-Signature`), Secret verschlüsselt gespeichert, Zustellung über die Queue mit Backoff.
* **iCal-Abo**: 48-stelliger Zufallstoken je Benutzer, jederzeit widerruf-/erneuerbar, nur Lesezugriff auf Termine, Rate-Limit.
* **Gerätepass** (öffentlich): UUID-basiert (nicht erratbar), nur Modell, Tag, Prüfstatus, Eigentümer – keine Seriennummer/Preise/Notizen.
* **Unterschrift**: nur echte PNG-Dateien (Magic-Bytes + Bildparser, ≤ 300 KB), privat gespeichert, Erfassung im Audit-Log.
* **Rider-Import**: Dateityp-/Größenlimit, nichts wird ohne Bestätigung übernommen.

## Audit-Log
Append-only-Tabelle; jeder Eintrag enthält `prev_hash` und `hash = HMAC-SHA256(APP_KEY, Inhalt || prev_hash)`.
Änderung oder Löschung (auch direkt per SQL) bricht die Kette → `php artisan inv:audit-verify` (täglich 03:15 automatisch,
im UI unter *Audit-Log → Integrität prüfen*) meldet die erste manipulierte Stelle. Passwörter/2FA-Geheimnisse werden nie protokolliert.
Protokolliert werden u. a. Änderungen an allen Stammdaten, Logins (auch fehlgeschlagene), 2FA, Passwortwechsel, Token-Erzeugung,
Bestandsbuchungen und Inventurabschlüsse.

## Container & Infrastruktur
* Alle Container: `no-new-privileges`, `cap_drop: ALL` (nur das Nötigste per `cap_add`), Nicht-Root wo möglich, `read_only`-Dateisystem
  mit gezielten `tmpfs`/Volumes, Speicherlimits, Log-Rotation.
* **Secrets** liegen in `./secrets/*` (Docker-Secrets, `/run/secrets`), nicht in Compose/Umgebung/Image; der Entrypoint lädt sie zur Laufzeit.
* **Netz-Segmentierung**: `vtssg_inv02_backend` ist `internal` – PostgreSQL/Redis sind weder aus dem Internet noch ins Internet erreichbar.
  Nur Caddy veröffentlicht Ports (standardmäßig nur `127.0.0.1`; für LAN/Internet `BIND_IP=0.0.0.0`).
* PHP-FPM-Pool-Härtung: `disable_functions` (exec, system, proc_open …), `open_basedir`, `expose_php=Off`, `allow_url_include=Off`.
* PostgreSQL: SCRAM-SHA-256, Datenprüfsummen. Redis: Passwort, kein Port-Publishing.
* Backups: AES-256-CBC/PBKDF2, Prüfsumme, Aufbewahrung, Wiederherstellungsskript.
* CI: `composer audit`, `npm audit`, Hadolint, Trivy-Image-Scan, Tests gegen PostgreSQL.

## Betriebshinweise
1. `.env.docker` und `secrets/` niemals committen (sind in `.gitignore`).
2. Für Internetbetrieb: `APP_DOMAIN` setzen, DNS auf den Server, Ports 80/443 freigeben – Caddy holt automatisch ein Let's-Encrypt-Zertifikat.
3. Regelmäßig `make update` (Basis-Images erhalten Sicherheitsupdates beim Neubau: `docker compose build --pull`).
4. Backups offsite kopieren und die Wiederherstellung **testen**.
5. Es gibt bewusst keine „Passwort vergessen“-Funktion per E-Mail: Passwörter setzt ein Administrator zurück (`make admin`).
