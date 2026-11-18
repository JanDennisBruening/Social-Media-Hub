<?php
/**
 * Konfigurierbares Plattform-Steckmodul (`ConfigurablePlatformProvider`).
 *
 * @package SocialMediaHub\Providers
 */

declare(strict_types=1);

namespace SocialMediaHub\Providers;

use SocialMediaHub\Infrastructure\Security\SignedMediaEndpoint;

class ConfigurablePlatformProvider implements ProviderInterface
{
    private string $platformKey;
    /** @var array<string, mixed> */
    private array $spec;
    private SignedMediaEndpoint $signedMediaEndpoint;

    /**
     * @param array<string, mixed> $spec
     */
    public function __construct(string $platformKey, array $spec, SignedMediaEndpoint $signedMediaEndpoint)
    {
        $this->platformKey = $platformKey;
        $this->spec = $spec;
        $this->signedMediaEndpoint = $signedMediaEndpoint;
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
        $connectionState = (string) ($account['connection_state'] ?? 'unverified');
        $grantedScopes = array_values(array_map('strval', (array) ($account['granted_scopes'] ?? [])));
        $expiresAt = isset($account['token_expires_at']) ? (string) $account['token_expires_at'] : '';

        if (in_array($this->platformKey, ['whatsapp', 'signal'], true) || $connectionState === 'manual') {
            return [
                'status'         => 'manual',
                'delivery_mode'  => 'manual',
                'reason'         => 'Manueller Übergabe-Kanal (Text kopieren, Bild laden & Veröffentlichung bestätigen).',
                'missing_scopes' => [],
                'token_expired'  => false,
                'evaluated_at'   => $now,
            ];
        }

        if ($connectionState === 'unverified' || $connectionState === 'unknown') {
            return [
                'status'         => 'unknown',
                'delivery_mode'  => 'unknown',
                'reason'         => 'Verbindung und Veröffentlichungsrechte wurden noch nicht geprüft.',
                'missing_scopes' => [],
                'token_expired'  => false,
                'evaluated_at'   => $now,
            ];
        }

        $tokenExpired = ($connectionState === 'expired')
            || ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) <= time());

        $requiredByType = (array) ($this->spec['required_scopes'][$accountType] ?? []);
        $missingScopes = array_values(array_diff($requiredByType, $grantedScopes));

        if ($tokenExpired || $missingScopes !== [] || $connectionState === 'blocked') {
            $reasonParts = [];
            if ($tokenExpired) {
                $reasonParts[] = 'Zugangstoken ist abgelaufen';
            }
            if ($missingScopes !== []) {
                $reasonParts[] = 'Fehlende Berechtigung (' . implode(', ', $missingScopes) . ')';
            }
            if ($reasonParts === []) {
                $reasonParts[] = 'Konto-Autorisierung ungültig oder gesperrt';
            }
            return [
                'status'         => 'blocked',
                'delivery_mode'  => 'blocked',
                'reason'         => implode(' · ', $reasonParts) . '. Bitte Konto neu autorisieren.',
                'missing_scopes' => $missingScopes,
                'token_expired'  => $tokenExpired,
                'evaluated_at'   => $now,
            ];
        }

        if ($connectionState === 'handoff_only' || ($this->platformKey === 'tiktok' && !in_array('video.publish', $grantedScopes, true))) {
            return [
                'status'         => 'handoff',
                'delivery_mode'  => 'handoff',
                'reason'         => 'Upload an die Ziel-App möglich; finaler Abschluss erfolgt in der App.',
                'missing_scopes' => [],
                'token_expired'  => false,
                'evaluated_at'   => $now,
            ];
        }

        return [
            'status'         => 'ready',
            'delivery_mode'  => 'api',
            'reason'         => 'Konto verbunden und Berechtigungen gültig.',
            'missing_scopes' => [],
            'token_expired'  => false,
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

        foreach ($matchingMedia as $mediaItem) {
            if (($mediaItem['kind'] ?? '') === 'image' && trim((string) ($mediaItem['alt_text'] ?? '')) === '') {
                $advice[] = sprintf('Für „%s“ fehlt noch ein Alt-Text (Barrierefreiheit).', (string) ($mediaItem['name'] ?? 'Bild'));
            }
        }

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

        $hasLinkInText = (bool) preg_match('~https?://\S+~i', $effectiveText);
        if ($this->platformKey === 'instagram' && ($externalUrl !== '' || $hasLinkInText) && $formatKey !== 'story') {
            if (empty($variant['metadata']['bio_link_hint']) && !str_contains(mb_strtolower($effectiveText), 'bio')) {
                $advice[] = 'Links sind in Instagram-Captions nicht klickbar. Empfehlung: Hinweis „Link in der Bio“ aktivieren.';
            }
        }

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

        $cap = $this->evaluateAccountCapabilities($account);
        $method = $cap['delivery_mode'];

        if ($this->platformKey === 'instagram' && $formatKey === 'story' && !empty($meta['native_extras'])) {
            $method = 'manual';
            $advice[] = 'Story mit nativen Stickern/Musik ist als manuelle Übergabe eingeplant.';
        }

        if ($cap['status'] === 'blocked') {
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

        if ($cap['status'] === 'blocked' || empty($decryptedSecrets['access_token'])) {
            return [
                'outcome'       => 'failed_permanent',
                'http_code'     => 401,
                'error_code'    => 'auth_expired_or_missing_scope',
                'error_message' => $cap['reason'],
                'retryable'     => false,
            ];
        }

        if (!empty($snapshotVariant['metadata']['simulate_rate_limit']) && ((int) ($job['attempt_count'] ?? 0)) < 1) {
            return [
                'outcome'       => 'failed_retryable',
                'http_code'     => 429,
                'error_code'    => 'rate_limit_exceeded',
                'error_message' => 'Plattform meldet vorübergehendes Rate-Limit (HTTP 429). Automatischer Retry mit Backoff.',
                'retryable'     => true,
            ];
        }

        $mediaIds = (array) ($snapshotVariant['media_ids'] ?? []);
        $renditionKey = (string) ($snapshotVariant['rendition_key'] ?? '4:5');
        $signedPullUrl = null;
        if (($this->spec['media_transport'] ?? '') === 'pull_signed_url' && $mediaIds !== []) {
            $signedPullUrl = $this->signedMediaEndpoint->createSignedPullUrl((string) $mediaIds[0], $renditionKey, 900);
        }

        $remotePostId = $this->platformKey . '_' . substr(hash('sha256', (string) ($job['idempotency_key'] ?? uniqid())), 0, 12);
        $handleSlug = preg_replace('/[^a-z0-9._-]+/i', '', ltrim((string) ($account['handle'] ?? 'account'), '@')) ?: 'post';
        $remoteUrl = sprintf('https://%s.example.com/%s/posts/%s', $this->platformKey, strtolower($handleSlug), $remotePostId);

        return [
            'outcome'             => 'published',
            'http_code'           => 200,
            'remote_container_id' => $signedPullUrl !== null ? 'cnt_' . substr(md5($signedPullUrl), 0, 10) : null,
            'remote_post_id'      => $remotePostId,
            'remote_url'          => $remoteUrl,
            'signed_pull_url'     => $signedPullUrl,
        ];
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
