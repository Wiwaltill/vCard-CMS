# Digital vCard CMS
[![Wiwaltill - vCard-CMS](https://img.shields.io/static/v1?label=Wiwaltill&message=vCard-CMS&color=blue&logo=github)](https://github.com/Wiwaltill/vCard-CMS "Go to GitHub repo")
[![stars - vCard-CMS](https://img.shields.io/github/stars/Wiwaltill/vCard-CMS?style=social)](https://github.com/Wiwaltill/vCard-CMS)
[![forks - vCard-CMS](https://img.shields.io/github/forks/Wiwaltill/vCard-CMS?style=social)](https://github.com/Wiwaltill/vCard-CMS)

[![GitHub release](https://img.shields.io/github/v/release/Wiwaltill/vCard-CMS)](https://github.com/Wiwaltill/vCard-CMS/releases/)
[![License](https://img.shields.io/github/license/Wiwaltill/vCard-CMS)](https://github.com/Wiwaltill/vCard-CMS/blob/main/LICENSE)
[![issues - vCard-CMS](https://img.shields.io/github/issues/Wiwaltill/vCard-CMS)](https://github.com/Wiwaltill/vCard-CMS/issues)


Modernes PHP vCard CMS zur Erstellung digitaler Visitenkarten mit Adminbereich, Mehrsprachigkeit, REST API, PWA und Theme-System. Optimiert für einfache Installation ohne Datenbank.

---

# Funktionen

## Kontaktkarten

- Digitale vCards erstellen
- Eigene Profilbilder & Coverbilder
- Kontaktinformationen verwalten
- Social Media Links
- Telefon, Mail, Webseite
- Standort & Google Maps
- Download als `.vcf`
- QR-Code Anzeige
- Responsive Darstellung für Mobilgeräte

## Mehrsprachigkeit

- Deutsch & Englisch
- Automatische Browser-Erkennung
- Sprachumschaltung im Frontend & Adminbereich

## Themes & Darstellung

- Classic Theme
- Glass Theme
- Light Mode
- Dark Mode
- Auto-Modus nach Systemeinstellung

## PWA / Homescreen

- Als App installierbar
- „Zum Homescreen hinzufügen“
- Kontaktkarten starten direkt in der jeweiligen Karte
- Android & iPhone kompatibel

## QR-Code Funktionen

- QR-Code Anzeige
- Download als PNG
- Download als SVG

## Adminbereich

- Kontaktkarten verwalten
- Datentypen verwalten
- Drag & Drop Sortierung
- Theme Auswahl
- Spracheinstellungen
- Darkmode Umschaltung
- API Verwaltung
- Backup & Restore

## CSV Funktionen

- CSV Import
- CSV Export
- Kontakte schnell importieren/exportieren

## REST API

- JSON API
- Token-Authentifizierung
- Kontakte abrufen
- Kontaktinformationen ausgeben

Authentifizierung:

```http
X-API-Token: DEIN_API_TOKEN
```

Beispiel:

```bash
curl -H "X-API-Token: DEIN_API_TOKEN" \
https://deine-domain.de/api/contacts
```

## Backup & Restore

- Backups direkt im Adminbereich erstellen
- Restore per Upload
- JSON-basierte Datensicherung

---

# Voraussetzungen

- PHP 8.0 oder höher
- Apache Webserver
- `mod_rewrite` aktiviert
- Beschreibbares `/data` Verzeichnis

---

# Installation

## 1. Dateien hochladen

Projekt auf den Webserver kopieren.

## 2. Schreibrechte setzen

Folgende Ordner müssen beschreibbar sein:

```txt
/data
/backups
/uploads
```

## 3. Webseite öffnen

CMS im Browser aufrufen.

## 4. Admin Login

Standard Zugang:

```txt
Benutzername: admin
Passwort: admin
```

Passwort anschließend ändern.

---

# Projektstruktur

```txt
/admin          → Adminbereich
/api            → REST API
/assets         → CSS, JS, Bilder
/data           → Kontakte & Einstellungen
/themes         → Themes
/uploads        → Uploads
/backups        → Backups
```

---

# Themes

## Classic

Klassisches Bootstrap Layout.

## Minimal

Minimale Fassung vom Bootstrap Layout.

## Glass

Moderne Glasoptik mit Blur-Effekten und Transparenz.

---

# Darkmode

Unterstützt drei Modi:

- Hell
- Dunkel
- Auto (Systemeinstellung)

Basierend auf Bootstrap 5.3 `data-bs-theme`.

---

# Sicherheit

- Passwortgeschützter Adminbereich
- API Token Schutz
- JSON-basierte Datenspeicherung
- Keine Datenbank notwendig
