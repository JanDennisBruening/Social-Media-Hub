# Social Media Hub – Architektur- und Ausbaukonzept V0.1

**Stand:** 18. November 2026  
**Status:** Interaktiver HTML-Konzeptprototyp, **noch kein WordPress-Plugin**  
**Ziel:** Eigenständig verwendbares WordPress-Modul, optional integrierbar in einen universellen Business Hub. Mehrmarkenfähig und ausbaubar ohne Umbau der Kernobjekte.

## 1. Leitprinzipien

1. **Markenunabhängiger Kern:** Eine Installation verwaltet mehrere Marken, Kunden, Benutzer und Kanäle. Inhalte gehören zu einer Marke; Berechtigungen sind später zusätzlich auf Rollen und Marken einzuschränken.
2. **Kanäle als Adapter:** Plattformlogik nicht in Beitragsformular, Kalender oder Geschäftslogik integrieren. Ein Provider meldet eigene Fähigkeiten, Anforderungen, Fehlercodes und Berechtigungen.
3. **Ein Beitrag, mehrere Zielkonten:** Gemeinsamer redaktioneller Inhalt; optionale kanalindividuelle Text- und Medienvarianten; pro Zielkonto eine eigene Veröffentlichung und ein eigener Zustellstatus.
4. **Zuverlässige asynchrone Zustellung:** Planung, Freigabe und Veröffentlichung sind getrennte Prozesse mit Warteschlange, Wiederholungsregeln, Token-Aktualisierung und Protokollen.
5. **Offene Erweiterungsgrenzen:** Neue Provider, Ansichten, Automationen und spätere Integrationen dürfen die bestehende Persistenz nicht unkontrolliert verändern.
6. **Datenhoheit und Datenschutz:** Nutzer- und Markentrennung, nur notwendige Kontodaten; nachvollziehbare Einwilligung, Löschung, Export und Datenaufbewahrung.
7. **Portabilität:** Das Social-Media-Modul funktioniert separat; Business-Hub-Integration erfolgt später über dokumentierte Schnittstellen, nicht über direkte Querverweise in Tabellen.

## 2. Funktionslandkarte

| Modul | V0.1 HTML | Künftiges WordPress-Plugin |
| --- | --- | --- |
| Dashboard | Nutzbare Demoansicht | Echte Aggregationen, Filter, KPIs |
| Marken / Profile | Marken und Demo-Konten hinzufügen | Nutzerrechte, OAuth, Token- und Verbindungsstatus |
| Beitrag / Varianten | Speichern, kopieren, bearbeiten, planen | Validierung pro Plattform, Entwürfe, Freigabesperren |
| Redaktionskalender | Monatsansicht, Tagesplanung | Zeitzonen, Kampagnen, Wochenansicht, Drag-and-drop |
| Medien | Lokale Metadateneinträge | WordPress-Mediathek, Renditions, Video-Transcoding |
| Inbox | Beispielkommentare, lokale Antwortnotizen | Plattformabhängige Empfangs- und Antwortfunktionen |
| Analytics | Mockwerte und lokale Redaktion-Kennzahlen | Plattformdaten, Zeitreihen, Metriknormalisierung |
| Automationen | Demoregeln an/aus | Eventbus, Trigger, Regeln, Job-Ausführung |
| Audit | Lokale Nutzeraktionen | Server-Audit, Zustellversuche, Berechtigungsänderungen |
| Datenexport | JSON-Demoimport/-export | Geprüfter DSGVO-Export, Backups, Migrationen |

**Wichtig:** Das Vorhandensein einer Plattform im Verzeichnis ist keine Zusage für eine bestimmte API-Funktion. Schreiben, Lesen, Analytics, Kommentare, Reels/Stories oder Direktnachrichten müssen pro Provider und Accounttyp einzeln geprüft werden.

## 3. Kernobjekte – später serverseitig getrennt

- **Workspace**: Logischer Mandantenbereich; kann mehrere Marken umfassen. Optionaler Ausbau für Agenturen und Teams.
- **Brand**: Marke mit Name, Darstellung und zugewiesenen Mitarbeitenden; ihr gehören Inhalte und Konten.
- **ChannelAccount**: Account, Plattform, Marken-Zuordnung, öffentliche Profilinformationen, Verbindungsstatus, Berechtigungen; sensible OAuth-Tokens separat und verschlüsselt.
- **ContentItem**: Interner Titel, gemeinsamer Haupttext, Inhaltstyp, Status, Autor, Marke, Zeitstempel.
- **ContentVariant**: Abweichende Kopie, Hashtags, Link, Format- und Medienauswahl pro Zielkonto oder definierter Zielgruppe.
- **MediaAsset**: Dateireferenz, Eigentümer/Marke, Typ, Metadaten, Versionen, Lizenz/Quelle, Barrierefreiheit (z. B. Alt-Text).
- **Publication**: Geplante Veröffentlichung eines ContentItem auf genau einem ChannelAccount, mit geplanten Zeitpunkt, Plattform-Validierung und Status.
- **DeliveryAttempt**: Einzelner Versandversuch mit Zeit, Anbieterantwort, Fehlercode, Wiederholbarkeit, externer Post-ID und Korrelations-ID.
- **Approval**: Freigabeanforderung, berechtigte Prüfer, Entscheidung, Version, Kommentar und Zeitstempel.
- **ConversationItem**: Kommentar/Nachricht/Erwähnung, nur bei API-Berechtigung und datenschutzkonformer Verarbeitung.
- **MetricSnapshot**: Kanal- und Beitragsmetriken mit Plattform, Zeitraum, Erhebungszeit und Definitionskontext.
- **AutomationRule**: Trigger, Bedingungen, Aktionen, Aktivstatus, Umfang, Ausführungslimits.
- **AuditEvent**: Wer hat wann was an welchem Objekt geändert? Exportierbar und nach Aufbewahrungsfristen bereinigbar.

**Beziehungsmodell:** Workspace → Brand → ChannelAccount; Brand → ContentItem → ContentVariant; ContentItem × ChannelAccount → Publication → DeliveryAttempt. Eine Kunden-/Markenberechtigung wird an jeder serverseitigen Lese- und Schreiboperation geprüft.

## 4. Zustands- und Prozesslogik

### Redaktioneller Ablauf

`Entwurf → In Prüfung → Freigegeben → Geplant → In Zustellung → Veröffentlicht`

Ausnahmen: `Abgelehnt → Überarbeiten`; `Geplant → Pausiert`; `In Zustellung → Wiederholbar fehlgeschlagen / Endgültig fehlgeschlagen`; jederzeit mit nachvollziehbaren Übergangsregeln. Veröffentlichungen müssen **pro Zielkonto** abgeschlossen werden: Wenn ein Beitrag auf Instagram gelingt, auf LinkedIn aber scheitert, dürfen die Ergebnisse nicht zu einem einzigen globalen Erfolg zusammengefasst werden.

### Technisch notwendige Fälle

- Token abgelaufen: erneute Autorisierung, Versuche blockieren statt Endlosschleife.
- Fehlende Rechte / Plattformfreigabe: vor dem Einplanen prüfen oder gezielt warnen.
- Zeitplan wird geändert: ausstehende Jobs aktualisieren, bereits veröffentlichte Beiträge nicht erneut senden.
- Mehrfachausführung durch Cron: idempotente Job-ID/Publikations-ID, doppelte Posts verhindern.
- Beitrag wird nach Freigabe verändert: Freigabe bei relevanten Änderungen erneut erforderlich.
- Bild/Video nicht unterstützt: nur betroffenen Zielkanal blockieren und andere zulässige Ziele erhalten.
- API-Limits oder temporärer Ausfall: begrenzte Wiederholungsstrategie mit Backoff, sichtbarer Fehlerstatus.
- Konto gelöscht / Marke entzogen: Jobs stoppen, Rechte prüfen, datenschutzkonform Daten bereinigen.

## 5. Plugin-Architektur (für spätere Umsetzung)

```text
social-media-hub/
├── social-media-hub.php         # WP-Bootstrap, Versionsprüfung, Aktivierung
├── includes/
│   ├── Domain/                 # Geschäftsregeln, Entitäten, Statusübergänge
│   ├── Application/            # Anwendungsfälle und Services
│   ├── Infrastructure/         # WP-Datenbank, Jobs, Logging, Token-Safe
│   ├── Rest/                   # API-Endpunkte, Schemas und Rechteprüfung
│   ├── Providers/              # Plattformfähigkeiten, Adapter-Verträge
│   ├── Security/               # Capabilities, Nonces, Validierung
│   └── Integrations/           # Optional Business Hub / externe Systeme
├── assets/admin/               # Modulare JavaScript-/CSS-Oberfläche
├── migrations/                 # Versionierte DB-Migrationen
├── tests/                      # Status-, API-, Rechte- und Integrations-Tests
└── languages/                  # Übersetzungen
```

**Repository-Verträge:** `BrandRepository`, `ChannelRepository`, `ContentRepository`, `PublicationRepository`, `AuditRepository`.  
**Provider-Vertrag:** `authorize`, `refreshCredentials`, `getCapabilities`, `validateContent`, `publish`, `getPublicationStatus`, optional `fetchComments`, `reply`, `fetchMetrics`, `revoke`.  
**Ereignisse:** `content.created`, `content.approval_requested`, `content.approved`, `publication.queued`, `publication.succeeded`, `publication.failed`, `channel.authorization_expired`.  
**Erweiterung:** Neue Provider/Module werden registriert, nicht durch if/else-Änderungen an allen Ansichten eingefügt.

### WordPress-spezifisch

- REST-Routen mit Versionierung (z. B. `/social-hub/v1/...`), **Permission-Callbacks** auf jedem schreibenden und geschützten lesenden Endpoint.
- Sicherer OAuth-Flow; Tokens nur serverseitig und geschützt, niemals in HTML/BrowserlocalStorage.
- WordPress-Objektcache nur zur Beschleunigung, nicht als alleinige Datenbank.
- Eigene, ordentlich indizierte Tabellen für wachstumsstarke Entitäten wie Publikationen, Zustellversuche und Messdaten; WordPress-Users/Media wiederverwenden, wo es sinnvoll ist.
- WP-Cron ist für niedriges Volumen möglich. Für verlässliche, zeitkritische Veröffentlichung braucht es eine robuste Job-Verarbeitung, Monitoring und ggf. echten Server-Cron/Queue-Worker.
- Keine kanalübergreifende Zwangssynchronisierung: Plattform-Aktionen werden nur ausgeführt, wenn die API sie tatsächlich unterstützt.

## 6. Anforderungen an Sicherheit, Betrieb und Skalierung

- Benutzer-/Markenrechte: auch direkt über API gegen IDOR und fremde Markenzugriffe absichern.
- Eingaben serverseitig validieren/sanitizen; Ausgaben kontextgerecht escapen; Nonces gegen CSRF; sichere Dateiuploads.
- Rate Limits und Concurrency je Provider/Account; queuefähige Wiederholungsstrategie.
- Datenmigrationen mit Schema-Version, Rollback-/Recovery-Plan und Backup-Funktion.
- Zustellung überwachen: Fehlerrate, wartende Jobs, Token-Ablauf, Webhook-Ereignisse, Audit-Trail.
- Dokumentierte Aufbewahrungs- und Löschregeln, Datenexport, Rollenmodell, minimale Verarbeitung.
- Automatisierte Tests pro Provider mit Mocks; echte Integrationstests in separater Staging-Umgebung.
- Assets für Web- und Mobile-Varianten, Alt-Texte, Untertitel, Bildverhältnisse und Länge pro Plattform validieren.

## 7. Empfohlene Entwicklungsreihenfolge

**Schritt 1 – HTML-Konzept überprüfen:** Navigation, Beitragstypen, Markenwechsel, Zielkonto-Auswahl, Varianten, Tagesplanung und Freigabewege.

**Schritt 2 – Datenmodell und Anforderungen fixieren:** Core-Objekte, Statusautomaten, Rechtesystem, Provider-Fähigkeiten, Fehlerfälle und API-Verträge samt Tests.

**Schritt 3 – WordPress-Grundsystem:** Modul-Lifecycle, Datenbank-Migrationen, geschützte REST-Schnittstellen, Marken/Benutzer/Medien, Admin-Oberfläche.

**Schritt 4 – Erste echte Provider:** Fokus auf Instagram/Facebook sowie ausgewählte textbasierte Kanäle; OAuth und Veröffentlichungen für dokumentiert unterstützte Medientypen, mit Testaccounts und Freigabe der Plattform-Apps.

**Schritt 5 – Produktion:** Queue und Monitoring, Wiederholungslogik, Reports, Zugriffsrollen, Datenschutzfunktionen, weitere Provider.

## 8. Technische Referenzen (Stand November 2026)

- WordPress REST API: https://developer.wordpress.org/plugins/rest-api/
- WordPress Cron: https://developer.wordpress.org/plugins/cron/
- WordPress REST Security & Permission Callbacks: https://developer.wordpress.org/plugins/rest-api/routes-endpoints/
- Instagram-API (Meta-Dokumentation): https://www.postman.com/meta/instagram/documentation/6yqw8pt/instagram-api
- LinkedIn Posts API: https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api
- TikTok Direct Posting API: https://developers.tiktok.com/docs/en/content-posting-api-get-started

## 9. Grenzen des aktuellen HTML-Prototyps

Die Datei enthält aus praktischen Gründen CSS und JavaScript zusammen mit HTML. Sie ist eine **Visualisierungs- und Interaktionsbasis**, keine produktionsfähige Plugin-Struktur. Ihre registrierbaren Ansichten und Plattformdefinitionen zeigen den geplanten Zuschnitt, ersetzen aber noch keine späteren eigenständigen Module, keine Serverdatenbank, keine echten Provider-Adapter und keine abgesicherte Mehrmandantenfähigkeit. Beiträge, Marken, Regeln und Kommentare sind lokal simuliert. Exportierte JSON-Sicherungen enthalten ausschließlich Demodaten.