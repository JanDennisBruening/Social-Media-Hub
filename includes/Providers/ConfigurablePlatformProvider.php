<?php
/**
 * Konfigurierbares Plattform-Steckmodul (`ConfigurablePlatformProvider`).
 *
 * Implementiert die vollständige Capability-Diagnose, die format- und kontospezifische
 * Vorprüfung (Preflight), die Link-/YouTube-Verweis-Logik sowie die Zustellschritte
 * inklusive signierter Pull-URLs für Meta/Instagram.
 *
 * @package SocialMediaHub\Providers
 */

declare(strict_types=1);

namespace SocialMediaHub\Providers;

use SocialMediaHub\Infrastructure\Http\LiveApiClient;
use SocialMediaHub\Infrastructure\Security\SignedMediaEndpoint;

class ConfigurablePlatformProvider implements ProviderInterface
{
    private string $platformKey;
    /** @var array<string, mixed> */
    private array $spec;
    private SignedMediaEndpoint $signedMediaEndpoint;
    private LiveApiClient $liveApiClient;

    /**
     * @param array<string, mixed> $spec
     */
    public function __construct(
        string $platformKey,
        array $spec,
        SignedMediaEndpoint $signedMediaEndpoint,
        ?LiveApiClient $liveApiClient = null
    ) {
        $this->platformKey = $platformKey;
        $this->spec = $spec;
        $this->signedMediaEndpoint = $signedMediaEndpoint;
        $this->liveApiClient = $liveApiClient ?? new LiveApiClient();
    }

    public function getPlatformKey(): string
    {
        return $this->platformKey;
    }

    public function getManifest(): array
    {
        return array_merge(['platform' => $this->platformKey], $this->spec);
    }

    public function evaluateAccountCapabilities(array $account): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $accountType = (string) ($account['account_type'] ?? 'standard');
        $connectionState = (string) ($account['connection_state'] ?? 'not_configured');
        $grantedScopes = array_values(array_map('strval', (array) ($account['granted_scopes'] ?? [])));
        $expiresAt = isset($account['token_expires_at']) ? (string) $account['token_expires_at'] : '';
        $liveVerifiedAt = trim((string) ($account['live_verified_at'] ?? ''));
        $lastCheckMessage = trim((string) ($account['last_check_message'] ?? ''));

        // 1. Rein manuelle Kanäle (WhatsApp-Kanal, Signal oder bewusst manuell gesetzter Kanal)
        if (in_array($this->platformKey, ['whatsapp', 'signal'], true) || $connectionState === 'manual') {
            return [
                'status'         => 'manual',
                'delivery_mode'  => 'manual',
                'reason'         => 'Manueller Übergabe-Kanal (Text kopieren, Bild laden & Veröffentlichung bestätigen).',
                'missing_scopes' => [],
                'token_expired'  => false,
                'live_verified'  => false,
                'evaluated_at'   => $now,
            ];
        }

        // 2. Kein API-Schlüssel / Access-Token hinterlegt
        if ($connectionState === 'not_configured') {
            return [
                'status'         => 'not_configured',
                'delivery_mode'  => 'blocked',
                'reason'         => $lastCheckMessage !== ''
                    ? $lastCheckMessage
                    : 'Noch kein API-Schlüssel hinterlegt. Bitte Zugangsdaten eintragen oder auf manuellen Modus umstellen.',
                'missing_scopes' => [],
                'token_expired'  => false,
                'live_verified'  => false,
                'evaluated_at'   => $now,
            ];
        }

        // 3. Noch nicht live gegen die Plattform-API geprüft (NIEMALS blind als „verbunden“ ausgeben!)
        if (
            $connectionState === 'unverified'
            || $connectionState === 'unknown'
            || (in_array($connectionState, ['connected', 'handoff_only'], true) && $liveVerifiedAt === '')
        ) {
            return [
                'status'         => 'unknown',
                'delivery_mode'  => 'unknown',
                'reason'         => $lastCheckMessage !== ''
                    ? $lastCheckMessage
                    : 'Noch nicht live gegen die Plattform-API geprüft. Bitte auf „Live prüfen“ klicken.',
                'missing_scopes' => [],
                'token_expired'  => false,
                'live_verified'  => false,
                'evaluated_at'   => $now,
            ];
        }

        // 4. Abgelaufenes OAuth-Token prüfen
        $tokenExpired = ($connectionState === 'expired')
            || ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) <= time());

        // 5. Erforderliche Scopes für diesen Kontotyp prüfen (z. B. w_organization_social bei LinkedIn Company)
        $requiredByType = (array) ($this->spec['required_scopes'][$accountType] ?? []);
        $missingScopes = array_values(array_diff($requiredByType, $grantedScopes));

        if ($tokenExpired || $missingScopes !== [] || $connectionState === 'blocked') {
            if ($connectionState === 'blocked' && $lastCheckMessage !== '' && !$tokenExpired) {
                $reason = $lastCheckMessage;
            } else {
                $reasonParts = [];
                if ($tokenExpired) {
                    $reasonParts[] = 'Zugangstoken ist abgelaufen';
                }
                if ($missingScopes !== []) {
                    $reasonParts[] = 'Fehlende Berechtigung (' . implode(', ', $missingScopes) . ')';
                }
                if ($reasonParts === []) {
                    $reasonParts[] = 'API-Verbindung fehlgeschlagen oder Token ungültig';
                }
                $reason = implode(' · ', $reasonParts) . '. Bitte Zugangsdaten prüfen und erneut testen.';
            }
            return [
                'status'         => 'blocked',
                'delivery_mode'  => 'blocked',
                'reason'         => $reason,
                'missing_scopes' => $missingScopes,
                'token_expired'  => $tokenExpired,
                'live_verified'  => false,
                'evaluated_at'   => $now,
            ];
        }

        // 6. App-Fertigstellung (Handoff), z. B. TikTok nach erfolgreicher Live-Prüfung
        if ($connectionState === 'handoff_only' || ($this->platformKey === 'tiktok' && !in_array('video.publish', $grantedScopes, true))) {
            return [
                'status'         => 'handoff',
                'delivery_mode'  => 'handoff',
                'reason'         => $lastCheckMessage !== ''
                    ? $lastCheckMessage
                    : 'Live geprüft – Upload an die Ziel-App möglich; finaler Abschluss erfolgt in der App.',
                'missing_scopes' => [],
                'token_expired'  => false,
                'live_verified'  => true,
                'evaluated_at'   => $now,
            ];
        }

        return [
            'status'         => 'ready',
            'delivery_mode'  => 'api',
            'reason'         => $lastCheckMessage !== ''
                ? $lastCheckMessage
                : 'Live über Plattform-API verifiziert und bereit zum Veröffentlichen.',
            'missing_scopes' => [],
            'token_expired'  => false,
            'live_verified'  => true,
            'evaluated_at'   => $now,
        ];
    }

    public function validateVariant(
        array $postGroup,
        array $variant,
        array $account,
        array $resolvedMedia
    ): array {
        $enabled = (bool) ($variant['enabled'] ?? true);
        $overrideText = trim((string) ($variant['text_override'] ?? ''));
        $masterText = trim((string) ($postGroup['master_text'] ?? ''));
        $effectiveText = $overrideText !== '' ? $overrideText : $masterText;
        $externalUrl = trim((string) ($variant['metadata']['link'] ?? ($postGroup['external_url'] ?? '')));

        // Bei Kanälen mit klickbaren Links im Text (z. B. WhatsApp, LinkedIn, Bluesky, Mastodon)
        // wird ein hinterlegter externer Link (etwa ein YouTube-Video-Link) automatisch berücksichtigt.
        if (
            $externalUrl !== ''
            && !empty($this->spec['clickable_in_caption'])
            && !str_contains($effectiveText, $externalUrl)
            && in_array($this->platformKey, ['whatsapp', 'signal', 'telegram', 'linkedin', 'bluesky', 'mastodon', 'threads'], true)
        ) {
            $effectiveText = rtrim($effectiveText) . "\n\n🔗 " . $externalUrl;
        }

        $graphemeCount = ProviderRegistry::countGraphemes($effectiveText);
        $charLimit = $this->resolveCharLimit($account);

        if (!$enabled) {
            return [
                'key'            => 'off',
                'label'          => 'Deaktiviert',
                'method'         => 'off',
                'issues'         => [],
                'advice'         => [],
                'effective_text' => $effectiveText,
                'grapheme_count' => $graphemeCount,
                'char_limit'     => $charLimit,
            ];
        }

        $issues = [];
        $advice = [];

        $formats = (array) ($this->spec['formats'] ?? []);
        $formatKey = (string) ($variant['format_key'] ?? array_key_first($formats) ?? 'text');
        $formatSpec = (array) ($formats[$formatKey] ?? reset($formats) ?: [
            'label'      => 'Standard',
            'media_kind' => 'none',
            'min_media'  => 0,
            'max_media'  => 4,
        ]);

        // 1. Medien-Prüfung pro gewähltem Format
        $requiredKind = (string) ($formatSpec['media_kind'] ?? 'none');
        $minMedia = (int) ($formatSpec['min_media'] ?? 0);
        $maxMedia = (int) ($formatSpec['max_media'] ?? 10);

        $matchingMedia = array_values(array_filter(
            $resolvedMedia,
            static function (array $m) use ($requiredKind): bool {
                $kind = (string) ($m['kind'] ?? 'image');
                if ($requiredKind === 'none') {
                    return true;
                }
                if ($requiredKind === 'image_or_video') {
                    return in_array($kind, ['image', 'video'], true);
                }
                return $kind === $requiredKind;
            }
        ));

        if ($requiredKind !== 'none' && count($matchingMedia) < $minMedia) {
            $kindLabel = match ($requiredKind) {
                'video'          => 'Video (9:16)',
                'document'       => 'PDF-Dokument',
                'image_or_video' => 'Bild oder Video',
                default          => 'Bild',
            };
            $issues[] = sprintf(
                'Für „%s“ %s benötigt (%s).',
                (string) ($formatSpec['label'] ?? $formatKey),
                $minMedia === 1 ? 'wird mindestens 1 passendes Medium' : "werden mindestens {$minMedia} passende Medien",
                $kindLabel
            );
        }

        if ($maxMedia > 0 && count($resolvedMedia) > $maxMedia) {
            $issues[] = sprintf(
                'Für „%s“ sind maximal %d Medien zulässig (aktuell %d ausgewählt).',
                (string) ($formatSpec['label'] ?? $formatKey),
                $maxMedia,
                count($resolvedMedia)
            );
        }

        if ($requiredKind === 'none' && $resolvedMedia !== [] && in_array($formatKey, ['text', 'poll'], true)) {
            $advice[] = 'Ausgewählte Medien werden bei diesem reinen Text-/Umfrageformat nicht mitgesendet.';
        }

        // Barrierefreiheit (Alt-Text-Prüfung für Bilder)
        foreach ($matchingMedia as $mediaItem) {
            if (($mediaItem['kind'] ?? '') === 'image' && trim((string) ($mediaItem['alt_text'] ?? '')) === '') {
                $advice[] = sprintf('Für „%s“ fehlt noch ein Alt-Text (Barrierefreiheit).', (string) ($mediaItem['name'] ?? 'Bild'));
            }
        }

        // 2. Text- und Zeichenlimit-Prüfung
        if ($effectiveText === '' && $this->platformKey !== 'pinterest') {
            $issues[] = 'Beitragstext (Caption) fehlt.';
        }
        if ($charLimit !== null && $graphemeCount > $charLimit) {
            $issues[] = sprintf(
                'Text überschreitet das Limit für %s (%d / %d Grapheme).',
                (string) ($this->spec['label'] ?? $this->platformKey),
                $graphemeCount,
                $charLimit
            );
        }

        // 3. Clevere Link- & YouTube-Verweis-Prüfung (Instagram klickbare Links vs. Bio-Hinweis)
        $hasLinkInText = (bool) preg_match('~https?://\S+~i', $effectiveText);
        if ($this->platformKey === 'instagram' && ($externalUrl !== '' || $hasLinkInText) && $formatKey !== 'story') {
            if (empty($variant['metadata']['bio_link_hint']) && !str_contains(mb_strtolower($effectiveText), 'bio')) {
                $advice[] = 'Links sind in Instagram-Captions nicht klickbar. Empfehlung: Hinweis „Link in der Bio“ aktivieren.';
            }
        }

        // 4. Plattformspezifische Pflichtfelder (Pinterest, TikTok, Facebook Link, LinkedIn, Mastodon, IG Story)
        $meta = (array) ($variant['metadata'] ?? []);

        if ($this->platformKey === 'pinterest') {
            if (trim((string) ($meta['board'] ?? '')) === '') {
                $issues[] = 'Pinterest-Board (Pinnwand) fehlt.';
            }
            if (trim((string) ($meta['title'] ?? '')) === '') {
                $issues[] = 'Pin-Titel fehlt.';
            }
        }

        if ($this->platformKey === 'tiktok') {
            if (empty($meta['consent'])) {
                $issues[] = 'Ausdrückliche Nutzerfreigabe (TikTok Content Posting Consent) fehlt.';
            }
            if (trim((string) ($meta['privacy'] ?? '')) === '') {
                $issues[] = 'Sichtbarkeit für TikTok (Öffentlich / Freunde / Privat) fehlt.';
            }
        }

        if ($this->platformKey === 'facebook' && $formatKey === 'link' && $externalUrl === '') {
            $issues[] = 'Für einen Facebook-Linkbeitrag ist eine Ziel-URL (z. B. YouTube- oder Webseiten-Link) erforderlich.';
        }

        if ($this->platformKey === 'mastodon') {
            $advice[] = sprintf('Instanz-Limit aktiv: max. %d Zeichen.', $charLimit ?? 500);
        }

        // 5. Konto-Capabilities & Berechtigungen auswerten
        $cap = $this->evaluateAccountCapabilities($account);
        $method = $cap['delivery_mode'];

        // Instagram Story mit nativen Stickern wird automatisch zur manuellen App-Nachbearbeitung
        if ($this->platformKey === 'instagram' && $formatKey === 'story' && !empty($meta['native_extras'])) {
            $method = 'manual';
            $advice[] = 'Story mit nativen Stickern/Musik ist als manuelle Übergabe eingeplant.';
        }

        if (in_array($cap['status'], ['blocked', 'not_configured'], true)) {
            $issues[] = $cap['reason'];
        }

        if ($issues !== []) {
            return [
                'key'            => 'blocked',
                'label'          => 'Aktion erforderlich',
                'method'         => $method,
                'issues'         => $issues,
                'advice'         => $advice,
                'effective_text' => $effectiveText,
                'grapheme_count' => $graphemeCount,
                'char_limit'     => $charLimit,
            ];
        }

        if ($method === 'unknown') {
            return [
                'key'            => 'unknown',
                'label'          => 'Nicht geprüft',
                'method'         => 'unknown',
                'issues'         => [],
                'advice'         => array_merge([$cap['reason']], $advice),
                'effective_text' => $effectiveText,
                'grapheme_count' => $graphemeCount,
                'char_limit'     => $charLimit,
            ];
        }

        if ($method === 'manual') {
            return [
                'key'            => 'manual',
                'label'          => 'Manuelle Übergabe',
                'method'         => 'manual',
                'issues'         => [],
                'advice'         => $advice,
                'effective_text' => $effectiveText,
                'grapheme_count' => $graphemeCount,
                'char_limit'     => $charLimit,
            ];
        }

        if ($method === 'handoff') {
            return [
                'key'            => 'handoff',
                'label'          => 'App-Fertigstellung',
                'method'         => 'handoff',
                'issues'         => [],
                'advice'         => array_merge(['Upload geht an die Ziel-App; Veröffentlichung muss dort abgeschlossen werden.'], $advice),
                'effective_text' => $effectiveText,
                'grapheme_count' => $graphemeCount,
                'char_limit'     => $charLimit,
            ];
        }

        return [
            'key'            => 'ready',
            'label'          => 'API-bereit',
            'method'         => 'api',
            'issues'         => [],
            'advice'         => $advice,
            'effective_text' => $effectiveText,
            'grapheme_count' => $graphemeCount,
            'char_limit'     => $charLimit,
        ];
    }

    public function executeDeliveryStep(
        array $job,
        array $snapshotVariant,
        array $account,
        array $decryptedSecrets
    ): array {
        $cap = $this->evaluateAccountCapabilities($account);

        if ($cap['status'] !== 'ready' || empty($cap['live_verified']) || empty($decryptedSecrets['access_token'])) {
            return [
                'outcome'       => 'failed_permanent',
                'http_code'     => 401,
                'error_code'    => 'not_live_verified',
                'error_message' => 'Kanal ist nicht live verbunden/geprüft (' . $cap['reason'] . '). Es wurde nichts simuliert.',
                'retryable'     => false,
            ];
        }

        // Simulierter transienter Rate-Limit-Fehler (HTTP 429) ausschließlich für automatisierte Backoff-Tests
        if (!empty($snapshotVariant['metadata']['simulate_rate_limit']) && ((int) ($job['attempt_count'] ?? 0)) <= 1) {
            return [
                'outcome'       => 'failed_retryable',
                'http_code'     => 429,
                'error_code'    => 'rate_limit_exceeded',
                'error_message' => 'Plattform meldet vorübergehendes Rate-Limit (HTTP 429). Automatischer Retry mit Backoff.',
                'retryable'     => true,
            ];
        }

        $postGroup = is_array($snapshotVariant['_post_group'] ?? null) ? $snapshotVariant['_post_group'] : [];
        $effectiveText = (string) ($snapshotVariant['_effective_text'] ?? ($snapshotVariant['text_override'] ?? ($postGroup['master_text'] ?? '')));
        $mediaUrl = isset($snapshotVariant['_media_url']) && trim((string) $snapshotVariant['_media_url']) !== ''
            ? (string) $snapshotVariant['_media_url']
            : null;

        // Für Pull-APIs (Instagram/Threads) alternativ signierte Abhol-URL erzeugen, falls keine direkte URL vorliegt
        $mediaIds = (array) ($snapshotVariant['media_ids'] ?? []);
        $renditionKey = (string) ($snapshotVariant['rendition_key'] ?? '4:5');
        if ($mediaUrl === null && ($this->spec['media_transport'] ?? '') === 'pull_signed_url' && $mediaIds !== []) {
            $mediaUrl = $this->signedMediaEndpoint->createSignedPullUrl((string) $mediaIds[0], $renditionKey, 900);
        }

        // Echter HTTPS-Versand an die offizielle Plattform-API (keine simulierten Scheinergebnisse)
        return $this->liveApiClient->publishLive(
            $postGroup,
            $snapshotVariant,
            $account,
            $decryptedSecrets,
            $effectiveText,
            $mediaUrl
        );
    }

    private function resolveCharLimit(array $account): ?int
    {
        if ($this->platformKey === 'mastodon' && isset($account['instance_limits']['max_chars'])) {
            return (int) $account['instance_limits']['max_chars'];
        }
        $limit = $this->spec['default_char_limit'] ?? null;
        return is_int($limit) ? $limit : null;
    }
}
