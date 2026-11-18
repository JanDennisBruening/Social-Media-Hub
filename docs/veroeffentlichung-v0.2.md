# Social Media Hub – Veröffentlichung und Plattformvorschauen V0.2

**Stand:** 18. November 2026  
**Phase:** Funktions- und UI-Prototyp; **noch kein WordPress-Plugin** und keine reale API-Veröffentlichung.

## 1. Leitentscheidung

Eine redaktionelle Idee bleibt ein übergeordnetes **Beitragsobjekt**. Darunter entstehen getrennte **Zielversionen** und **Veröffentlichungsaufträge** für jeden ausgewählten Social-Media- oder Messenger-Kanal. Eine gemeinsame Aktion löst später mehrere unabhängige Aufträge aus – keinen plattformübergreifenden atomaren „Alles-oder-nichts“-Vorgang.

Der Arbeitsablauf: Inhalt erstellen → Zielkonten auswählen → Varianten nebeneinander prüfen → einzelne Ziele aktivieren/deaktivieren → Freigabe prüfen → automatisierte Ausspielung planen/auslösen oder manuelle Übergabe vorbereiten → Erfolg/Fehler/Manuellerledigung je Kanal dokumentieren.

## 2. In V0.2 als HTML-Demo umgesetzt

- **Veröffentlichungszentrale** als eigener Navigationsbereich mit redaktionellen Beitragsgruppen, Aktivitäts- und Zielübersicht.
- **Plattformvorschauen** für alle einem Beitrag zugeordneten Konten, mit unterschiedlichen, bewusst angenäherten Oberflächen und visuellen Hinweisen für Text-/Medienformate.
- **Individuelle Textvarianten pro Zielkonto** im selben Kontrollfenster; leerer Variantentext übernimmt den gemeinsamen Text.
- **Ein-/Ausschalter je Zielkonto** vor der Ausspielung: Ausschalten bewahrt die Zielkonfiguration, schließt es aber von Aktionen aus.
- **Getrennte Demo-Statusdaten** pro Zielkonto: offen, geplant, simuliert, manuell ausstehend, manuell bestätigt.
- **„API-Lauf simulieren“**: ändert nur lokale Statusdaten für aktivierte automatische Ziele. Es wird **nichts online veröffentlicht**.
- **„Aktive Ziele planen“**: speichert die Planung lokal; kein Hintergrundprozess versendet zu diesem Termin.
- **Manuelle Kanäle** (Beispiel: „Digital-Tipps Südwestpfalz“ als WhatsApp-Kanal sowie Telegram und Signal): Text kopieren, Medien separat bereitstellen und nach tatsächlichem externen Versand eine ausdrückliche Bestätigung erfassen.
- **Manuelle Dokumentation** mit Bestätigungszeitpunkt, optionaler Beitrags-URL und interner Notiz sowie Rücknahme einer Bestätigung. Eigene Übersicht „Manuelle Aufgaben“.
- **Lokaler Zustand** im Browser, JSON-Export/Import und Übernahme vorhandener V0.1-Demodaten in ein V0.2-Datenmodell.
- **Mehrmarkenfähigkeit** bleibt erhalten: Konten, Medien und Beiträge werden nach Marke gefiltert.

### Beispiel – ein Beitrag mit vier Zielen

| Kanal | Methode V0.2 | Aktion nach Kontrolle |
|---|---|---|
| Instagram | automatische API-Aufgabe **nur simuliert** | In aktivierten Satz aufnehmen |
| Facebook | automatische API-Aufgabe **nur simuliert** | In aktivierten Satz aufnehmen |
| LinkedIn | automatische API-Aufgabe **nur simuliert** | In aktivierten Satz aufnehmen |
| WhatsApp-Kanal | manuell | Text kopieren, in WhatsApp veröffentlichen, anschließend als erledigt dokumentieren |

## 3. Vergleich mit anderen Lösungen

Die funktionale Recherche beruht auf Anleitungen der Anbieter, nicht auf der Übernahme fremder Designs oder Quelltexte.

- **Buffer:** Gemeinsamer Composer, Anpassung des Beitrags an jede Plattform, Vorschau je Kanal, „Post Groups“ für zusammengehörende Veröffentlichungen. Übernehmen: **ein Beitragsverbund mit unabhängigen Varianten und Statuswerten**. Quellen: https://support.buffer.com/articles/scheduling-posts-4Qdld7giAZ und https://support.buffer.com/en-us/articles/using-post-groups-in-buffer-bUZAamv7kk
- **Metricool:** Multiposting mit Einzelanpassungen und Vorschau; manuelle Veröffentlichung kann über Benachrichtigungen und kopierten Text erfolgen. Übernehmen: **manual handoff als integralen Teil der Planung**, später optional Erinnerungen oder Mobilübergabe. Quellen: https://help.metricool.com/multiposting-edit-per-social-network-85out und https://help.metricool.com/how-to-manually-publish-via-notification-5ds3m
- **Planable:** Detaillierte Voransichten, Kunden-/Teamfreigaben, Rollen und mehrstufige Prüfungen. Übernehmen: **später rollenbasierte Freigabe vor Produktion, Revisionsverlauf und Kommentarspuren**. Quellen: https://help.planable.io/hc/en-us/articles/21715323068444-Getting-started-with-Planable und https://planable.io/approvals/

**API-Abgrenzung:** Die offizielle WhatsApp Business Platform ist nicht mit einem allgemeinen Schreibzugriff auf WhatsApp-Kanal-Updates gleichzusetzen; deshalb das WhatsApp-Kanal-Ziel hier zunächst manuell behandeln. Telegram besitzt dagegen eine Bot API, die Nachrichten an einen Kanal unterstützen kann, sofern erforderliche Rechte vorliegen; in der V0.2-Demo bleibt Telegram als manuelles Beispiel konfiguriert. Quellen: https://developers.facebook.com/documentation/business-messaging/whatsapp/overview und https://core.telegram.org/bots/api

## 4. Skalierbares Datenmodell für die Umsetzung

Diese Objekte sollten später **getrennte Persistenz- und Rechte-Grenzen** bekommen:

1. **Workspace / Marke:** Eigentümer, Branding, Team und Zugriffsregeln.
2. **ChannelAccount:** Plattform, externe ID, Marke, Berechtigungen, Token-Verweis, Fähigkeiten (Capabilities) sowie Verbindungsgesundheit.
3. **PostGroup:** Übergeordnete Idee, Titel, gemeinsamer Text, Freigabezustand und zugeordnete Medien.
4. **PostVariant:** Spezifische Text-/Medienfassung und Optionen für genau einen Zielkanal; alternative Formate je Plattform.
5. **PublicationDestination:** Kanalzuordnung inklusive aktiviert/deaktiviert, gewählte Methode (auto/manuell), individuelle Terminierung und Validierung.
6. **DeliveryJob:** Für automatische Kanäle; Idempotenzschlüssel, Queue-Status, Provider-Anfrage/-Antwort, Remote-Post-ID, Retry, Zeitstempel und Fehlercode.
7. **ManualPublicationRecord:** Explizite, von einer berechtigten Person bestätigte externe Veröffentlichung mit Zeitpunkt, Notiz, URL und ggf. Medienübergabe.
8. **AuditEvent:** Unveränderbare Historie von Änderung, Freigabe, Statuswechsel, Deaktivierung und manueller Bestätigung.

**Der HTML-Prototyp verwendet der Einfachheit halber `post.destinations[accountId]`**. Das ist bewusst keine Vorgabe für die relationale Produktionsdatenbank. In WordPress sollte die Speicherung und Autorisierung serverseitig erfolgen.

## 5. Entscheidungen, die wir noch ausarbeiten sollten

**Vor echter Veröffentlichung unbedingt:** Plattform-Adapter und aktuelle Rechtebedingungen, tatsächliche Medienverwaltung, Bildzuschnitte und Formatvarianten, Linkvorschau, Accessibility/Alt-Texte, Freigabesystem, rückverfolgbare Zustellung, Fehler-/Teilversagen, Wiederholungsregeln, Zeitzonen und Sommerzeit, Rate Limits, Widerruf von OAuth-Zugriff, Datenschutz und personenbezogene Angaben.

**Sinnvoll im nächsten Prototypen-Schritt:**

- Varianten für Feed, Story, Reel, Short und Karussell einschließlich Bildformat-/Dateianforderungsprüfung je Kanal.
- Qualitätscheck je Ziel: Textlänge, Mention-/Hashtag-Kompatibilität, erforderliche Medien, Zielrechte, Alt-Text und Link-Handling.
- Medienpaket für manuelle Ausspielung: Bild/Video herunterladen, Text kopieren, Ziel-App öffnen, Checkliste abhaken.
- Statusansicht je Ziel: **nicht begonnen / geplant / Übergabe ausstehend / abgeschlossen / fehlgeschlagen / erneut versuchen**; keine Vermischung von API-Erfolg und manueller Selbstauskunft.
- Unterschiedliche Zeiten und Freigabeverantwortliche pro Kanal; Mobile-First-Auslieferung der manuellen Aufgaben.
- Versionen und gemeinsamer Redaktionsplan inklusive Mehrkunden-Freigaben.

## 6. Qualität und Demo-Grenzen

Der Stand V0.2 wurde mit einem Chromium-basierten Browser auf Navigation, Vorschau, Zielschalter, Variantentext, API-Simulation, manueller Bestätigung und neuer Beitragsgruppe getestet. Die lokale HTML-Datei führt keine Social-Media-API-Aufrufe aus. Vorschauen sind **Illustrationen**, keine verbindlichen Renderings der fremden Plattformen. Der Browser speichert Entwürfe nur lokal, soweit verfügbar; weder externe Veröffentlichung noch automatische Termin-Ausführung finden statt.