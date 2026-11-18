# Social Media Hub

Modularer, mehrmarkenfähiger Social-Media-Management-Hub für WordPress – aktuell in der **Konzept- und Prototypenphase**.

> **Stand:** V0.1 · 18. November 2026. Das Projekt ist **noch kein installierbares WordPress-Plugin**. Es gibt noch keine echten Plattform-Anbindungen oder Veröffentlichungen.

## Schnellstart: HTML-Prototyp

1. [`prototypes/v0.1/index.html`](prototypes/v0.1/index.html) herunterladen.
2. Datei im Browser öffnen. Für diese Demo ist keine WordPress-Installation notwendig.
3. Die Funktionen mit Beispieldaten testen. Daten werden nur lokal im Browser gespeichert, nicht mit Social-Media-Konten synchronisiert.

## Inhalt

- [Interaktiver HTML-Prototyp V0.1](prototypes/v0.1/index.html) – Dashboard, Beitragsstudio, Marken-/Kanalverwaltung, Kalender und weitere Demoansichten.
- [Architektur- und Ausbaukonzept V0.1](docs/architektur-v0.1.md) – Datenmodell, Modulgrenzen, Provider-Adapter, Rechte, Veröffentlichung und Fehlerbehandlung.

## Leitlinien für die Weiterentwicklung

- **Universeller Kern:** Marken, Kanäle, Beiträge, Veröffentlichungen und Rechte werden unabhängig von einzelnen Netzwerken modelliert.
- **Mehrmarkenfähigkeit:** Trennung eigener Marken und Kundenmarken; später Rollen und Teams.
- **Plattform-Adapter:** Jede Plattform liefert eigene Fähigkeiten, Validierungen und Veröffentlichungsprozesse.
- **Zuverlässige Ausführung:** Geplante Posts je Zielkonto mit separatem Status, Queue, Retry und Audit.
- **Saubere Grenzen:** Eigenständiges WordPress-Plugin, optional integrierbar in einen größeren Business Hub.
- **Erweiterbarkeit:** Neue Ansichten, Provider und Automationen ohne Umbau des Kerns.

## Vorgesehene nächste Schritte

1. Prototyp und Bedienlogik prüfen.
2. Technisches Datenmodell, Modul-Schnittstellen und Rechtekonzept spezifizieren.
3. WordPress-Plugin-Grundgerüst **nach gesonderter Freigabe** implementieren.
4. Plattform-APIs schrittweise anschließen und absichern.

## Ablage

Die ergänzenden Desktop-/Mobil-Vorschauen, das Konzeptpaket und die Arbeitskopien liegen in Google Drive: [Plugins / Social Media Hub / Entwicklung und Prototyp](https://drive.google.com/drive/folders/1RQmw3oXuCPuqezNxAiPWtlnTfMUz9Yb2).

> Hinweis: Dieses Repository ist öffentlich. Keine Zugangsdaten, OAuth-Tokens oder privaten Kundendaten committen.

## Lizenz

Noch nicht festgelegt.
