# Digital vCard CMS

**Digitale Visitenkarten für dein Team – mit eigenem Branding und ohne Datenbank.**

Ein kleines PHP-CMS mit Adminbereich, Kontaktkarten, vCard-Downloads und QR-Codes. Kontakte und Einstellungen werden als JSON gespeichert; die Einrichtung erfolgt über einen Installationsassistenten.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![GitHub release](https://img.shields.io/github/v/release/Wiwaltill/vCard-CMS)](https://github.com/Wiwaltill/vCard-CMS/releases)

[Installation](#installation) · [Verwendung](#verwendung) · [REST API](#rest-api) · [Backups & Updates](#backups--updates) · [Wartung & Papierkorb](#wartung-und-papierkorb) · [Betrieb & Sicherheit](#betrieb--sicherheit) · [Tests](#tests)

## Funktionen

| Bereich | Möglichkeiten |
| --- | --- |
| Kontaktkarten | Responsive Karten, Profilbilder, Position und konfigurierbare Kontaktfelder |
| Teilen | Download als `.vcf`, lokal erzeugte QR-Codes als PNG oder SVG |
| Branding | Firmenname, Logo, Akzentfarbe und Links zu Impressum und Datenschutz |
| Darstellung | Themes **Classic**, **Minimal** und **Glass**; heller, dunkler oder systemabhängiger Modus |
| Sprache | Deutsch und Englisch, automatische Browser-Erkennung und Sprachwahl auf Kontaktkarten |
| Verwaltung | Kontakte suchen, nach Position filtern, seitenweise anzeigen und mit Live-Vorschau bearbeiten |
| Bilder | Verkleinerung im Browser für neue Uploads und bestehende Bilder; serverseitig geprüfte WebP-Ausgabe |
| Datenaustausch | CSV-Import und -Export sowie REST API zum Lesen und Schreiben von Kontakten |
| Wartung | Server-Check, Bildoptimierung mit vorherigem Backup und fortsetzbarem Fortschritt, Kontakt-Papierkorb |
| Datensicherung | ZIP-Backups mit Konfiguration, Kontakten und Uploads; Download und Wiederherstellung im Adminbereich |
| PWA | Web-App-Manifest und Service Worker zum Hinzufügen auf den Homescreen; Unterstützung abhängig von Browser und Gerät |

## Installation

### Voraussetzungen

- PHP **8.2 oder neuer** (empfohlen: **8.4**); für den Betrieb eine gepflegte PHP-Version einsetzen.
- Apache mit `mod_rewrite` und erlaubten `.htaccess`-Regeln, beispielsweise `AllowOverride All` für `public/`.
- PHP-Erweiterungen **fileinfo**, **gd** mit JPEG-, PNG- und WebP-Unterstützung, **mbstring** und **zip** (`ZipArchive`).
- **exif** wird zusätzlich für die Ausrichtung von JPEGs bei der direkten serverseitigen Verarbeitung empfohlen. Browser-vorbereitete Bilder benötigen keine EXIF-Verarbeitung in PHP.
- Für Live-Vorschau, Bildverkleinerung, Bildmigration und Änderungswarnungen einen aktuellen Browser mit aktiviertem JavaScript verwenden.
- Für browser-vorbereitete Bilder werden **128 MB PHP-Speicher oder mehr** empfohlen; Upload und Migration wurden mit **192 MB** und einem JPEG mit 24 Megapixeln geprüft. Das ist keine Garantie für die direkte Verarbeitung großer Originale in PHP.
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

### Fotos und Logos

Beim Auswählen eines Fotos verkleinert der Browser es vor dem Upload. Profilbilder werden auf höchstens **768 Pixel**, Firmenlogos auf **1200 Pixel** Kantenlänge begrenzt; kleine Bilder werden nicht vergrößert. Die Anzeige „Für den Upload vorbereitet“ bestätigt die Vorbereitung. Wird währenddessen gespeichert, wartet das Formular auf die Verarbeitung.

PHP prüft das vorbereitete Bild erneut und speichert es als WebP. Die optimierte Datei enthält keine ursprünglichen EXIF-Metadaten. Der Browser berücksichtigt beim Dekodieren die JPEG-Ausrichtung; bei direkter PHP-Verarbeitung übernimmt dies die EXIF-Erweiterung.

Scheitert die Vorbereitung im Browser, bleibt der ursprüngliche Upload ausgewählt und wird beim Speichern serverseitig geprüft. Scheitert auch die Optimierung eines gültigen Uploads, wird das Originalformat gespeichert. In diesem Fall bleiben ursprüngliche Metadaten erhalten und der Adminbereich zeigt die Ursache unter **Fehlerdetails**. Bestehende Bilder werden erst durch einen neuen Upload oder die Wartungsaktion verändert.

Das Original muss PNG, JPEG oder WebP sein, höchstens **5 MB**, **8192 Pixel pro Seite** und **25 Millionen Pixel insgesamt**. Die PHP-Uploadlimits müssen die tatsächlich gesendete Datei zulassen; für den Original-Fallback mindestens `upload_max_filesize = 5M` und `post_max_size = 6M` vorsehen. Für Backup-Restore sind entsprechend höhere Limits nötig.

| URL | Zweck |
| --- | --- |
| `/admin` | Kontaktverwaltung |
| `/admin/settings` | Einstellungen und Server-Check |
| `/admin/maintenance` | Bestehende Bilder optimieren |
| `/admin/trash` | Gelöschte Kontakte wiederherstellen oder endgültig löschen |
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
| `GET` | `/api/contacts` | Alle aktiven Kontakte abrufen |
| `GET` | `/api/contacts/{id}` | Einzelnen Kontakt abrufen |
| `POST` | `/api/contacts` | Kontakt erstellen |
| `PUT` | `/api/contacts/{id}` | Kontaktfelder aktualisieren |
| `DELETE` | `/api/contacts/{id}` | Kontakt in den Papierkorb verschieben |

Beispiel zum Anlegen eines Kontakts:

```bash
curl --request POST \
  --header "X-API-Token: DEIN_API_TOKEN" \
  --header "Content-Type: application/json" \
  --data '{"vorname":"Erika","nachname":"Mustermann","position":"Design"}' \
  https://deine-domain.de/api/contacts
```

Ohne `id` erzeugt die Anwendung eine ID. `PUT` führt übergebene Felder mit dem bestehenden Kontakt zusammen. Die API antwortet mit JSON; typische Statuscodes sind `201` beim Anlegen, `401` bei ungültigem Token, `403` bei deaktivierter API und `404` bei unbekanntem Kontakt.

API und öffentliche Kartenrouten akzeptieren IDs aus zwei bis 20 Kleinbuchstaben oder Ziffern. Schreibanfragen erwarten ein JSON-Objekt mit höchstens 1 MB. Fehlerhaftes JSON liefert `400`, ungültige Felder oder IDs `422` und eine bereits vergebene oder im Papierkorb reservierte ID `409`. Die API bietet keine Aktion zum Leeren des Papierkorbs; Wiederherstellung und endgültiges Löschen erfolgen im Adminbereich. Tokens ausschließlich als Header übergeben; Tokens in der URL werden nicht akzeptiert.

## Backups & Updates

Unter `/admin/backup` ZIP-Sicherungen erstellen, herunterladen und wiederherstellen. Eine Sicherung enthält:

- `data/config.json` einschließlich Zugangskonfiguration und API-Token
- `data/contacts.json` einschließlich der Kontakte im Papierkorb
- Dateien aus `public/uploads/` (ohne `.htaccess` und `.gitkeep`)

Die ZIP-Dateien werden zusätzlich unter `data/backups/` gespeichert. Sicherungen vertraulich behandeln und eine Kopie außerhalb des Webservers aufbewahren. Beim Restore werden enthaltene Konfiguration, Kontakte und gleichnamige Uploads überschrieben; zusätzliche bestehende Uploads werden nicht entfernt. Vor dem Restore wird automatisch eine Sicherung des aktuellen Zustands angelegt. Dauerhafte Login-Tokens werden nach einem Restore widerrufen.

Restore akzeptiert ZIP-Dateien bis 50 MB, höchstens 1.000 Einträge und maximal 100 MB entpackte Daten. Konfiguration, Kontakte und Bildinhalte werden vor Änderungen geprüft. Backups mit SVGs oder anderen nicht unterstützten Dateien werden abgelehnt; ältere SVG-Bilder vorher in PNG oder WebP umwandeln und ihre Referenzen aktualisieren.

Vor Updates ein Backup herunterladen. Beim Austausch der Programmdateien **private Laufzeitdateien in `data/` und alle Bilder in `public/uploads/` erhalten**. Dazu gehören `config.json`, `contacts.json`, `remember.json`, `.storage.lock`, `backups/` und gegebenenfalls `image-optimization.json` mit dem gespeicherten Migrationsfortschritt. Die `*.sample.json`-Dateien aus der neuen Version mit übertragen; fehlende Konfigurationsschlüssel werden anhand von `config.sample.json` ergänzt. Der alte konfigurierbare GitHub-Link wird entfernt; der Projektlink ist fest hinterlegt.

## Betrieb & Sicherheit

- **Anmeldung:** Serverseitige Sessions mit zufälliger Session-ID, ID-Wechsel nach dem Login und acht Stunden Gültigkeit. „Angemeldet bleiben“ verwendet separate zufällige Tokens mit 30 Tagen Gültigkeit; gespeichert werden nur deren Hashes. Passwort- oder Benutzername-Änderungen entwerten frühere Anmeldungen.
- **Formulare:** CSRF-Tokens schützen Login, Installation und schreibende Adminaktionen. Löschen und Abmelden sind ausschließlich per POST möglich.
- **Bilder:** Neue Uploads akzeptieren PNG, JPEG und WebP mit passender MIME-Erkennung und gültigen Bildmaßen. Limits: 5 MB, 8.192 Pixel pro Seite und 25 Millionen Pixel insgesamt. SVG-Uploads sind gesperrt; Dateinamen enthalten einen zufälligen Anteil. `public/uploads/.htaccess` sperrt ausführbare Dateitypen und aktive Inhalte unter Apache.
- **Datensicherung:** Restore verwendet eine Liste erlaubter Dateipfade und prüft JSON-Struktur, eindeutige Kontakt-IDs, Bildinhalte und Größen. ZIP-Einträge werden nicht frei ins Dateisystem entpackt. Bei fehlgeschlagenen Änderungen wird der vorherige Zustand wiederhergestellt.
- **Speicherung:** Eine gemeinsame Dateisperre umfasst den gesamten Anfrageablauf; JSON wird über temporäre Dateien atomar ersetzt. Schreib- und JSON-Fehler werden erkannt. Dadurch werden Anfragen serialisiert; für große Installationen wäre eine Datenbank sinnvoll.
- **Cache:** Admin- und API-Antworten senden `Cache-Control: no-store`. Der Service Worker speichert ausschließlich das statische App-Icon und entfernt ältere vCard-Caches beim Aktivieren. Kontaktkarten werden nicht offline gespeichert.

Login-Cookies aus früheren Versionen mit dem alten Anmeldeverfahren werden nicht akzeptiert; in diesem Fall erneut anmelden. `data/remember.json` und `data/.storage.lock` gehören zu den privaten Laufzeitdateien und dürfen nicht öffentlich erreichbar sein. Für Uploads die PHP-Limits `upload_max_filesize` und `post_max_size` passend konfigurieren, für ZIP-Restore entsprechend höher als 50 MB.

Die Tests ersetzen keine Prüfung der konkreten Serverkonfiguration. HTTPS, ein DocumentRoot auf `public/` und aktivierte `.htaccess`-Regeln bleiben Voraussetzungen für den produktiven Betrieb.

Bootstrap und Bootstrap Icons werden über jsDelivr geladen. QR-Codes werden vollständig auf dem eigenen Server erzeugt; Kontakt-URLs werden dabei nicht an einen QR-Dienst übertragen. Für einen Betrieb ohne externe Asset-Abhängigkeiten auch Bootstrap und Icons lokal ausliefern.

## Wartung und Papierkorb

### Server-Check

Unter **Einstellungen → Server-Check** stehen PHP-Version, Speicher- und Uploadlimits, Bildfunktionen und Schreibrechte. Ab `memory_limit = 128M` zeigt die Speicherprüfung **OK**. Der Hinweis bezieht sich auf im Browser vorbereitete Bilder; ein größeres Original kann bei direkter PHP-Verarbeitung trotzdem mehr Speicher benötigen.

Für ein Hosting mit höchstens **192 MB** die Browser-Verkleinerung nutzen. PHP dekodiert dann das kleine Ergebnis statt des hoch aufgelösten Originals. Falls die Browser-Verarbeitung nicht verfügbar ist, bleibt die serverseitige Speicherprüfung aktiv. Fehler erscheinen im Adminbereich und werden zusätzlich protokolliert.

### Bestehende Bilder optimieren

1. **Einstellungen → Bestehende Bilder optimieren** öffnen.
2. **„Optimierung starten / fortsetzen“** wählen. Vor einem neuen Durchlauf wird ein ZIP-Backup erstellt; ohne erfolgreiches Backup beginnt keine Verarbeitung.
3. Die Seite geöffnet lassen. Profilbilder, Bilder im Papierkorb und das Firmenlogo werden einzeln im Browser verkleinert und von PHP geprüft und gespeichert. Das Original wird erst nach erfolgreicher Speicherung ersetzt; gemeinsam verwendete Dateien bleiben erhalten, solange sie noch referenziert werden.
4. Fortschritt und Bericht prüfen. Bereits passende WebP-Bilder werden übersprungen. Fehlerhafte oder nicht verarbeitbare Bilder bleiben unverändert; der Bericht nennt den Grund.

**„Nach diesem Bild pausieren“** stoppt vor dem nächsten Schritt. Nach einer Unterbrechung oder einem Neuladen setzt derselbe Startknopf den gespeicherten Durchlauf fort. **„Aktuelles Bild überspringen“** lässt eine blockierende Datei unverändert und erlaubt das anschließende Fortsetzen. Inzwischen geänderte Bilder werden nicht überschrieben.

Unter **Fehlerdetails** stehen PHP-Meldungen oder bei ungültigen Antworten HTTP-Status und Antworttext. Der Abschluss unterscheidet neu optimierte, bereits optimierte und nicht verarbeitete Bilder. Nach Abschluss startet ein erneuter Klick einen neuen Durchlauf mit neuem Backup; damit lassen sich zuvor übersprungene Bilder erneut versuchen.

Das Backup kann unter `/admin/backup` heruntergeladen oder wiederhergestellt werden. Der Fortschritt steht separat in `data/image-optimization.json` und ist nicht Teil des ZIP-Backups.

### Papierkorb und Änderungswarnungen

Löschen im Adminbereich oder per API verschiebt Kontakte in den **Papierkorb**. Öffentliche Karte, vCard, QR-Code, API-Liste und CSV-Export blenden sie aus. Wiederherstellen erhält die ursprüngliche ID und das Foto. IDs im Papierkorb bleiben reserviert; CSV-Import und API dürfen sie nicht überschreiben.

Eine endgültige Löschung entfernt das Foto nur, wenn es nicht mehr verwendet wird. Es gibt keine automatische Leerung. Der Papierkorb ist Teil der Kontaktdatei und damit auch des ZIP-Backups. Bereits vor diesem Update endgültig gelöschte Kontakte können nur aus einem älteren Backup zurückgeholt werden.

Bei ungespeicherten Änderungen in Kontakteditor und Einstellungen warnt der Browser vor dem Verlassen der Seite.

## Tests

Mit einer lokalen PHP-Laufzeit einschließlich der oben genannten Erweiterungen:

```bash
find public tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/security.php
php tests/features.php
python3 tests/http_security.py
```

Die Tests verwenden temporäre Verzeichnisse und verändern keine bestehenden Kontakte oder Einstellungen. Der HTTP-Test startet einen lokalen PHP-Testserver mit mehreren Prozessen und prüft unter anderem 20 gleichzeitige API-Schreibzugriffe. Er benötigt ein Unix-System und Python 3. Die JavaScript-Prüfungen benötigen zusätzlich Node.js:

```bash
node tests/service_worker.mjs
node tests/unsaved_changes.mjs
node tests/image_maintenance.mjs
```

Die Funktionstests prüfen Suche, Pagination, Domain-Erkennung, Bildverarbeitung, alle acht EXIF-Ausrichtungen, QR-Code-Rundläufe, Papierkorb, Backups und Migrationsfortschritt. Die JavaScript-Tests prüfen Cache-Isolation, Änderungswarnungen und die Wiederaufnahme nach fehlerhaften Serverantworten.

Die tatsächliche Browser-Verkleinerung zusätzlich mit Foto-Upload und Bildmigration im Browser prüfen; sie wird durch diese Skripte nicht vollständig abgedeckt. GitHub Actions prüft Syntax, Funktionen und Sicherheitsfälle mit PHP 8.2 und 8.4.

## Updates der Abhängigkeiten

Unter **GitHub → Actions → Vendor update check** stehen die eingebundenen Versionen von `php-qrcode` und `php-settings-container` sowie deren neueste stabile Releases. Der Check ist wöchentlich für Montag um **07:23 UTC** geplant und lässt sich über **Run workflow** manuell starten. Neue Releases erscheinen als Warnung und in der Zusammenfassung des Laufs. Ein fehlgeschlagener API-Abruf wird als Fehler gemeldet, nicht als „aktuell“ gewertet. Der Check erstellt keine Issues und verändert keine Vendor-Dateien.

Die Versionsliste steht in [public/includes/vendor/packages.json](public/includes/vendor/packages.json). Bei einem Update Quelldateien, Lizenzhinweise, Versionsliste und Vendor-README gemeinsam aktualisieren. Neue Hauptversionen können eine neuere PHP-Version voraussetzen; vor der Übernahme die Anforderungen prüfen und die Tests ausführen.

**Dependabot** prüft die verwendeten GitHub Actions wöchentlich und erstellt gebündelte Update-Pull-Requests. Automatisches Zusammenführen ist nicht eingerichtet. Die kopierten PHP-Vendor-Dateien werden davon nicht aktualisiert.

Die Konfiguration muss auf GitHub im Standardbranch liegen; Actions und Dependabot müssen für das Repository aktiviert sein. In öffentlichen Repositories kann GitHub geplante Workflows nach 60 Tagen ohne Repository-Aktivität deaktivieren; bei Bedarf unter Actions wieder aktivieren. Eine Warnung im erfolgreichen Vendor-Check garantiert keine E-Mail-Benachrichtigung. Wer Releases direkt abonnieren möchte, kann in den Upstream-Repositories **Watch → Custom → Releases** wählen.

Den Versionsvergleich ohne Netzwerkzugriff testen:

```bash
python3 tests/vendor_updates.py
```

Den aktuellen Stand live abfragen:

```bash
python3 scripts/check_vendor_updates.py
```

## Mitwirken & Lizenz

Fehler und Verbesserungsvorschläge können über die [GitHub Issues](https://github.com/Wiwaltill/vCard-CMS/issues) gemeldet werden. Hinweise zur Mitarbeit stehen in [CONTRIBUTING.md](CONTRIBUTING.md), die Community-Regeln in [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

Das Projekt steht unter der [MIT-Lizenz](LICENSE). Die mitgelieferten QR-Bibliotheken haben eigene Lizenzhinweise in [public/includes/vendor/README.md](public/includes/vendor/README.md).
