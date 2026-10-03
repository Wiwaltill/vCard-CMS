# Digital vCard CMS

**Digitale Visitenkarten für dein Team – mit eigenem Branding und ohne Datenbank.**

Ein kleines PHP-CMS mit Adminbereich, Kontaktkarten, vCard-Downloads und QR-Codes. Kontakte und Einstellungen werden als JSON gespeichert; die Einrichtung erfolgt über einen Installationsassistenten.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![GitHub release](https://img.shields.io/github/v/release/Wiwaltill/vCard-CMS)](https://github.com/Wiwaltill/vCard-CMS/releases)

[Installation](#installation) · [Verwendung](#verwendung) · [REST API](#rest-api) · [Backups & Updates](#backups--updates) · [Betrieb & Sicherheit](#betrieb--sicherheit)

## Funktionen

| Bereich | Möglichkeiten |
| --- | --- |
| Kontaktkarten | Responsive Karten, Profilbilder, Position und konfigurierbare Kontaktfelder |
| Teilen | Download als `.vcf`, lokal erzeugte QR-Codes als PNG oder SVG |
| Branding | Firmenname, Logo, Akzentfarbe und Links zu Impressum und Datenschutz |
| Darstellung | Themes **Classic**, **Minimal** und **Glass**; heller, dunkler oder systemabhängiger Modus |
| Sprache | Deutsch und Englisch, automatische Browser-Erkennung und Sprachwahl auf Kontaktkarten |
| Verwaltung | Kontakte suchen, nach Position filtern, seitenweise anzeigen und mit Live-Vorschau bearbeiten |
| Bilder | Neue Profilbilder und Logos automatisch verkleinern, ausrichten und als WebP ohne Metadaten speichern |
| Datenaustausch | CSV-Import und -Export sowie REST API zum Lesen und Schreiben von Kontakten |
| Datensicherung | ZIP-Backups mit Konfiguration, Kontakten und Uploads; Download und Wiederherstellung im Adminbereich |
| PWA | Web-App-Manifest und Service Worker zum Hinzufügen auf den Homescreen; Unterstützung abhängig von Browser und Gerät |

## Installation

### Voraussetzungen

- PHP **8.0 oder neuer**; für den Betrieb eine gepflegte PHP-Version einsetzen.
- Apache mit `mod_rewrite` und erlaubten `.htaccess`-Regeln, beispielsweise `AllowOverride All` für `public/`.
- PHP-Erweiterungen **fileinfo**, **gd** mit WebP-Unterstützung, **exif**, **mbstring** und **ZipArchive** für Bildverarbeitung, Suche, QR-Codes und Backups.
- Schreibzugriff des PHP-Prozesses auf `data/` und `public/uploads/`.
- HTTPS für den produktiven Betrieb und die Service-Worker-Funktion.

Es sind weder eine Datenbank noch ein Node.js-Build oder eine Composer-Installation erforderlich. Die Bibliotheken für die lokale QR-Erzeugung sind mit ihren Lizenzen im Repository enthalten.

### 1. Projekt bereitstellen

```bash
git clone https://github.com/Wiwaltill/vCard-CMS.git
cd vCard-CMS
```

Alternativ das Repository herunterladen und auf den Server kopieren. Auch versteckte Dateien wie `public/.htaccess` müssen mit übertragen werden.

### 2. Webroot konfigurieren

Den **DocumentRoot auf `public/`** setzen. `data/` liegt außerhalb des öffentlich erreichbaren Verzeichnisses und enthält später auch Passwort-Hash und API-Token. Die zusätzliche `data/.htaccess` sperrt unter Apache direkte Zugriffe, sofern `.htaccess`-Regeln aktiviert sind.

```text
vCard-CMS/
├── data/                    # Private Konfiguration und Kontakte
│   ├── config.sample.json
│   ├── contacts.sample.json
│   └── backups/             # Wird bei der ersten Sicherung angelegt
├── public/                  # DocumentRoot des Webservers
│   ├── .htaccess            # URL-Rewriting
│   ├── admin/               # Verwaltung
│   ├── assets/              # Statische Assets
│   ├── includes/            # Gemeinsame PHP-Funktionen und Layout
│   ├── uploads/             # Öffentlich erreichbare Bilder
│   ├── api.php
│   ├── card.php
│   └── install.php
└── README.md
```

Die Anwendung verwendet absolute URL-Pfade wie `/admin` und `/uploads`. Für die Installation eine eigene Domain oder Subdomain verwenden; Unterverzeichnis-Hosting benötigt Anpassungen am Code und am Routing.

### 3. Schreibrechte einrichten

`data/` und `public/uploads/` für den PHP-Prozess beschreibbar machen. `data/backups/` wird automatisch angelegt. Eigentümer und Rechte passend zum Hosting einrichten; pauschale `777`-Rechte sind nicht erforderlich.

### 4. Assistent aufrufen

`https://deine-domain.de/install` öffnen und Firmenangaben, E-Mail-Konfiguration sowie einen eigenen Admin-Benutzernamen und ein Passwort festlegen. Der Assistent verlangt mindestens acht Passwortzeichen und startet mit einer leeren Kontaktliste.

**Es gibt keinen vorgesehenen Standard-Login nach der Installation.** Anschließend mit den selbst vergebenen Zugangsdaten unter `/admin/login` anmelden.

## Verwendung

1. Unter `/admin` einen Kontakt anlegen und Kontaktinformationen sowie ein Profilbild hinterlegen.
2. Unter `/admin/datatypes` weitere Felder und ihre Reihenfolge konfigurieren.
3. Unter `/admin/settings` Branding, Sprache, Theme und Darstellung einstellen.
4. Die Kontaktkarte über ihre ID teilen oder den QR-Code herunterladen.

Die Kontaktverwaltung bietet Suche, Positionsfilter und 20, 50 oder 100 Einträge pro Seite. Beim Anlegen und Bearbeiten zeigt die Live-Vorschau die Karte samt ausgewähltem Foto; gespeichert wird erst beim Absenden. Bestehende Kontakt-IDs bleiben beim Umbenennen erhalten, sodass Links und bereits gedruckte QR-Codes weiterhin funktionieren. Links verwenden automatisch die Domain des aktuellen Aufrufs.

Neue Profilbilder werden auf maximal 768 Pixel, Logos auf maximal 1200 Pixel Kantenlänge begrenzt. JPEG-Fotos werden anhand ihrer EXIF-Ausrichtung gedreht; die gespeicherten WebP-Dateien enthalten keine ursprünglichen EXIF-Metadaten. Bereits gespeicherte Bilder bleiben erhalten.

| URL | Zweck |
| --- | --- |
| `/admin` | Kontaktverwaltung |
| `/{id}` | Öffentliche Kontaktkarte |
| `/{id}/vcard` | Kontakt als `.vcf` herunterladen |
| `/qr/{id}/png` | QR-Code als PNG anzeigen; mit `?download=1` herunterladen |
| `/qr/{id}/svg` | QR-Code als SVG anzeigen; mit `?download=1` herunterladen |
| `/admin/import_export` | CSV-Import und -Export |
| `/admin/backup` | ZIP-Backups verwalten |
| `/admin/api` | API aktivieren und Token neu erzeugen |

Die Startseite `/` leitet nach der Installation an die konfigurierte Ziel-URL weiter, sofern eine hinterlegt ist. Sie zeigt keine öffentliche Kontaktliste.

### CSV-Import

Am besten zunächst einen CSV-Export als Vorlage herunterladen. Das Trennzeichen ist ein **Semikolon**; die Spalten umfassen auch die konfigurierten Datentypen. Vorhandene Kontakte werden anhand der Spalte `id` aktualisiert. Vor größeren Importen ein Backup erstellen.

## REST API

Die API lässt sich unter `/admin/api` aktivieren. Ein zufälliger Token wird bei der Installation erzeugt; leere Tokens und der bekannte Platzhalter aus älteren Installationen werden automatisch ersetzt. Über **„Token neu erzeugen“** lässt er sich jederzeit wechseln.

Den Token über den Header `X-API-Token` übergeben:

```bash
curl --header "X-API-Token: DEIN_API_TOKEN" \
  https://deine-domain.de/api/contacts
```

| Methode | Endpoint | Funktion |
| --- | --- | --- |
| `GET` | `/api/contacts` | Alle Kontakte abrufen |
| `GET` | `/api/contacts/{id}` | Einzelnen Kontakt abrufen |
| `POST` | `/api/contacts` | Kontakt erstellen |
| `PUT` | `/api/contacts/{id}` | Kontaktfelder aktualisieren |
| `DELETE` | `/api/contacts/{id}` | Kontakt löschen |

Beispiel zum Anlegen eines Kontakts:

```bash
curl --request POST \
  --header "X-API-Token: DEIN_API_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{"vorname":"Erika","nachname":"Mustermann","position":"Design"}' \
  https://deine-domain.de/api/contacts
```

Ohne `id` erzeugt die Anwendung eine ID. `PUT` führt übergebene Felder mit dem bestehenden Kontakt zusammen. Die API antwortet mit JSON; typische Statuscodes sind `201` beim Anlegen, `401` bei ungültigem Token, `403` bei deaktivierter API und `404` bei unbekanntem Kontakt.

API und öffentliche Kartenrouten akzeptieren IDs aus zwei bis 20 Kleinbuchstaben oder Ziffern. Schreibanfragen erwarten ein JSON-Objekt mit höchstens 1 MB. Fehlerhaftes JSON liefert `400`, ungültige Felder oder IDs `422` und eine bereits vergebene ID `409`. Tokens ausschließlich als Header übergeben; Tokens in der URL werden nicht akzeptiert.

## Backups & Updates

Unter `/admin/backup` ZIP-Sicherungen erstellen, herunterladen und wiederherstellen. Eine Sicherung enthält:

- `data/config.json` einschließlich Zugangskonfiguration und API-Token
- `data/contacts.json`
- Dateien aus `public/uploads/` (ohne `.htaccess` und `.gitkeep`)

Die ZIP-Dateien werden zusätzlich unter `data/backups/` gespeichert. Sicherungen vertraulich behandeln und eine Kopie außerhalb des Webservers aufbewahren. Beim Restore werden enthaltene Konfiguration, Kontakte und gleichnamige Uploads überschrieben; zusätzliche bestehende Uploads werden nicht entfernt. Vor dem Restore wird automatisch eine Sicherung des aktuellen Zustands angelegt. Dauerhafte Login-Tokens werden nach einem Restore widerrufen.

Restore akzeptiert ZIP-Dateien bis 50 MB, höchstens 1.000 Einträge und maximal 100 MB entpackte Daten. Konfiguration, Kontakte und Bildinhalte werden vor Änderungen geprüft. Backups mit SVGs oder anderen nicht unterstützten Dateien werden abgelehnt; ältere SVG-Bilder vorher in PNG oder WebP umwandeln und ihre Referenzen aktualisieren.

Vor Updates ein Backup herunterladen. Beim Austausch der Programmdateien **`data/config.json`, `data/contacts.json`, `data/backups/` und `public/uploads/` erhalten**. Neue Konfigurationsschlüssel werden anhand von `config.sample.json` ergänzt.

## Betrieb & Sicherheit

- **Anmeldung:** Serverseitige Sessions mit zufälliger Session-ID, ID-Wechsel nach dem Login und acht Stunden Gültigkeit. „Angemeldet bleiben“ verwendet separate zufällige Tokens mit 30 Tagen Gültigkeit; gespeichert werden nur deren Hashes. Passwort- oder Benutzername-Änderungen entwerten frühere Anmeldungen.
- **Formulare:** CSRF-Tokens schützen Login, Installation und schreibende Adminaktionen. Löschen und Abmelden sind ausschließlich per POST möglich.
- **Bilder:** Neue Uploads akzeptieren PNG, JPEG und WebP mit passender MIME-Erkennung und gültigen Bildmaßen. Limits: 5 MB, 8.192 Pixel pro Seite und 25 Millionen Pixel insgesamt. SVG-Uploads sind gesperrt; Dateinamen enthalten einen zufälligen Anteil. `public/uploads/.htaccess` sperrt ausführbare Dateitypen und aktive Inhalte unter Apache.
- **Datensicherung:** Restore verwendet eine Liste erlaubter Dateipfade und prüft JSON-Struktur, eindeutige Kontakt-IDs, Bildinhalte und Größen. ZIP-Einträge werden nicht frei ins Dateisystem entpackt. Bei fehlgeschlagenen Änderungen wird der vorherige Zustand wiederhergestellt.
- **Speicherung:** Eine gemeinsame Dateisperre umfasst den gesamten Anfrageablauf; JSON wird über temporäre Dateien atomar ersetzt. Schreib- und JSON-Fehler werden erkannt. Dadurch werden Anfragen serialisiert; für große Installationen wäre eine Datenbank sinnvoll.
- **Cache:** Admin- und API-Antworten senden `Cache-Control: no-store`. Der Service Worker speichert ausschließlich das statische App-Icon und entfernt ältere vCard-Caches beim Aktivieren. Kontaktkarten werden nicht offline gespeichert.

Beim Update werden alte Login-Cookies nicht mehr akzeptiert; erneut anmelden. `data/remember.json` und `data/.storage.lock` gehören zu den privaten Laufzeitdateien und dürfen nicht öffentlich erreichbar sein. Für Uploads die PHP-Limits `upload_max_filesize` und `post_max_size` passend konfigurieren, für ZIP-Restore entsprechend höher als 50 MB.

Die Tests ersetzen keine Prüfung der konkreten Serverkonfiguration. HTTPS, ein DocumentRoot auf `public/` und aktivierte `.htaccess`-Regeln bleiben Voraussetzungen für den produktiven Betrieb.

Bootstrap und Bootstrap Icons werden über jsDelivr geladen. QR-Codes werden vollständig auf dem eigenen Server erzeugt; Kontakt-URLs werden dabei nicht an einen QR-Dienst übertragen. Für einen Betrieb ohne externe Asset-Abhängigkeiten auch Bootstrap und Icons lokal ausliefern.

## Tests

Mit einer lokalen PHP-Laufzeit einschließlich der oben genannten Erweiterungen:

```bash
find public tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/security.php
php tests/features.php
python3 tests/http_security.py
```

Die Tests verwenden temporäre Verzeichnisse und verändern keine bestehenden Kontakte oder Einstellungen. Der HTTP-Test startet einen lokalen PHP-Testserver mit mehreren Prozessen und prüft unter anderem 20 gleichzeitige API-Schreibzugriffe. Er benötigt ein Unix-System und Python 3. Optional lässt sich der Service Worker mit Node.js prüfen:

```bash
node tests/service_worker.mjs
```

Die Funktionstests prüfen Suche, Pagination, Domain-Erkennung, Bildgröße, Transparenz, alle acht EXIF-Ausrichtungen und QR-Code-Rundläufe. GitHub Actions prüft Syntax, Funktionen und Sicherheitsfälle mit PHP 8.0 und 8.4.

## Mitwirken & Lizenz

Fehler und Verbesserungsvorschläge können über die [GitHub Issues](https://github.com/Wiwaltill/vCard-CMS/issues) gemeldet werden. Hinweise zur Mitarbeit stehen in [CONTRIBUTING.md](CONTRIBUTING.md), die Community-Regeln in [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

Das Projekt steht unter der [MIT-Lizenz](LICENSE).
