# pkg_benutzerbilder — Geschützte Benutzerbilder-Galerie für Joomla

Ein Joomla-Paket (4 / 5 / 6) das Shooting-Bilder geschützt pro Benutzer ausliefert.  
Jeder eingeloggte Benutzer sieht ausschließlich seine eigenen Fotos — kein direkter Dateizugriff möglich.

---

## Enthaltene Plugins

| Plugin | Gruppe | Funktion |
|--------|--------|----------|
| `plg_ajax_protectedimage` | ajax | Liefert Bilder nur für angemeldete, berechtigte Benutzer aus |
| `plg_content_benutzerimages` | content | Ersetzt `[benutzer_images]` im Artikel durch die Galerie |
| `plg_system_benutzerimages` | system | Legt fehlende Benutzerordner automatisch an (bei jedem Backend-Aufruf) |
| `plg_editors-xtd_benutzerimages` | editors-xtd | Editor-Button zum Einfügen des Platzhalters |

---

## Voraussetzungen

- Joomla 4.x / 5.x / 6.x
- PHP 8.1+
- Joomla-Benutzer mit Benutzernamen nach dem Schema `{Präfix}{Nummer}` (z. B. `folio1`, `Benutzer3`)

---

## Installation

1. `dist/pkg_benutzerbilder_v1.2.zip` in Joomla installieren  
   → Backend → System → Erweiterungen installieren → Paketdatei hochladen
2. Alle vier Plugins aktivieren  
   → Backend → Erweiterungen → Plugins → Gruppe filtern
3. Content-Plugin konfigurieren  
   → **Inhalt – Benutzerbilder** öffnen → Präfix und Anzahl eintragen

---

## Konfiguration

Alle Einstellungen befinden sich im Plugin **„Inhalt – Benutzerbilder"**:

| Parameter | Standard | Beschreibung |
|-----------|----------|--------------|
| Benutzer-Präfix | `Benutzer` | Präfix des Joomla-Benutzernamens (`folio`, `Benutzer`, …) |
| Anzahl Benutzer | `8` | Maximale Benutzernummer (1–99) |
| Platzhalter | `[benutzer_images]` | Platzhalter im Artikeltext |
| Beitragstitel | `Ihre Fotos` | Überschreibt den Artikel-Titel (leer = nicht überschreiben) |

---

## Ordnerstruktur auf dem Server

```
images/
  shootings/
    folio1/       ← Bilder für Benutzer „folio1"
      bild1.jpg
      bild2.png
    folio2/
      ...
```

Ordner werden beim ersten Backend-Aufruf eines Administrators **automatisch angelegt**.  
Erlaubte Bildformate: `jpg`, `jpeg`, `png`, `gif`, `webp`

---

## Verwendung im Artikel

Platzhalter in den gewünschten Artikel einfügen:

```
[benutzer_images]
```

Oder den **Editor-Button „Benutzerbilder"** unterhalb des Editors verwenden.

---

## Sicherheit

- Bilder werden nie direkt über den Webserver ausgeliefert
- Zugriff nur für eingeloggte Benutzer mit passendem Benutzernamen-Schema
- Pfad-Traversal-Schutz via `realpath()`
- MIME-Typ-Prüfung via `finfo`
- Jede erstellter Ordner enthält eine leere `index.html` (Joomla-Standard)

---

## Build

```powershell
.\build.ps1
```

Erzeugt `dist/pkg_benutzerbilder_v1.2.zip` — direkt in Joomla installierbar.

---

## Lizenz

GNU Affero General Public License v3.0 — siehe [LICENSE](LICENSE)

---

## Autor

Andreas Rottmann — [rottmanninfo.at](https://rottmanninfo.at)
