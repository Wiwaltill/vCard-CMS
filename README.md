# Digital vCard CMS

Modernes PHP vCard CMS basierend auf v8.5 mit Mehrsprachigkeit, Themes, PWA und REST API.

## Features

- DE / EN Mehrsprachigkeit
- Theme-System (Classic & Glass)
- Light / Dark / Auto Modus
- Drag & Drop Sortierung
- CSV Import / Export
- REST API mit Token-Authentifizierung
- QR-Code Download als PNG & SVG
- PWA / Zum Homescreen hinzufügen
- Backup & Restore
- Responsive Bootstrap 5.3 Oberfläche

## Voraussetzungen

- PHP 8.0+
- Apache mit `mod_rewrite`
- Beschreibbares `/data` Verzeichnis

## Installation

1. Dateien auf den Webserver hochladen
2. `/data` Verzeichnis beschreibbar machen
3. CMS im Browser öffnen
4. Im Adminbereich anmelden
5. Einstellungen konfigurieren

## Admin Login

Standard Login:

```txt
Benutzername: admin
Passwort: admin
```

Das Passwort nach dem ersten Login unbedingt ändern.

## API

Authentifizierung per Header:

```http
X-API-Token: DEIN_API_TOKEN
```

Beispiel:

```bash
curl -H "X-API-Token: DEIN_API_TOKEN" \
https://deine-domain.de/api/contacts
```

## PWA

Wenn in den Einstellungen aktiviert, können Kontaktkarten direkt zum Homescreen auf iPhone und Android hinzugefügt werden.

## Themes & Darkmode

Verfügbare Modi:

- Hell
- Dunkel
- Auto (Systemeinstellung)

Der Wechsel ist direkt über die Admin-Navigation möglich.

## Backup

Backups können direkt im Adminbereich erstellt und wiederhergestellt werden.

## Lizenz

Private / angepasste Extended-Version basierend auf Digital vCard CMS v8.5.
