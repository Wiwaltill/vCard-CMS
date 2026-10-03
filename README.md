# Digital vCard CMS

**Digitale Visitenkarten für dein Team – mit eigenem Branding und ohne Datenbank.**

Ein kleines PHP-CMS mit Adminbereich, Kontaktkarten, vCard-Downloads und QR-Codes. Kontakte und Einstellungen werden als JSON gespeichert; die Einrichtung erfolgt über einen Installationsassistenten.

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![GitHub release](https://img.shields.io/github/v/release/Wiwaltill/vCard-CMS)](https://github.com/Wiwaltill/vCard-CMS/releases)

[Installation](#installation) · [Verwendung](#verwendung) · [REST API](#rest-api) · [Backups & Updates](#backups--updates) · [Betrieb & bekannte-grenzen](#betrieb--bekannte-grenzen)

> **Aktueller Sicherheitshinweis:** Die Admin-Anmeldung verwendet derzeit einen statischen, berechenbaren Cookie-Wert. Vor einem öffentlich erreichbaren Betrieb muss die Anmeldung abgesichert werden. Weitere offene Punkte stehen unter [Betrieb & bekannte Grenzen](#betrieb--bekannte-grenzen).

## Funktionen

| Bereich | Möglichkeiten |
| --- | --- |
| Kontaktkarten | Responsive Karten, Profilbilder, Position und konfigurierbare Kontaktfelder |
| Teilen | Download als `.vcf`, QR-Code-Anzeige und QR-Downloads als PNG oder SVG |
| Branding | Firmenname, Logo, Akzentfarbe und Links zu Impressum und Datenschutz |
| Darstellung | Themes **Classic**, **Minimal** und **Glass**; heller, dunkler oder systemabhängiger Modus |
| Sprache | Deutsch und Englisch, automatische Browser-Erkennung und Sprachwahl auf Kontaktkarten |
| Verwaltung | Kontakte anlegen und bearbeiten, eigene Datentypen und deren Reihenfolge konfigurieren |
| Datenaustausch | CSV-Import und -Export sowie REST API zum Lesen und Schreiben von Kontakten |
| Datensicherung | ZIP-Backups mit Konfiguration, Kontakten und Uploads; Download und Wiederherstellung im Adminbereich |
| PWA | Web-App-Manifest und Service Worker zum Hinzufügen auf den Homescreen; Unterstützung abhängig von Browser und Gerät |

## Installation

### Voraussetzungen

- PHP **8.0 oder neuer**; für den Betrieb eine gepflegte PHP-Version einsetzen.
- Apache mit `mod_rewrite` und erlaubten `.htaccess`-Regeln, beispielsweise `AllowOverride All` für `public/`.
- PHP-Erweiterung **ZipArchive** für Backup und Restore.
- Schreibzugriff des PHP-Prozesses auf `data/` und `public/uploads/`.
- HTTPS für den produktiven Betrieb und die Service-Worker-Funktion.

Es sind weder eine Datenbank noch ein Node.js-Build oder eine Composer-Installation erforderlich. Für serverseitige QR-Downloads wird ausgehender HTTPS-Zugriff über `file_get_contents` benötigt (`allow_url_fopen`); bei einem Fehler erfolgt eine Weiterleitung zum QR-Dienst.

### 1. Projekt bereitstellen

```bash
git clone https://github.com/Wiwaltill/vCard-CMS.git
cd vCard-CMS
```

Alternativ das Repository herunterladen und auf den Server kopieren. Auch versteckte Dateien wie `public/.htaccess` müssen mit übertragen werden.

### 2. Webroot konfigurieren

Den **DocumentRoot auf `public/`** setzen. `data/` liegt außerhalb des öffentlich erreichbaren Verzeichnisses und enthält später auch Passwort-Hash und API-Token.

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

| URL | Zweck |
| --- | --- |
| `/admin` | Kontaktverwaltung |
| `/{id}` | Öffentliche Kontaktkarte |
| `/{id}/vcard` | Kontakt als `.vcf` herunterladen |
| `/qr/{id}/png` | QR-Code als PNG herunterladen |
| `/qr/{id}/svg` | QR-Code als SVG herunterladen |
| `/admin/import_export` | CSV-Import und -Export |
| `/admin/backup` | ZIP-Backups verwalten |
| `/admin/api` | API aktivieren und Token neu erzeugen |

Die Startseite `/` leitet nach der Installation an die konfigurierte Ziel-URL weiter, sofern eine hinterlegt ist. Sie zeigt keine öffentliche Kontaktliste.

### CSV-Import

Am besten zunächst einen CSV-Export als Vorlage herunterladen. Das Trennzeichen ist ein **Semikolon**; die Spalten umfassen auch die konfigurierten Datentypen. Vorhandene Kontakte werden anhand der Spalte `id` aktualisiert. Vor größeren Importen ein Backup erstellen.

## REST API

Die API lässt sich unter `/admin/api` aktivieren. Dort vor der ersten Verwendung einen eigenen Token über **„Token neu erzeugen“** erstellen; die Beispielkonfiguration enthält einen bekannten Platzhalter.

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

Die öffentliche Kartenroute akzeptiert derzeit IDs aus zwei bis sechs Kleinbuchstaben oder Ziffern. Bei selbst vergebenen IDs diese Grenze berücksichtigen; die API-Einzelroute erlaubt bis zu 20 Zeichen.

## Backups & Updates

Unter `/admin/backup` ZIP-Sicherungen erstellen, herunterladen und wiederherstellen. Eine Sicherung enthält:

- `data/config.json` einschließlich Zugangskonfiguration und API-Token
- `data/contacts.json`
- Dateien aus `public/uploads/`

Die ZIP-Dateien werden zusätzlich unter `data/backups/` gespeichert. Sicherungen vertraulich behandeln und eine Kopie außerhalb des Webservers aufbewahren. Beim Restore werden enthaltene Konfiguration, Kontakte und gleichnamige Uploads überschrieben; zusätzliche bestehende Uploads werden nicht entfernt.

Vor Updates ein Backup herunterladen. Beim Austausch der Programmdateien **`data/config.json`, `data/contacts.json`, `data/backups/` und `public/uploads/` erhalten**. Neue Konfigurationsschlüssel werden anhand von `config.sample.json` ergänzt.

## Betrieb & bekannte Grenzen

Die aktuelle Implementierung hat offene Punkte, die vor einem öffentlichen Betrieb behoben werden sollten:

- **Admin-Authentifizierung:** Der Login-Cookie ist statisch und lässt sich ohne Passwort berechnen. Serverseitige Sessions und separate zufällige Tokens für dauerhafte Anmeldungen sind erforderlich.
- **Schreibaktionen:** CSRF-Schutz fehlt im Adminbereich; Kontaktlöschung ist derzeit per GET möglich.
- **API-Token:** Der bekannte Platzhalter aus der Beispielkonfiguration wird bei der Installation nicht automatisch ersetzt. Einen eigenen Token erzeugen oder die API deaktivieren.
- **Uploads und Restore:** Bilder werden anhand ihrer Dateiendung geprüft, SVG ist zugelassen. MIME-/Inhaltsprüfung, Größenlimits und die Validierung von Backup-Inhalten fehlen.
- **Offline-Cache:** Der Service Worker speichert derzeit GET-Antworten auch für Admin- und API-Aufrufe. Den Cache auf geeignete öffentliche Ressourcen begrenzen.
- **JSON-Speicherung:** Schreibvorgänge erfolgen ohne Dateisperre, atomaren Austausch oder Fehlerprüfung. Gleichzeitige Änderungen können Daten verlieren; das Projekt eignet sich daher vorerst für kleine Installationen mit wenigen Schreibzugriffen.

Bootstrap und Bootstrap Icons werden über jsDelivr geladen. QR-Codes werden durch `api.qrserver.com` erzeugt; dabei wird die URL der Kontaktkarte an diesen Dienst übertragen. Für einen Betrieb ohne diese externen Abhängigkeiten Assets lokal ausliefern und QR-Codes lokal erzeugen.

## Mitwirken & Lizenz

Fehler und Verbesserungsvorschläge können über die [GitHub Issues](https://github.com/Wiwaltill/vCard-CMS/issues) gemeldet werden. Hinweise zur Mitarbeit stehen in [CONTRIBUTING.md](CONTRIBUTING.md), die Community-Regeln in [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

Das Projekt steht unter der [MIT-Lizenz](LICENSE).
