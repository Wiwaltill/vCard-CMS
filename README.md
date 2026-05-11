# Digital vCard CMS

Ein kleines, dateibasiertes PHP-System zur Erstellung digitaler Visitenkarten für Mitarbeitende.

Das System benötigt **keine Datenbank**, kein Docker und kein Framework. Es läuft auf klassischem PHP-Webspace mit Apache und `.htaccess`.

## Features

- Digitale Kontaktseiten pro Mitarbeiter
- Konfigurierbare Datentypen für Kontaktinformationen
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
- Datentypen aktivieren, sortieren, hinzufügen und löschen
- Impressum- und Datenschutzlinks auf der Kontaktseite
- `noindex`, `nofollow` und `robots.txt`
- Installationsassistent für die Ersteinrichtung
- Root-Domain kann auf eine frei definierbare Website weiterleiten
- Schöne 404-Seite für nicht gefundene Kontakte mit Sammelmail-Button

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
- Startseiten-Weiterleitung
- Impressum-Link
- Datenschutz-Link
- Sammelmail für nicht gefundene Kontakte
- GitHub-Link optional
- E-Mail-Domain
- E-Mail-Schema
- Admin-Benutzername
- Admin-Passwort

Der Assistent erkennt aus der aufgerufenen Host-Domain automatisch eine Basis-Domain. Beispiel:

```text
vc.example.com → example.com
```

Diese Basis-Domain wird als Vorschlag für Mail-Domain, Impressum, Datenschutz, Startseiten-Link und Sammelmail verwendet.

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

## Startseite

Wenn die Root-Domain aufgerufen wird, zum Beispiel:

```text
https://vc.example.com
```

wird auf die in den Einstellungen definierte Website weitergeleitet.

## Nicht gefundene Kontakte

Wenn ein Kontakt nicht existiert, wird eine eigene 404-Seite angezeigt:

```text
Kontakt nicht gefunden
Der gesuchte Kontakt konnte nicht gefunden werden.
```

Dort erscheint ein Button „Schreiben Sie uns“. Die Zieladresse ist in den Einstellungen als Sammelmail pflegbar.

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
- Startseiten-Weiterleitung
- Sammelmail für nicht gefundene Kontakte
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

Die Dateien `config.json` und `contacts.json` sind Laufzeitdaten und werden bewusst nicht versioniert.

## Updates

Bei Updates sollten normalerweise folgende Dateien erhalten bleiben:

```text
/data/config.json
/data/contacts.json
/public/uploads
```

Neue Programmdateien können ersetzt werden. Vorher immer ein Backup erstellen.

Das Repository enthält nur:

```text
/data/config.sample.json
/data/contacts.sample.json
```

Diese Sample-Dateien dienen als Vorlage für Neuinstallationen und Updates.

Wenn bei einem späteren Release neue Einstellungen in `config.sample.json` ergänzt werden, erkennt das System fehlende Keys automatisch und übernimmt sie in die bestehende `config.json`, ohne vorhandene Werte zu überschreiben.

## Projektstruktur

```text
/data
  config.sample.json
  contacts.sample.json
  config.json       # wird bei Installation erzeugt und ist nicht im Git-Repo
  contacts.json     # wird bei Installation erzeugt und ist nicht im Git-Repo

/public
  .htaccess
  robots.txt
  install.php
  index.php
  contact-not-found.php
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


## Verhalten vor Abschluss der Installation

Solange `installed` in `/data/config.json` nicht auf `true` steht, leiten die PHP-Einstiegspunkte automatisch auf `/install` weiter. Die `.htaccess` bleibt dabei bewusst kompatibel mit Shared-Hosting-Umgebungen und verwendet keine Apache-`expr`- oder `file()`-Bedingungen.


## Fehlerbehebung: Internal Server Error 500

Falls direkt nach dem Upload ein 500-Fehler erscheint, liegt das bei Shared-Hosting meistens an einer nicht unterstützten `.htaccess`-Anweisung.

Diese Version verwendet deshalb nur klassische `RewriteRule`/`RewriteCond`-Regeln und verschiebt die Installationsprüfung in PHP.

Prüfe außerdem:

- `mod_rewrite` ist aktiv
- `AllowOverride` erlaubt `.htaccess`
- DocumentRoot zeigt auf `/public`
- PHP kann `/data` und `/public/uploads` beschreiben


## Fehlerbehebung: Zu viele Umleitungen bei `/install`

Falls `/install` eine Umleitungsschleife erzeugt, prüfe die `.htaccess`. Wichtig ist, dass existierende Dateien und Ordner vor dem Fallback ausgeschlossen werden:

```apache
RewriteCond %{REQUEST_FILENAME} -f [OR]
RewriteCond %{REQUEST_FILENAME} -d
RewriteRule ^ - [L]
```

Diese Version enthält diese Regel bereits.

- Firmenlogo auf der 404-Kontaktseite verweist jetzt auf den konfigurierten Logo-Link


## Sample-Dateien und Updates

Für GitHub-Releases werden keine echten Installationsdaten ausgeliefert.

Enthalten sind nur:

```text
/data/config.sample.json
/data/contacts.sample.json
```

Bei der ersten Installation werden daraus automatisch erzeugt:

```text
/data/config.json
/data/contacts.json
```

Diese echten Dateien sind in `.gitignore` ausgeschlossen, damit Updates keine bestehenden Daten überschreiben.

### Automatische Config-Migration

Wenn in einem Update neue Felder in `config.sample.json` hinzukommen, werden diese beim nächsten Aufruf automatisch in die bestehende `config.json` übernommen.

Vorhandene Werte bleiben erhalten.

Beispiel:

```json
{
  "new_setting": "default value"
}
```

Wenn `new_setting` in der bestehenden Installation fehlt, wird es ergänzt. Wenn es bereits existiert, bleibt der bestehende Wert unverändert.


## Datentypen

Im Adminmenü gibt es den Bereich **Datentypen**.

Dort kann festgelegt werden, welche Kontaktinformationen auf der öffentlichen Kontaktseite erscheinen.

Möglich sind zum Beispiel:

- E-Mail
- Telefon
- Festnetz
- Fax
- Website
- LinkedIn
- Freitext

Jeder Datentyp hat:

- Bezeichnung
- Typ: Text, Telefon, E-Mail oder URL
- Sortierung
- Aktiv/Inaktiv
- optionales vCard-Feld
- Löschen, sofern es kein Systemfeld ist

Die Reihenfolge auf der Kontaktseite richtet sich nach dem Sortierwert.

### Systemfelder

Die Standardfelder `Telefon` und `E-Mail` sind Systemfelder. Sie können ausgeblendet und umbenannt, aber nicht gelöscht werden.

### Eigene Felder

Neue Felder werden im Kontaktformular automatisch ergänzt. Werte werden im jeweiligen Kontakt unter `fields` gespeichert.

Beispiel:

```json
{
  "fields": {
    "fax": "+49 123 456789",
    "linkedin": "https://linkedin.com/in/example"
  }
}
```


### vCard-Felder

Im Bereich Datentypen kann optional ein vCard-Feld gesetzt werden, zum Beispiel:

```text
TEL;TYPE=WORK
EMAIL
URL;TYPE=LinkedIn
```

Weitere Informationen zum vCard-Format gibt es hier:

```text
https://de.wikipedia.org/wiki/VCard
```

### Social Media

Datentypen können als **Social Media** angelegt werden. Unterstützte Plattformen:

- Facebook
- Instagram
- LinkedIn
- TikTok
- X
- YouTube
- Xing

Im Kontaktformular reicht dann der Username. Das System erzeugt daraus automatisch die passende Profil-URL.

Beispiele:

```text
Instagram: max.mustermann → https://www.instagram.com/max.mustermann
LinkedIn: max-mustermann → https://www.linkedin.com/in/max-mustermann
TikTok: maxmustermann → https://www.tiktok.com/@maxmustermann
```

Alternativ kann auch direkt eine vollständige URL eingetragen werden.


### URL- und Social-Link-Ausgabe

URL- und Social-Media-Datentypen öffnen auf der Kontaktseite automatisch in einem neuen Tab.

Für Social-Media-Datentypen kann im Kontaktformular entweder ein Username oder eine vollständige URL eingetragen werden. In der Kontaktseite und in der vCard wird daraus immer eine vollständige URL erzeugt.


### Xing

Für Xing kann einfach der Profilname eingetragen werden:

```text
max_mustermann
```

Das System erzeugt daraus automatisch:

```text
https://www.xing.com/profile/max_mustermann
```


### Hinweis zu Social-Media-Datentypen

Social-Media-Datentypen werden nicht automatisch angelegt. Sie können bei Bedarf im Adminbereich unter **Datentypen** hinzugefügt werden.

Gelöschte Datentypen bleiben gelöscht. Die Update-Migration ergänzt neue allgemeine Config-Keys, überschreibt aber nicht die individuell gepflegte `data_types`-Liste.

- Fix: Social-Media-Plattformen können wieder über „Datentypen“ hinzugefügt werden, ohne standardmäßig angelegt zu sein.


### Social Media Datentypen anlegen

Bei Social Media muss keine separate Bezeichnung mehr eingegeben werden. Es reicht, den Typ **Social Media** zu wählen und danach die Plattform auszuwählen.

Das System setzt automatisch:

- Bezeichnung, z.B. `Instagram`
- Key, z.B. `instagram`
- vCard-Feld, z.B. `URL;TYPE=Instagram`
