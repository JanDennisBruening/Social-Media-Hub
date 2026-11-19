# Social Media Hub – Eigenständiges WordPress-Plugin (`v2.1.0`)

Eigenständiges, aufgeräumtes WordPress-Backend-Plugin zur geführten Erstellung, Vorprüfung, 1-Klick-Veröffentlichung, manuellen Übergabe (z. B. WhatsApp-Kanäle) und revisionssicheren Dokumentation von Social-Media-Beiträgen – inklusive **Komplett-Export (JSON + CSV)**.

---

## 1. Neu in Version 2.1.0

- **Echte, ehrliche Live-API-Prüfung (`LiveApiClient`):**
  - Kein Kanal wird mehr blind als „Verbunden“ angezeigt, wenn kein echter API-Schlüssel hinterlegt oder geprüft wurde.
  - Beim Speichern mit Token sowie beim Klick auf **„Live prüfen“** führt WordPress (`wp_remote_get` / `wp_remote_post`) eine echte HTTPS-Anfrage gegen die jeweilige Plattform-API aus (Instagram/Meta Graph API, Facebook, LinkedIn, Threads, Pinterest, Bluesky, Mastodon, Telegram, TikTok) und zeigt das tatsächliche Ergebnis inkl. HTTP-Status und Original-Fehlermeldung direkt in der Kanal-Karte an.
  - Auch beim Veröffentlichen gibt es keine simulierten `example.com`-Scheinergebnisse mehr: Ungeprüfte Kanäle werden ehrlich geblockt, verifizierte Kanäle senden über echte Plattform-Endpunkte.
- **Große Beitrags-Detailübersicht per Direktklick:**
  - Einzelne Beiträge lassen sich in der Beitragsliste, Übersicht und im Kalender direkt anklicken und öffnen eine große Detailansicht mit Vollbild-Vorschau, komplettem Text, Link, Metadaten, allen Kanal-Karten, Revisionen und Direkt-Aktionen (*Bearbeiten*, *Prüfen & Veröffentlichen*, *Verschieben*, *Duplizieren*, *Löschen*).

## 2. Generalüberholung aus Version 2.0.0

- **Sauberer Erststart ohne Demo-Müll:** Startet komplett leer und frei von Beispieleinträgen (alte unveränderte v1.0-Demo-Einträge werden beim Upgrade automatisch bereinigt).
- **Automatisch reduzierte WordPress-Hauptnavigation:** Beim Öffnen von *Social Hub* klappt das WordPress-Seitenmenü automatisch ein (`body.folded`), sodass maximale Arbeitsfläche entsteht (weiterhin jederzeit über WordPress ein- und ausklappbar).
- **Einfacher 3-Schritt-Beitrags-Assistent:**
  1. **Plattformen wählen:** Kanäle und Format direkt ankreuzen (inkl. Schnell-Anlage für neue Kanäle).
  2. **Inhalt & Bild gestalten:** Caption, Hashtag-Bausteine, echter Upload/Auswahl aus der nativen **WordPress-Mediathek (`wp.media`)**, optionaler Link (z. B. YouTube-Video), Ordner/Kampagne, Termin und Live-Vorschau.
  3. **Prüfen & 1-Klick-Veröffentlichen:** Alle ausgewählten Kanäle samt Verbindungsstatus prüfen und sofort oder zum Wunschtermin mit einem Klick starten.
- **Große, aufgeräumte Beitragsliste:**
  - Status-Tabs (`Alle`, `Entwürfe`, `Geplant`, `Offen / Teilweise`, `Veröffentlicht`, `Archiv`), Suche, Ordner-, Plattform- und Sortierfilter.
  - **Mehrfachauswahl** mit Sammel-Aktionsleiste für **Verschieben** (Ordner/Kampagne, Status/Archiv, Termin oder Marke), **Duplizieren**, **Auswahl-Export** und **Löschen**.
  - **Zweistufige Lösch-Sicherheitsabfrage:** Erst Bestätigung mit Auflistung der betroffenen Einträge, danach zweite rote Warnstufe mit Pflicht-Bestätigungshäkchen.
- **Schlanke Ein-Marken-Ansicht:** Der Marken-Umschalter oben rechts bleibt ausgeblendet, solange nur eine Marke angelegt ist, und erscheint automatisch ab 2 Marken.

---

## 2. Kernfunktionen & Architektur

- **100 % eigenständig & unabhängig von WordPress-Blogbeiträgen:**
  Nutzt WordPress als sichere Laufzeitumgebung (Login, Rollen, REST-API, Mediathek), speichert aber alle Daten unabhängig in **10 eigenen Custom Tables** (`{prefix}smh_...`).
- **Steckmodul-Architektur für 11 Kanäle (`ProviderRegistry`):**
  Instagram, Facebook-Seite, LinkedIn (Profil & Unternehmensseite getrennt), Threads, TikTok, Pinterest, Bluesky, Mastodon, Telegram-Kanal, WhatsApp-Kanal und Signal.
- **Sicherheit, Transport, Abgleich & Automatisierung:**
  1. **Unveränderliche Revisions-Snapshots (`smh_revisions`):** Friert bei Freigabe und Versand den exakten Text- und Medienstand unveränderlich ein.
  2. **Komplett-Export & Umzugs-Paket (`BackupExporter`):** Portabler JSON-Komplett-Export (stets **ohne geheime Tokens**), CSV-Archivliste und validierter Re-Import.
  3. **Verschlüsselter Zugangsdaten-Tresor (`TokenSafe`):** Serverseitige Verschlüsselung über Libsodium (`sodium_crypto_secretbox`) bzw. `AES-256-GCM`.
  4. **Signierte Medien-Abhol-Links (`SignedMediaEndpoint`):** Kurzlebige HMAC-URLs (15 Min. TTL) für Pull-APIs wie Instagram/Meta.
  5. **Idempotente Queue & Einzel-Retry (`PublishQueueWorker`):** Verhindert doppelte Posts auf bereits erfolgreichen Kanälen bei Teilversagen.
  6. **48-Stunden-Vorab-Check (`PreflightService`):** Prüft geplante Beiträge rechtzeitig auf ablaufende Kanal-Zugänge.
  7. **Volle Kompatibilität mit `JDB Wartungsmodus`:** Greift niemals ins Frontend ein und erlaubt ausschließlich berechtigte `/social-hub/v1/`-Backend-Aufrufe sowie signierte Medien-Pulls.

---

## 3. Verzeichnisstruktur

```text
Plugin/social-media-hub/
├── social-media-hub.php                              # WordPress-Plugin-Bootstrap & Autoloader (v2.1.0)
├── uninstall.php                                     # Schutz vor unbeabsichtigtem Datenverlust beim Deinstallieren
├── README.md                                         # Dokumentation & Installationsanleitung
├── assets/
│   ├── css/admin-app.css                             # Aufgeräumtes Design System (#17213A / #586EE7) & WP-Fold-Integration
│   └── js/admin-app.js                               # REST-gebundene Admin-UI (Detailansicht, 3-Schritt-Assistent, Beitragsliste, Bulk & 2-Stufen-Löschen)
├── includes/
│   ├── Admin/AdminPage.php                           # WP-Admin-Menü, Auto-Fold (`admin_body_class`) & `wp_enqueue_media()`
│   ├── Core/
│   │   ├── Activator.php                             # dbDelta-Tabellenanlage, Rollen & Cron-Hooks
│   │   ├── EventBus.php                              # Interner Event-Dispatcher & HMAC-Webhooks
│   │   └── Plugin.php                                # Zentraler Service-Container & Wartungsmodus-Kompatibilität
│   ├── Domain/
│   │   ├── PreflightService.php                      # Format-/Medien-/Pflichtfeld-Prüfung & 48h-Token-Check
│   │   └── PublicationService.php                    # Revisions-Snapshots, Queue-Dispatch, Einzel-Retry & Nachweise
│   ├── Infrastructure/
│   │   ├── Database/
│   │   │   ├── Schema.php                            # 10 Custom Tables (smh_brands ... smh_audit_events)
│   │   │   └── Repositories.php                      # Sauberer leerer Grundbestand, v1->v2-Migration, Bulk Move/Duplicate/Delete
│   │   ├── Export/BackupExporter.php                 # Portabler Komplett-Export (JSON + CSV) & Import-Validator
│   │   ├── Http/LiveApiClient.php                    # Echte HTTPS-Live-Prüfungen & echte Plattform-Veröffentlichungen
│   │   ├── Queue/PublishQueueWorker.php              # Asynchrone Zustellung & Exponential Backoff (60s/300s/1800s)
│   │   └── Security/
│   │       ├── Capabilities.php                      # 4 WP-Rollen/Capabilities & Marken-ACL
│   │       ├── SignedMediaEndpoint.php               # HMAC-signierte Medien-Pull-URLs für Meta/Instagram
│   │       └── TokenSafe.php                         # Libsodium / AES-256-GCM Token-Tresor
│   ├── Providers/
│   │   ├── ConfigurablePlatformProvider.php          # Dynamische Capability-, Preflight- & Versandlogik
│   │   ├── ProviderInterface.php                     # Steckmodul-Vertrag für Plattformen
│   │   └── ProviderRegistry.php                      # Zentrale Plattform-Registry (11 Kanäle)
│   └── Rest/RestController.php                       # Vollständige WP-REST-Endpunkte (`/wp-json/social-hub/v1/...`)
└── tests/
    └── verify-all-scenarios.mjs                      # Automatisierte Verifikations-Suite (13 Testgruppen)
```

---

## 4. Installation in WordPress

1. Den Ordner `Plugin/social-media-hub` nach `wp-content/plugins/social-media-hub` kopieren (oder als ZIP im WordPress-Backend hochladen).
2. Optional in der `wp-config.php` einen dedizierten Tresor-Schlüssel hinterlegen (ansonsten nutzt `TokenSafe` automatisch die WordPress-Salts):
   ```php
   define('SMH_ENCRYPTION_KEY', 'hier-einen-langen-zufallsschluessel-eintragen');
   ```
3. Im WordPress-Backend unter **Plugins** das Plugin **Social Media Hub** aktivieren.
4. Über den Menüpunkt **Social Hub** im WordPress-Admin starten.
