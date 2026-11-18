# Social Media Hub

Modularer, mehrmarkenfähiger Social-Media-Management-Hub für WordPress. Derzeit befindet sich das Projekt in der **Konzept- und UI-Prototypenphase**.

> **Aktueller Stand: V0.2 · 18. November 2026.** Es handelt sich **noch nicht** um ein WordPress-Plugin. Echte Plattformverbindungen, automatische Veröffentlichung und produktive Terminplanung sind **nicht implementiert**.

## Schnellstart – V0.2

1. [HTML-Prototyp V0.2 herunterladen](prototypes/v0.2/index.html).
2. Die Datei im Browser öffnen. Es ist kein WordPress nötig.
3. Unter **Veröffentlichen** einen Beitrag auswählen und alle Plattformvorschauen nebeneinander prüfen.
4. Kanäle einzeln aktivieren/deaktivieren; Varianten bearbeiten. Automatische Veröffentlichungen sind **nur simuliert**.
5. Für einen manuellen Kanal den Text kopieren, extern teilen und **erst danach** die tatsächliche Veröffentlichung bestätigen. Unter **Manuelle Aufgaben** werden offene und bestätigte Übergaben geführt.

Die Demo speichert Beispieldaten bei verfügbarer Speicherfreigabe lokal im Browser. V0.1-Demodaten werden beim ersten Öffnen von V0.2 in ein neues lokales Datenformat übernommen; die alte Kopie bleibt unverändert.

## Projektdateien

| Inhalt | Version | Beschreibung |
| --- | --- | --- |
| [Interaktiver HTML-Prototyp](prototypes/v0.2/index.html) | **V0.2** | Veröffentlichung, Plattformvarianten, Ziel-Schalter, manuelle Nachweise |
| [Veröffentlichungskonzept und Recherche](docs/veroeffentlichung-v0.2.md) | **V0.2** | Funktionsmodell, Vergleich mit existierenden Diensten, offene Aufgaben |
| [Architektur- und Ausbaukonzept](docs/architektur-v0.1.md) | V0.1 | Universeller Kern, modulare Erweiterbarkeit, Marken & Workflows |
| [HTML-Prototyp – vorheriger Stand](prototypes/v0.1/index.html) | V0.1 | Erster Dashboard- und Redaktionsprototyp |

## Grundprinzipien

- **Ein Beitragsverbund, viele Zielversionen:** Ein Beitrag kann verschiedene Texte, Medienvarianten, Termine und Veröffentlichungsstatus besitzen.
- **Mehrmarkenfähig:** Trennung eigener Marken, Kundenmarken, Konten, Medien, Team- und Freigaberollen.
- **Getrennte Veröffentlichungsziele:** Der spätere Sammelbefehl startet pro API-Ziel einen eigenen kontrollierbaren Auftrag.
- **Manuelle Übergabe integriert:** WhatsApp-Kanäle und andere manuelle Ziele bleiben in Planung, Status und Dokumentation sichtbar.
- **Capabilities statt Plattform-Hardcoding:** Die spätere Adapter-Schnittstelle entscheidet anhand verfügbarer Funktionen, ob Auto-/Manual-Publishing möglich ist.
- **Nachvollziehbarkeit:** Freigaben, Fehler, Wiederholungen und manuelle Bestätigungen mit Zeitstempeln und Ereignishistorie.
- **WordPress-kompatibel und eigenständig:** Als eigenes Plugin gedacht, später optional mit dem universellen Business Hub kombinierbar.

## Nächste Arbeitsschritte

1. Bedienoberfläche, Vorschauen und manuelle Übergabe anhand echter Alltagsszenarien verbessern.
2. Medienformate je Plattform, Textvalidierung, Alt-Texte, Rechts-/Freigabechecks und Publikationsstatus konzipieren.
3. Klares Server-Datenmodell, Berechtigungskonzept, Queue und OAuth-/Provider-Architektur spezifizieren.
4. Produktives WordPress-Plugin **erst nach gesonderter Freigabe** implementieren.

## Projektablage

Zusätzliche Screenshots, Sicherungen und Arbeitsunterlagen liegen in Google Drive: [Plugins / Social Media Hub / Entwicklung und Prototyp](https://drive.google.com/drive/folders/1RQmw3oXuCPuqezNxAiPWtlnTfMUz9Yb2).

**Sicherheit:** Dieses Repository ist öffentlich. Keine Tokens, Passwörter, privaten Kontaktlisten oder Kundendaten einchecken.

**Lizenz:** Noch nicht festgelegt.
