# Social Media Hub – Eigenständiges WordPress-Plugin (`v1.0.0`)

Eigenständiges, mehrmarkenfähiges WordPress-Backend-Plugin zur Gestaltung, Vorprüfung, Ausspielung, manuellen Übergabe (z. B. WhatsApp-Kanäle) und revisionssicheren Dokumentation von Social-Media-Beitragsverbünden – inklusive **Komplett-Export (JSON + CSV)**.

---

## 1. Kernmerkmale im Überblick

- **100 % eigenständig & unabhängig von WordPress-Blogbeiträgen:**
  Nutzt WordPress als sichere Laufzeitumgebung (Authentifizierung, Rollen, Datenbankverbindung, REST-API), speichert aber alle Inhalte in **10 eigenen, indizierten Custom Tables** (`{prefix}smh_...`).
- **Fokus auf den Social-Media-Klassiker (ohne YouTube-Upload-Ballast):**
  Bild(er) / Kurzvideo + Caption + Hashtag-Bausteine + optionaler Verweis-Link (z. B. um auf ein neues YouTube-Video oder eine Aktion hinzuweisen).
- **Clevere Link- & YouTube-Verweis-Logik:**
  Hängt einen hinterlegten Link auf klickbaren Kanälen (Facebook, LinkedIn, WhatsApp-Kanal, Telegram, Bluesky, Mastodon, Threads) direkt an und warnt auf Instagram automatisch mit dem Hinweis *„Link in der Bio“*.
- **Steckmodul-Architektur für 11 Kanäle (`ProviderRegistry`):**
  Instagram, Facebook-Seite, LinkedIn (Persönliches Profil & Unternehmensseite getrennt), Threads, TikTok, Pinterest, Bluesky, Mastodon, Telegram-Kanal, WhatsApp-Kanal und Signal.
- **Volle Kompatibilität mit `JDB Wartungsmodus` & JDB-Plugins:**
  Greift nicht in das öffentliche Frontend (`template_redirect`) ein, lädt CSS/JS strikt nur auf der eigenen Backend-Seite (`toplevel_page_social-media-hub`) und gibt seine REST-Endpunkte (`/wp-json/social-hub/v1/...`) gezielt für berechtigte Rollen sowie HMAC-signierte Medien-Pulls frei.
- **Sicherheit, Transport, Abgleich & Automatisierung (Bereiche 1, 2 und A–D):**
  1. **Unveränderliche Revisions-Snapshots (`smh_revisions`):** Friert bei Freigabe und Versand den exakten Text- und Medienstand unveränderlich ein.
  2. **Komplett-Export & Umzugs-Paket (`BackupExporter`):** Exportiert alle Marken, Kanäle (**ohne geheime Tokens**), Medien-Manifeste (inkl. Zuschnitten & Alt-Texten), Textbausteine, Beiträge, Revisionen und Nachweise als JSON-Paket oder CSV-Archivliste.
  3. **Verschlüsselter Zugangsdaten-Tresor (`TokenSafe`):** Serverseitige Verschlüsselung über Libsodium (`sodium_crypto_secretbox`) bzw. `AES-256-GCM`.
  4. **Zwei Medien-Transportwege:** Direkter Datei-Push sowie kurzlebige, HMAC-signierte Abhol-Links (`SignedMediaEndpoint`, 15 Min. TTL) für Pull-APIs wie Instagram/Meta.
  5. **Idempotente Queue & Einzel-Retry (`PublishQueueWorker`):** Verhindert bei einem Retry nach Teilversagen zu 100 % doppelte Posts auf bereits erfolgreichen Kanälen.
  6. **48-Stunden-Vorab-Check (`PreflightService`):** Prüft 48 Stunden vor geplanten Beiträgen alle OAuth-Zugänge auf Ablauf.
