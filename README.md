# VTSSG Inventar (`vtssg_inv02`)

Inventar- und Einsatzverwaltung für eine Veranstaltungstechnik-Firma – inspiriert von Snipe-IT, aber auf den Alltag
zwischen Lager, Werkstatt und Bühne zugeschnitten: **Projekte, Packlisten, Scan-Beladung, DGUV-V3-/Rigging-Prüffristen,
Defekt-Tickets, Inventur und ein manipulationssicheres Audit-Log.**

Stack: Laravel 12 · PHP 8.3-FPM · PostgreSQL 16 · Redis · Caddy (TLS) · Tailwind 4 · vollständig in Docker.

## Funktionsumfang

| Bereich | Highlights |
|---|---|
| **Equipment** | Assets mit Tag/QR/Seriennummer, Modelle, Kategorien, Hersteller, Lieferanten, Standortbaum (Lager → Regal, Fahrzeug, Venue), Status-Labels, **Cases/Kits** (Inhalt wird mit ausgegeben), **Mengenartikel** (z. B. 200 XLR-Kabel), Verbrauchsmaterial mit Mindestbestand, Dateianhänge (Handbücher, Protokolle) |
| **Einsatz** | Projekte mit Zeitfenster, **Reservierung & Verfügbarkeitsprüfung** über Projekte hinweg, Packlisten mit Gruppen, **Vorlagen** („PA klein“), **Zumiete**, Projekt-Kalender, Duplizieren, **Lieferschein- und Packlisten-PDF**, Mietwert-Berechnung |
| **Scannen** | Kamera-Scanner (PWA) und Handscanner (Tastatur-Modus); Beladen/Rückbau mit Soll/Ist-Fortschritt, Ton-/Vibrations-Feedback, Offline-Warteschlange; Schnellausgabe/-rücknahme |
| **Sicherheit & Wartung** | Prüfarten (DGUV V3, Rigging/Sachkundiger, Kettenzug …) je Modell mit Intervall; **überfällige Prüfung sperrt die Ausgabe**; „nicht bestanden“ sperrt das Gerät und erzeugt ein Ticket; Betriebsstunden/Lampenlebensdauer; Wartungstickets mit Kosten; Rückgabe „defekt“ → automatisch Ticket + Sperre |
| **Inventur** | Soll/Ist-Abgleich per Scan, unerwartete Funde, Abschlussprotokoll |
| **Auswertung** | Dashboard, Bestand/Buchwert (lineare Abschreibung), Lebenszykluskosten je Einsatz, tägliche E-Mail zu fälligen Prüfungen & Mindestbeständen |
| **Integration** | REST-API (`/api/v1`, Bearer-Token), **signierte Webhooks** (HMAC, SSRF-geschützt, Zustellprotokoll) für Rentman/n8n/Slack-Brücken, **iCal-Kalender-Abo** pro Benutzer, CSV-Import (atomar, mit Testlauf) und -Export |
| **Rider & Logistik** | **Tech-Rider-Import** (Text/PDF → Modell-Vorschläge mit Verfügbarkeit, Rest als Zumiete), Gesamtgewicht/Leistung je Projekt mit **Fahrzeug-Zuladungsprüfung**, **Beladefotos** (Case-Check), **digitale Kunden-Unterschrift** auf dem Lieferschein |
| **Buchhaltung** | Anlagenspiegel mit AfA (CSV), Rechnungsposten je Projekt, Erlösbuchungen im DATEV-ähnlichen Stil (Konten/Format mit Steuerberater abstimmen) |
| **Mobil/NFC** | Web-NFC: Tags lesen und beschreiben (Chrome/Android); RFID-Handleser im Tastatur-Modus funktionieren am Scan-Feld |
| **Gerätepass** | Öffentliche, minimale Seite je Gerät (`/pass/<uuid>`): Prüfstatus und Eigentümer – ohne Seriennummer/Preise |
| **Prognose** | Ersatzbedarf (24 Monate) aus Betriebsstunden-Tempo und Abschreibung; Erinnerungs-Mail auch für überfällige Rückgaben |
| **Administration** | Rollen (Administrator, Lager, Projektleitung, Techniker, Nur Lesen) mit feingranularen Rechten, Benutzerverwaltung, Einstellungen |

## Schnellstart (Docker)

```bash
make init        # erzeugt ./secrets/* und .env.docker (Zufallspasswörter, App-Key)
$EDITOR .env.docker   # APP_DOMAIN, MAIL_*, ADMIN_EMAIL anpassen
make up          # baut und startet alle Container
make ps          # Health-Status
```

Danach: `https://<APP_DOMAIN>` (lokal: `https://localhost`, Zertifikat der Caddy-eigenen CA).
Das initiale Admin-Passwort gibt `make init` aus; beim ersten Login muss es geändert werden. Mit
`INV_REQUIRE_2FA=true` (Standard im Docker-Beispiel) muss außerdem 2FA eingerichtet werden.

### Container

Alle Ressourcen tragen das Präfix **`vtssg_inv02_`** (Docker erlaubt keine führenden Unterstriche in Namen):

| Container | Aufgabe | Netz |
|---|---|---|
| `vtssg_inv02_web` | Caddy: TLS, HSTS, statische Dateien, Proxy zu PHP-FPM | frontend |
| `vtssg_inv02_app` | PHP-FPM, führt Migrationen/Erstinstallation aus | frontend + backend |
| `vtssg_inv02_queue` | Queue-Worker (E-Mail-Versand) | frontend + backend |
| `vtssg_inv02_scheduler` | Zeitplan: Prüf-Erinnerungen, Neuberechnung, Audit-Verifikation | frontend + backend |
| `vtssg_inv02_db` | PostgreSQL 16 (SCRAM, Prüfsummen) | **backend (intern)** |
| `vtssg_inv02_redis` | Cache & Queue (Passwort) | **backend (intern)** |
| `vtssg_inv02_backup` | Täglich verschlüsselte Sicherung (DB + Dateien) | backend (intern) |
| `vtssg_inv02_mailpit` | Mail-Catcher, nur `--profile dev` | frontend |

Netze `vtssg_inv02_frontend` / `vtssg_inv02_backend` (**internal: true** – DB und Redis haben keinen Internetzugang),
Volumes `vtssg_inv02_{pgdata,redisdata,storage,backups,caddy_data,caddy_config}`.

### Betrieb

```bash
make logs                       # Logs
make artisan c="inv:audit-verify"   # Hash-Kette des Audit-Logs prüfen
make admin email=chef@firma.de  # Administrator anlegen / Passwort zurücksetzen
make backup                     # Sofort-Backup  → Volume vtssg_inv02_backups
make restore f=vtssg_inv02-20260101-020000.tar.enc
make update                     # git pull, Backup, neu bauen, starten
```

Backups (`*.tar.enc`) sind mit AES-256 (PBKDF2, 600 000 Iterationen) verschlüsselt, per SHA-256 gesichert und werden
`BACKUP_KEEP_DAYS` Tage aufbewahrt. **Die Datei `secrets/backup_passphrase` extern sichern** – ohne sie sind Backups unlesbar.
Backups regelmäßig vom Server wegkopieren (`docker cp vtssg_inv02_backup:/backups ./offsite`).

## Sicherheit

Siehe [`docs/SECURITY.md`](docs/SECURITY.md). Kurzfassung: Härtung der Container (read-only, `cap_drop: ALL`,
no-new-privileges, Nicht-Root), Docker-Secrets statt Umgebungsvariablen, isoliertes Backend-Netz, striktes CSP,
TLS/HSTS, 2FA (TOTP + Recovery-Codes), Lockout/Rate-Limiting, verschlüsselte Sessions, Rollen/Rechte auf jeder Route,
Hash-verkettetes Audit-Log, sichere Datei-Uploads, CSV-Injection-Schutz.

## Entwicklung (ohne Docker)

```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed            # legt Basisdaten + Admin an (Passwort wird ausgegeben)
php artisan db:seed --class=DemoSeeder   # optional: Beispieldaten
npm run build && php artisan serve    # oder: npm run dev
php artisan test                      # 51 Tests (SQLite); CI testet zusätzlich gegen PostgreSQL
```

## Wichtige Konzepte

* **Asset vs. Modell**: Ein *Modell* („Robe MegaPointe“) definiert Preis, Prüfpflichten, Lebensdauer. Ein *Asset* ist das
  konkrete Gerät (Tag `VT-000123`). Mengenartikel haben ein Asset mit Stückzahl.
* **Verfügbarkeit**: Zählt alle ausgebbaren, nicht gesperrten Assets eines Modells minus Reservierungen *überlappender*
  Projekte (Status geplant → im Einsatz). Konflikte werden am Projekt angezeigt und blockieren „Bestätigt“.
* **Prüfungen**: Fälligkeit = letzte bestandene Prüfung + Intervall (ohne Prüfung: Kaufdatum + Intervall). Prüfarten mit
  „sperrt Ausgabe“ verhindern Check-out und Verladen, solange sie überfällig sind.
* **Audit-Log**: Jede Änderung wird mit Benutzer/IP protokolliert und per HMAC-Kette gesichert. `inv:audit-verify` (täglich
  automatisch) erkennt nachträgliche Änderungen oder Löschungen.

## Lizenz / Hinweis

Eigenentwicklung, nicht von Snipe-IT abgeleitet (kein Code übernommen).
