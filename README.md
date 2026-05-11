# Digital vCard CMS

Ein kleines, dateibasiertes PHP-System zur Erstellung digitaler Visitenkarten für Mitarbeitende.

Das System benötigt **keine Datenbank**, kein Docker und kein Framework. Es läuft auf klassischem PHP-Webspace mit Apache und `.htaccess`.

## Features

- Digitale Kontaktseiten pro Mitarbeiter
- URL nach Initialen oder eindeutigem Kürzel, zum Beispiel `/jd`
- Automatischer vCard-Download pro Kontakt
- Mitarbeiterfoto-Upload
- QR-Code-Popup auf der Kontaktseite
- Bootstrap-Adminbereich
- Login mit Cookie
- JSON-Dateien statt Datenbank
- Automatische E-Mail-Generierung nach Schema
- Optionale manuelle E-Mail-Überschreibung pro Kontakt
- Firmenlogo, Firmenfarbe, Links und Mail-Schema über Einstellungen pflegbar
- Impressum- und Datenschutzlinks auf der Kontaktseite
- `noindex`, `nofollow` und `robots.txt`
- Installationsassistent für die Ersteinrichtung

## Voraussetzungen

- PHP 8.0 oder neuer empfohlen
- Apache mit `mod_rewrite`
- Schreibrechte für:
  - `/data`
  - `/public/uploads`
- Domain oder Subdomain mit DocumentRoot auf `/public`

Eine Datenbank ist nicht erforderlich.

## Installation

### 1. Dateien hochladen

Lade das Projekt auf deinen Webspace hoch.

Empfohlene Struktur:

```text
/project
  /data
  /public
  README.md
```

### 2. DocumentRoot setzen

Die Domain oder Subdomain muss auf den Ordner zeigen:

```text
/project/public
```

Beispiel:

```text
https://vc.example.com
```

zeigt auf:

```text
/project/public
```

Wichtig: Der Ordner `/data` sollte **nicht direkt öffentlich erreichbar** sein.

### 3. Schreibrechte setzen

Die folgenden Ordner müssen durch PHP beschreibbar sein:

```text
/data
/public/uploads
```

Je nach Hosting reicht oft `755`. Falls Uploads oder Speichern nicht funktionieren, testweise `775` oder über das Hostingpanel Schreibrechte setzen.

### 4. Installationsassistent öffnen

Rufe im Browser auf:

```text
https://deine-domain.de/install
```

Der Assistent fragt ab:

- Firmenname
- Firmenfarbe
- Firmenlogo
- Logo-Link
- Impressum-Link
- Datenschutz-Link
- GitHub-Link optional
- E-Mail-Domain
- E-Mail-Schema
- Admin-Benutzername
- Admin-Passwort

Nach Abschluss wirst du zum Login weitergeleitet.

## Login

Nach der Installation:

```text
https://deine-domain.de/admin
```

oder:

```text
https://deine-domain.de/admin/login
```

## Kontakte verwalten

Im Adminbereich kannst du Kontakte:

- anlegen
- bearbeiten
- löschen
- Mitarbeiterfoto hochladen
- Mitarbeiterfoto löschen
- E-Mail automatisch generieren lassen
- E-Mail manuell überschreiben

## Kontakt-URLs

Jeder Kontakt erhält automatisch eine ID aus dem ersten Buchstaben von Vorname und Nachname.

Beispiel:

```text
Jane Doe → /jd
```

Falls eine ID schon existiert, wird automatisch erweitert:

```text
/jd
/jd2
/jd3
```

## vCard-Download

Für jeden Kontakt gibt es automatisch:

```text
/jd/vcard
```

Diese Datei wird als `.vcf` heruntergeladen.

## E-Mail Automatik

In den Einstellungen kann eine Mail-Domain festgelegt werden, zum Beispiel:

```text
@example.com
```

Außerdem kann das Schema vor dem `@` gewählt werden:

| Schema | Beispiel |
|---|---|
| Vorname | `jane@example.com` |
| Nachname | `doe@example.com` |
| Initialen | `jd@example.com` |
| Vorname.Nachname | `jane.doe@example.com` |
| Initial.Nachname | `j.doe@example.com` |
| Vorname_Nachname | `jane_doe@example.com` |
| VornameNachname | `janedoe@example.com` |

Wenn das Schema später geändert wird, aktualisieren sich automatisch alle Kontakte, bei denen keine manuelle E-Mail-Überschreibung aktiv ist.

## Manuelle E-Mail pro Kontakt

In den Kontakteinstellungen kann die Checkbox „Automatische E-Mail überschreiben“ aktiviert werden. Danach kann die E-Mail-Adresse manuell gesetzt werden.

Manuell gesetzte E-Mail-Adressen bleiben unverändert, auch wenn das globale Schema geändert wird.

## Einstellungen

Im Adminbereich unter „Einstellungen“ können geändert werden:

- Firmenname
- Firmenfarbe
- Firmenlogo
- Logo-Link
- GitHub-Link
- Impressum-Link
- Datenschutz-Link
- Mail-Domain
- Mail-Schema
- Admin-Benutzername
- Admin-Passwort

## Datenschutz und Suchmaschinen

Das System setzt auf den öffentlichen Kontaktseiten:

```html
<meta name="robots" content="noindex,nofollow,noarchive">
```

Zusätzlich enthält `/public/robots.txt`:

```text
User-agent: *
Disallow: /
```

Damit werden Suchmaschinen angewiesen, die Seiten nicht zu indexieren.

Hinweis: Technisch verhindert das keine Zugriffe. Wer eine Kontakt-URL kennt, kann sie weiterhin öffnen.

## Sicherheitshinweise

- Verwende ein starkes Admin-Passwort.
- Setze den DocumentRoot ausschließlich auf `/public`.
- Der Ordner `/data` sollte nicht öffentlich erreichbar sein.
- Halte PHP aktuell.
- Sichere regelmäßig:
  - `/data/config.json`
  - `/data/contacts.json`
  - `/public/uploads`

## Backup

Ein einfaches Backup besteht aus:

```text
/data/config.json
/data/contacts.json
/public/uploads
```

## Updates

Bei Updates sollten normalerweise folgende Dateien erhalten bleiben:

```text
/data/config.json
/data/contacts.json
/public/uploads
```

Neue Programmdateien können ersetzt werden. Vorher immer ein Backup erstellen.

## Projektstruktur

```text
/data
  config.json
  contacts.json

/public
  .htaccess
  robots.txt
  install.php
  card.php
  vcard.php
  /admin
    index.php
    login.php
    logout.php
    new.php
    edit.php
    delete.php
    settings.php
  /includes
    functions.php
    header.php
    footer.php
  /uploads
```

## Entwicklung

Das Projekt ist bewusst einfach gehalten:

- kein Composer
- kein Build-Prozess
- keine Datenbank
- keine externen PHP-Abhängigkeiten

Bootstrap und Bootstrap Icons werden per CDN geladen.

## Lizenz

Dieses Projekt kann frei angepasst und selbst gehostet werden. Ergänze bei Veröffentlichung gerne eine eigene Lizenzdatei, zum Beispiel MIT.
