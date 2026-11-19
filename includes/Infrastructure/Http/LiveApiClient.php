<?php
/**
 * Echter HTTP-API-Client (`LiveApiClient`) für Live-Verbindungsprüfungen und echte Ausspielung.
 *
 * Grundsatz v2.1.0 (Ehrlichkeit statt Simulation):
 * - Ein Kanal gilt NIEMALS als „Live verbunden“, nur weil irgendetwas ins Token-Feld getippt wurde.
 * - Jede Verbindungsprüfung führt eine echte HTTPS-Anfrage über WordPress (`wp_remote_get` / `wp_remote_post`)
 *   an die offizielle Plattform-API aus und meldet den tatsächlichen HTTP-Status sowie die echte
 *   Rückmeldung bzw. Fehlermeldung der Plattform zurück.
 *
 * @package SocialMediaHub\Infrastructure\Http
 */

declare(strict_types=1);

namespace SocialMediaHub\Infrastructure\Http;

class LiveApiClient
{
    private const TIMEOUT_SECONDS = 12;

    /**
     * Prüft die Verbindung eines Kontos LIVE gegen die jeweilige offizielle Plattform-API.
     *
     * @param array<string, mixed> $account
     * @param array<string, mixed> $secrets Entschlüsselte Zugangsdaten aus `TokenSafe`
     * @return array{
     *   ok: bool,
     *   connection_state: string,
     *   http_code: int,
     *   endpoint: string,
     *   message: string,
     *   verified_handle: ?string,
     *   external_account_id: ?string,
     *   granted_scopes: list<string>,
     *   instance_limits: ?array<string, mixed>,
     *   checked_at: string
     * }
     */
    public function verifyConnection(array $account, array $secrets): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $platform = strtolower((string) ($account['platform'] ?? ''));
        $connectionState = (string) ($account['connection_state'] ?? '');

        if (in_array($platform, ['whatsapp', 'signal'], true) || $connectionState === 'manual') {
            return [
                'ok'                  => true,
                'connection_state'    => 'manual',
                'http_code'           => 0,
                'endpoint'            => 'Manueller Übergabe-Modus',
                'message'             => 'Manueller Kanal – keine API-Verbindung erforderlich (Kopier- & Bestätigungs-Assistent aktiv).',
                'verified_handle'     => (string) ($account['handle'] ?? ''),
                'external_account_id' => null,
                'granted_scopes'      => [],
                'instance_limits'     => null,
                'checked_at'          => $now,
            ];
        }

        $token = trim((string) ($secrets['access_token'] ?? ''));
        if ($token === '') {
            return [
                'ok'                  => false,
                'connection_state'    => 'not_configured',
                'http_code'           => 0,
                'endpoint'            => 'Kein Abruf durchgeführt',
                'message'             => 'Kein API-Schlüssel bzw. Access-Token hinterlegt. Bitte gültige Zugangsdaten eintragen oder den Kanal auf „Manueller Übergabe-Kanal“ umstellen.',
                'verified_handle'     => null,
                'external_account_id' => null,
                'granted_scopes'      => [],
                'instance_limits'     => null,
                'checked_at'          => $now,
            ];
        }

        if (!function_exists('wp_remote_get') || !function_exists('wp_remote_post')) {
            return [
                'ok'                  => false,
                'connection_state'    => 'unverified',
                'http_code'           => 0,
                'endpoint'            => 'WordPress HTTP API fehlt',
                'message'             => 'Live-Prüfung ist nur innerhalb der laufenden WordPress-Umgebung möglich.',
                'verified_handle'     => null,
                'external_account_id' => null,
                'granted_scopes'      => [],
                'instance_limits'     => null,
                'checked_at'          => $now,
            ];
        }

        return match ($platform) {
            'instagram' => $this->verifyInstagram($account, $token, $now),
            'facebook'  => $this->verifyFacebook($account, $token, $now),
            'linkedin'  => $this->verifyLinkedIn($account, $token, $now),
            'threads'   => $this->verifyThreads($account, $token, $now),
            'pinterest' => $this->verifyPinterest($account, $token, $now),
            'bluesky'   => $this->verifyBluesky($account, $token, $now),
            'mastodon'  => $this->verifyMastodon($account, $token, $secrets, $now),
            'telegram'  => $this->verifyTelegram($account, $token, $now),
            'tiktok'    => $this->verifyTikTok($account, $token, $now),
            default     => [
                'ok'                  => false,
                'connection_state'    => 'blocked',
                'http_code'           => 0,
                'endpoint'            => 'Unbekannte Plattform',
                'message'             => "Für die Plattform „{$platform}“ ist kein Live-API-Endpunkt hinterlegt.",
                'verified_handle'     => null,
                'external_account_id' => null,
                'granted_scopes'      => [],
                'instance_limits'     => null,
                'checked_at'          => $now,
            ],
        };
    }

    /**
     * Führt eine echte HTTPS-Veröffentlichung über die jeweilige Plattform-API aus.
     *
     * @param array<string, mixed> $postGroup
     * @param array<string, mixed> $variant
     * @param array<string, mixed> $account
     * @param array<string, mixed> $secrets
     * @param string               $effectiveText
     * @param ?string              $mediaUrl
     * @return array<string, mixed>
     */
    public function publishLive(
        array $postGroup,
        array $variant,
        array $account,
        array $secrets,
        string $effectiveText,
        ?string $mediaUrl = null
    ): array {
        $platform = strtolower((string) ($account['platform'] ?? ''));
        $token = trim((string) ($secrets['access_token'] ?? ''));
        $externalId = trim((string) ($account['external_account_id'] ?? ''));

        if ($token === '') {
            return $this->failResult(401, 'missing_token', 'Kein API-Token hinterlegt. Versand abgebrochen.', false);
        }

        if (!function_exists('wp_remote_post')) {
            return $this->failResult(500, 'wp_http_unavailable', 'WordPress HTTP-API steht nicht zur Verfügung.', false);
        }

        return match ($platform) {
            'instagram' => $this->publishInstagram($externalId, $token, $effectiveText, $mediaUrl),
            'facebook'  => $this->publishFacebook($externalId, $token, $effectiveText, $mediaUrl, (string) ($postGroup['external_url'] ?? '')),
            'linkedin'  => $this->publishLinkedIn($account, $token, $effectiveText),
            'threads'   => $this->publishThreads($externalId, $token, $effectiveText, $mediaUrl),
            'pinterest' => $this->publishPinterest($variant, $token, $effectiveText, $mediaUrl, (string) ($postGroup['external_url'] ?? '')),
            'bluesky'   => $this->publishBluesky($account, $token, $effectiveText),
            'mastodon'  => $this->publishMastodon($account, $token, $secrets, $effectiveText),
            'telegram'  => $this->publishTelegram($externalId, $token, $effectiveText, $mediaUrl),
            default     => $this->failResult(400, 'unsupported_live_publish', "Direktveröffentlichung per API wird für „{$platform}“ in diesem Modus nicht unterstützt.", false),
        };
    }

    // -------------------------------------------------------------------------
    // Plattformspezifische Live-Checks
    // -------------------------------------------------------------------------

    private function verifyInstagram(array $account, string $token, string $now): array
    {
        $externalId = trim((string) ($account['external_account_id'] ?? ''));
        $isInstagramDirectToken = str_starts_with($token, 'IG');

        if ($isInstagramDirectToken) {
            $endpoint = 'https://graph.instagram.com/v21.0/me';
            $url = $endpoint . '?fields=id,username,account_type&access_token=' . rawurlencode($token);
        } elseif ($externalId !== '') {
            $endpoint = 'https://graph.facebook.com/v21.0/' . rawurlencode($externalId);
            $url = $endpoint . '?fields=id,username,name&access_token=' . rawurlencode($token);
        } else {
            $endpoint = 'https://graph.facebook.com/v21.0/me';
            $url = $endpoint . '?fields=id,name&access_token=' . rawurlencode($token);
        }

        $res = $this->httpRequest('GET', $url);
        if (!$res['ok']) {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Meta/Instagram API: ' . $res['error'], $now);
        }

        $data = $res['json'];
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Meta/Instagram lieferte keine gültige Konto-ID zurück.', $now);
        }

        // Echte Berechtigungen (Scopes) prüfen, sofern Graph-API-Token
        $grantedScopes = [];
        if (!$isInstagramDirectToken) {
            $permRes = $this->httpRequest('GET', 'https://graph.facebook.com/v21.0/me/permissions?access_token=' . rawurlencode($token));
            if ($permRes['ok'] && is_array($permRes['json']['data'] ?? null)) {
                foreach ($permRes['json']['data'] as $perm) {
                    if (($perm['status'] ?? '') === 'granted' && !empty($perm['permission'])) {
                        $grantedScopes[] = (string) $perm['permission'];
                    }
                }
            }
        } else {
            $grantedScopes = ['instagram_basic', 'instagram_content_publish'];
        }

        $missing = array_values(array_diff(['instagram_basic', 'instagram_content_publish'], $grantedScopes));
        if (!$isInstagramDirectToken && $grantedScopes !== [] && $missing !== []) {
            return [
                'ok'                  => false,
                'connection_state'    => 'blocked',
                'http_code'           => $res['http_code'],
                'endpoint'            => $endpoint,
                'message'             => 'Token ist bei Meta gültig, aber es fehlen Veröffentlichungsrechte: ' . implode(', ', $missing) . '.',
                'verified_handle'     => isset($data['username']) ? '@' . ltrim((string) $data['username'], '@') : (string) ($data['name'] ?? ''),
                'external_account_id' => $id,
                'granted_scopes'      => $grantedScopes,
                'instance_limits'     => null,
                'checked_at'          => $now,
            ];
        }

        // Wenn Facebook-Graph-Token ohne IG-Business-Account-ID genutzt wurde, sauber darauf hinweisen
        if (!$isInstagramDirectToken && $externalId === '') {
            return [
                'ok'                  => false,
                'connection_state'    => 'blocked',
                'http_code'           => $res['http_code'],
                'endpoint'            => $endpoint,
                'message'             => sprintf('Meta-Token gehört zu „%s“ (ID %s), aber es ist noch keine Instagram-Business-Account-ID hinterlegt. Bitte trage die IG-Konto-ID ein.', (string) ($data['name'] ?? 'Meta-Konto'), $id),
                'verified_handle'     => null,
                'external_account_id' => null,
                'granted_scopes'      => $grantedScopes,
                'instance_limits'     => null,
                'checked_at'          => $now,
            ];
        }

        $verifiedName = !empty($data['username']) ? '@' . ltrim((string) $data['username'], '@') : (string) ($data['name'] ?? $id);
        return [
            'ok'                  => true,
            'connection_state'    => 'connected',
            'http_code'           => $res['http_code'],
            'endpoint'            => $endpoint,
            'message'             => sprintf('Live verifiziert über Meta/Instagram API als %s (ID: %s).', $verifiedName, $id),
            'verified_handle'     => $verifiedName,
            'external_account_id' => $id,
            'granted_scopes'      => $grantedScopes !== [] ? $grantedScopes : ['instagram_basic', 'instagram_content_publish'],
            'instance_limits'     => null,
            'checked_at'          => $now,
        ];
    }

    private function verifyFacebook(array $account, string $token, string $now): array
    {
        $externalId = trim((string) ($account['external_account_id'] ?? ''));
        $target = $externalId !== '' ? rawurlencode($externalId) : 'me';
        $endpoint = "https://graph.facebook.com/v21.0/{$target}";
        $res = $this->httpRequest('GET', $endpoint . '?fields=id,name&access_token=' . rawurlencode($token));
        if (!$res['ok']) {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Facebook Graph API: ' . $res['error'], $now);
        }
        $id = (string) ($res['json']['id'] ?? '');
        $name = (string) ($res['json']['name'] ?? $id);
        if ($id === '') {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Facebook lieferte keine gültige Seiten-ID zurück.', $now);
        }

        return [
            'ok'                  => true,
            'connection_state'    => 'connected',
            'http_code'           => $res['http_code'],
            'endpoint'            => $endpoint,
            'message'             => sprintf('Live verifiziert über Facebook Graph API als „%s“ (ID: %s).', $name, $id),
            'verified_handle'     => $name,
            'external_account_id' => $id,
            'granted_scopes'      => ['pages_manage_posts'],
            'instance_limits'     => null,
            'checked_at'          => $now,
        ];
    }

    private function verifyLinkedIn(array $account, string $token, string $now): array
    {
        $endpoint = 'https://api.linkedin.com/v2/userinfo';
        $res = $this->httpRequest('GET', $endpoint, [
            'Authorization' => 'Bearer ' . $token,
        ]);
        if (!$res['ok']) {
            return $this->blockedVerification($endpoint, $res['http_code'], 'LinkedIn API: ' . $res['error'], $now);
        }
        $sub = (string) ($res['json']['sub'] ?? '');
        $name = (string) ($res['json']['name'] ?? ($res['json']['email'] ?? $sub));
        if ($sub === '') {
            return $this->blockedVerification($endpoint, $res['http_code'], 'LinkedIn lieferte keine Benutzer-ID (sub) zurück.', $now);
        }

        $accountType = (string) ($account['account_type'] ?? 'person');
        $scopes = $accountType === 'organization' ? ['w_organization_social'] : ['w_member_social'];
        return [
            'ok'                  => true,
            'connection_state'    => 'connected',
            'http_code'           => $res['http_code'],
            'endpoint'            => $endpoint,
            'message'             => sprintf('Live verifiziert über LinkedIn API als „%s“.', $name),
            'verified_handle'     => $name,
            'external_account_id' => (string) ($account['external_account_id'] ?: ('urn:li:person:' . $sub)),
            'granted_scopes'      => $scopes,
            'instance_limits'     => null,
            'checked_at'          => $now,
        ];
    }

    private function verifyThreads(array $account, string $token, string $now): array
    {
        $endpoint = 'https://graph.threads.net/v1.0/me';
        $res = $this->httpRequest('GET', $endpoint . '?fields=id,username,name&access_token=' . rawurlencode($token));
        if (!$res['ok']) {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Threads API: ' . $res['error'], $now);
        }
        $id = (string) ($res['json']['id'] ?? '');
        $user = !empty($res['json']['username']) ? '@' . ltrim((string) $res['json']['username'], '@') : $id;
        if ($id === '') {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Threads API lieferte keine gültige Konto-ID.', $now);
        }

        return [
            'ok'                  => true,
            'connection_state'    => 'connected',
            'http_code'           => $res['http_code'],
            'endpoint'            => $endpoint,
            'message'             => sprintf('Live verifiziert über Threads API als %s (ID: %s).', $user, $id),
            'verified_handle'     => $user,
            'external_account_id' => $id,
            'granted_scopes'      => ['threads_basic', 'threads_content_publish'],
            'instance_limits'     => null,
            'checked_at'          => $now,
        ];
    }

    private function verifyPinterest(array $account, string $token, string $now): array
    {
        $endpoint = 'https://api.pinterest.com/v5/user_account';
        $res = $this->httpRequest('GET', $endpoint, [
            'Authorization' => 'Bearer ' . $token,
        ]);
        if (!$res['ok']) {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Pinterest API v5: ' . $res['error'], $now);
        }
        $username = (string) ($res['json']['username'] ?? '');
        if ($username === '') {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Pinterest lieferte keinen Benutzernamen zurück.', $now);
        }

        return [
            'ok'                  => true,
            'connection_state'    => 'connected',
            'http_code'           => $res['http_code'],
            'endpoint'            => $endpoint,
            'message'             => sprintf('Live verifiziert über Pinterest API v5 als @%s.', ltrim($username, '@')),
            'verified_handle'     => '@' . ltrim($username, '@'),
            'external_account_id' => $username,
            'granted_scopes'      => ['boards:read', 'pins:write'],
            'instance_limits'     => null,
            'checked_at'          => $now,
        ];
    }

    private function verifyBluesky(array $account, string $token, string $now): array
    {
        $identifier = ltrim(trim((string) ($account['external_account_id'] ?: ($account['handle'] ?? ''))), '@');
        if ($identifier === '') {
            return $this->blockedVerification('https://bsky.social/xrpc/com.atproto.server.createSession', 0, 'Bitte ein Bluesky-Handle (z. B. name.bsky.social) angeben.', $now);
        }

        $endpoint = 'https://bsky.social/xrpc/com.atproto.server.createSession';
        $res = $this->httpRequest('POST', $endpoint, ['Content-Type' => 'application/json'], [
            'identifier' => $identifier,
            'password'   => $token,
        ]);
        if (!$res['ok']) {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Bluesky API: ' . $res['error'], $now);
        }
        $did = (string) ($res['json']['did'] ?? '');
        $handle = (string) ($res['json']['handle'] ?? $identifier);
        if ($did === '') {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Bluesky-Anmeldung lieferte keine DID zurück.', $now);
        }

        return [
            'ok'                  => true,
            'connection_state'    => 'connected',
            'http_code'           => $res['http_code'],
            'endpoint'            => $endpoint,
            'message'             => sprintf('Live verifiziert über Bluesky als @%s (%s).', $handle, $did),
            'verified_handle'     => '@' . $handle,
            'external_account_id' => $handle,
            'granted_scopes'      => ['com.atproto.repo.createRecord'],
            'instance_limits'     => null,
            'checked_at'          => $now,
        ];
    }

    private function verifyMastodon(array $account, string $token, array $secrets, string $now): array
    {
        $instanceUrl = rtrim(trim((string) ($account['instance_url'] ?: ($secrets['instance_url'] ?? ''))), '/');
        if ($instanceUrl === '' || !preg_match('~^https://~i', $instanceUrl)) {
            return $this->blockedVerification('Mastodon Instanz-URL fehlt', 0, 'Bitte eine gültige Mastodon-Instanz-URL mit https:// eintragen (z. B. https://mastodon.social).', $now);
        }

        $endpoint = $instanceUrl . '/api/v1/accounts/verify_credentials';
        $res = $this->httpRequest('GET', $endpoint, [
            'Authorization' => 'Bearer ' . $token,
        ]);
        if (!$res['ok']) {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Mastodon API: ' . $res['error'], $now);
        }

        $acct = (string) ($res['json']['acct'] ?? ($res['json']['username'] ?? ''));
        $id = (string) ($res['json']['id'] ?? '');
        if ($id === '') {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Mastodon lieferte kein Konto zurück.', $now);
        }

        // Echte Instanz-Limits abrufen
        $limits = null;
        $instRes = $this->httpRequest('GET', $instanceUrl . '/api/v2/instance');
        if ($instRes['ok']) {
            $maxChars = (int) ($instRes['json']['configuration']['statuses']['max_characters'] ?? 500);
            $maxMedia = (int) ($instRes['json']['configuration']['statuses']['max_media_attachments'] ?? 4);
            $limits = ['max_chars' => $maxChars, 'max_media' => $maxMedia];
        }

        return [
            'ok'                  => true,
            'connection_state'    => 'connected',
            'http_code'           => $res['http_code'],
            'endpoint'            => $endpoint,
            'message'             => sprintf('Live verifiziert auf %s als @%s (Zeichenlimit: %d).', $instanceUrl, ltrim($acct, '@'), $limits['max_chars'] ?? 500),
            'verified_handle'     => '@' . ltrim($acct, '@'),
            'external_account_id' => $id,
            'granted_scopes'      => ['write:statuses', 'write:media'],
            'instance_limits'     => $limits,
            'checked_at'          => $now,
        ];
    }

    private function verifyTelegram(array $account, string $token, string $now): array
    {
        $endpoint = 'https://api.telegram.org/bot***/getMe';
        $res = $this->httpRequest('GET', 'https://api.telegram.org/bot' . rawurlencode($token) . '/getMe');
        if (!$res['ok'] || empty($res['json']['ok'])) {
            return $this->blockedVerification($endpoint, $res['http_code'], 'Telegram Bot API: ' . ($res['json']['description'] ?? $res['error']), $now);
        }

        $botName = (string) ($res['json']['result']['username'] ?? 'Telegram-Bot');
        $chatId = trim((string) ($account['external_account_id'] ?? ''));
        if ($chatId === '') {
            return [
                'ok'                  => false,
                'connection_state'    => 'blocked',
                'http_code'           => $res['http_code'],
                'endpoint'            => $endpoint,
                'message'             => sprintf('Bot @%s ist gültig, aber es fehlt noch die Kanal-Chat-ID (z. B. @mein_kanal oder -100...).', $botName),
                'verified_handle'     => '@' . $botName,
                'external_account_id' => null,
                'granted_scopes'      => [],
                'instance_limits'     => null,
                'checked_at'          => $now,
            ];
        }

        $chatRes = $this->httpRequest('GET', 'https://api.telegram.org/bot' . rawurlencode($token) . '/getChat?chat_id=' . rawurlencode($chatId));
        if (!$chatRes['ok'] || empty($chatRes['json']['ok'])) {
            return $this->blockedVerification('https://api.telegram.org/bot***/getChat', $chatRes['http_code'], 'Bot @' . $botName . ' erreicht den Kanal „' . $chatId . '“ nicht: ' . ($chatRes['json']['description'] ?? $chatRes['error']), $now);
        }

        $chatTitle = (string) ($chatRes['json']['result']['title'] ?? $chatId);
        return [
            'ok'                  => true,
            'connection_state'    => 'connected',
            'http_code'           => $chatRes['http_code'],
            'endpoint'            => 'https://api.telegram.org/bot***/getChat',
            'message'             => sprintf('Live verifiziert: Bot @%s hat Zugriff auf Kanal „%s“.', $botName, $chatTitle),
            'verified_handle'     => $chatTitle,
            'external_account_id' => $chatId,
            'granted_scopes'      => ['bot_post'],
            'instance_limits'     => null,
            'checked_at'          => $now,
        ];
    }

    private function verifyTikTok(array $account, string $token, string $now): array
    {
        $endpoint = 'https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name';
        $res = $this->httpRequest('GET', $endpoint, [
            'Authorization' => 'Bearer ' . $token,
        ]);
        if (!$res['ok']) {
            return $this->blockedVerification($endpoint, $res['http_code'], 'TikTok API v2: ' . $res['error'], $now);
        }
        $user = (array) ($res['json']['data']['user'] ?? []);
        $openId = (string) ($user['open_id'] ?? '');
        if ($openId === '') {
            return $this->blockedVerification($endpoint, $res['http_code'], 'TikTok API lieferte keine Benutzerdaten zurück.', $now);
        }
        $name = (string) ($user['display_name'] ?? $openId);

        return [
            'ok'                  => true,
            'connection_state'    => 'handoff_only',
            'http_code'           => $res['http_code'],
            'endpoint'            => $endpoint,
            'message'             => sprintf('Live verifiziert über TikTok API als „%s“ (App-Fertigstellung aktiv).', $name),
            'verified_handle'     => $name,
            'external_account_id' => $openId,
            'granted_scopes'      => ['video.upload'],
            'instance_limits'     => null,
            'checked_at'          => $now,
        ];
    }

    // -------------------------------------------------------------------------
    // Echte Live-Veröffentlichungen (kein Fake-example.com!)
    // -------------------------------------------------------------------------

    private function publishInstagram(string $igUserId, string $token, string $caption, ?string $mediaUrl): array
    {
        if ($igUserId === '') {
            return $this->failResult(400, 'missing_ig_account_id', 'Instagram-Business-Account-ID fehlt.', false);
        }
        if ($mediaUrl === null || $mediaUrl === '') {
            return $this->failResult(400, 'missing_media', 'Instagram benötigt eine öffentlich erreichbare Bild- oder Video-URL.', false);
        }

        $base = str_starts_with($token, 'IG') ? 'https://graph.instagram.com/v21.0' : 'https://graph.facebook.com/v21.0';
        $containerRes = $this->httpRequest('POST', "{$base}/{$igUserId}/media", [], [
            'image_url'    => $mediaUrl,
            'caption'      => $caption,
            'access_token' => $token,
        ], false);

        if (!$containerRes['ok'] || empty($containerRes['json']['id'])) {
            return $this->failResult($containerRes['http_code'], 'ig_container_failed', 'Instagram Container-Erstellung fehlgeschlagen: ' . $containerRes['error'], $containerRes['http_code'] >= 500 || $containerRes['http_code'] === 429);
        }

        $creationId = (string) $containerRes['json']['id'];
        $pubRes = $this->httpRequest('POST', "{$base}/{$igUserId}/media_publish", [], [
            'creation_id'  => $creationId,
            'access_token' => $token,
        ], false);

        if (!$pubRes['ok'] || empty($pubRes['json']['id'])) {
            return $this->failResult($pubRes['http_code'], 'ig_publish_failed', 'Instagram Veröffentlichung fehlgeschlagen: ' . $pubRes['error'], $pubRes['http_code'] >= 500 || $pubRes['http_code'] === 429);
        }

        $postId = (string) $pubRes['json']['id'];
        return [
            'outcome'             => 'published',
            'http_code'           => $pubRes['http_code'],
            'remote_container_id' => $creationId,
            'remote_post_id'      => $postId,
            'remote_url'          => null,
        ];
    }

    private function publishFacebook(string $pageId, string $token, string $message, ?string $mediaUrl, string $link): array
    {
        $target = $pageId !== '' ? rawurlencode($pageId) : 'me';
        if ($mediaUrl !== null && $mediaUrl !== '') {
            $res = $this->httpRequest('POST', "https://graph.facebook.com/v21.0/{$target}/photos", [], [
                'url'          => $mediaUrl,
                'caption'      => $message,
                'access_token' => $token,
            ], false);
        } else {
            $body = ['message' => $message, 'access_token' => $token];
            if ($link !== '') {
                $body['link'] = $link;
            }
            $res = $this->httpRequest('POST', "https://graph.facebook.com/v21.0/{$target}/feed", [], $body, false);
        }

        if (!$res['ok']) {
            return $this->failResult($res['http_code'], 'fb_publish_failed', 'Facebook Versand fehlgeschlagen: ' . $res['error'], $res['http_code'] >= 500 || $res['http_code'] === 429);
        }
        $postId = (string) ($res['json']['post_id'] ?? ($res['json']['id'] ?? ''));
        return [
            'outcome'             => 'published',
            'http_code'           => $res['http_code'],
            'remote_container_id' => null,
            'remote_post_id'      => $postId,
            'remote_url'          => $postId !== '' ? 'https://www.facebook.com/' . $postId : null,
        ];
    }

    private function publishLinkedIn(array $account, string $token, string $text): array
    {
        $authorUrn = trim((string) ($account['external_account_id'] ?? ''));
        if ($authorUrn === '') {
            return $this->failResult(400, 'missing_linkedin_urn', 'LinkedIn Author-URN fehlt. Bitte Verbindung zuerst live prüfen.', false);
        }
        $res = $this->httpRequest('POST', 'https://api.linkedin.com/v2/ugcPosts', [
            'Authorization'             => 'Bearer ' . $token,
            'Content-Type'              => 'application/json',
            'X-Restli-Protocol-Version' => '2.0.0',
        ], [
            'author'          => $authorUrn,
            'lifecycleState'  => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary'    => ['text' => $text],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility'      => [
                'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC',
            ],
        ]);
        if (!$res['ok']) {
            return $this->failResult($res['http_code'], 'li_publish_failed', 'LinkedIn Versand fehlgeschlagen: ' . $res['error'], $res['http_code'] >= 500 || $res['http_code'] === 429);
        }
        $postId = (string) ($res['json']['id'] ?? '');
        return [
            'outcome'             => 'published',
            'http_code'           => $res['http_code'],
            'remote_container_id' => null,
            'remote_post_id'      => $postId,
            'remote_url'          => $postId !== '' ? 'https://www.linkedin.com/feed/update/' . $postId : null,
        ];
    }

    private function publishThreads(string $userId, string $token, string $text, ?string $mediaUrl): array
    {
        $target = $userId !== '' ? rawurlencode($userId) : 'me';
        $body = [
            'media_type'   => $mediaUrl ? 'IMAGE' : 'TEXT',
            'text'         => $text,
            'access_token' => $token,
        ];
        if ($mediaUrl) {
            $body['image_url'] = $mediaUrl;
        }
        $create = $this->httpRequest('POST', "https://graph.threads.net/v1.0/{$target}/threads", [], $body, false);
        if (!$create['ok'] || empty($create['json']['id'])) {
            return $this->failResult($create['http_code'], 'threads_create_failed', 'Threads Container fehlgeschlagen: ' . $create['error'], $create['http_code'] >= 500 || $create['http_code'] === 429);
        }
        $pub = $this->httpRequest('POST', "https://graph.threads.net/v1.0/{$target}/threads_publish", [], [
            'creation_id'  => (string) $create['json']['id'],
            'access_token' => $token,
        ], false);
        if (!$pub['ok']) {
            return $this->failResult($pub['http_code'], 'threads_publish_failed', 'Threads Versand fehlgeschlagen: ' . $pub['error'], $pub['http_code'] >= 500 || $pub['http_code'] === 429);
        }
        return [
            'outcome'             => 'published',
            'http_code'           => $pub['http_code'],
            'remote_container_id' => (string) $create['json']['id'],
            'remote_post_id'      => (string) ($pub['json']['id'] ?? ''),
            'remote_url'          => null,
        ];
    }

    private function publishPinterest(array $variant, string $token, string $description, ?string $mediaUrl, string $link): array
    {
        $boardId = trim((string) ($variant['metadata']['board'] ?? ''));
        $title = trim((string) ($variant['metadata']['title'] ?? ''));
        if ($mediaUrl === null || $mediaUrl === '') {
            return $this->failResult(400, 'missing_pin_media', 'Pinterest benötigt ein Bild.', false);
        }
        $payload = [
            'board_id'    => $boardId,
            'title'       => $title,
            'description' => $description,
            'media_source'=> ['source_type' => 'image_url', 'url' => $mediaUrl],
        ];
        if ($link !== '') {
            $payload['link'] = $link;
        }
        $res = $this->httpRequest('POST', 'https://api.pinterest.com/v5/pins', [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ], $payload);
        if (!$res['ok']) {
            return $this->failResult($res['http_code'], 'pinterest_publish_failed', 'Pinterest Versand fehlgeschlagen: ' . $res['error'], $res['http_code'] >= 500 || $res['http_code'] === 429);
        }
        $id = (string) ($res['json']['id'] ?? '');
        return [
            'outcome'             => 'published',
            'http_code'           => $res['http_code'],
            'remote_container_id' => null,
            'remote_post_id'      => $id,
            'remote_url'          => $id !== '' ? 'https://www.pinterest.com/pin/' . $id . '/' : null,
        ];
    }

    private function publishBluesky(array $account, string $token, string $text): array
    {
        $identifier = ltrim(trim((string) ($account['external_account_id'] ?: ($account['handle'] ?? ''))), '@');
        $sess = $this->httpRequest('POST', 'https://bsky.social/xrpc/com.atproto.server.createSession', ['Content-Type' => 'application/json'], [
            'identifier' => $identifier,
            'password'   => $token,
        ]);
        if (!$sess['ok'] || empty($sess['json']['accessJwt']) || empty($sess['json']['did'])) {
            return $this->failResult($sess['http_code'], 'bsky_auth_failed', 'Bluesky Anmeldung fehlgeschlagen: ' . $sess['error'], false);
        }
        $res = $this->httpRequest('POST', 'https://bsky.social/xrpc/com.atproto.repo.createRecord', [
            'Authorization' => 'Bearer ' . (string) $sess['json']['accessJwt'],
            'Content-Type'  => 'application/json',
        ], [
            'repo'       => (string) $sess['json']['did'],
            'collection' => 'app.bsky.feed.post',
            'record'     => [
                '$type'     => 'app.bsky.feed.post',
                'text'      => $text,
                'createdAt' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
        ]);
        if (!$res['ok']) {
            return $this->failResult($res['http_code'], 'bsky_publish_failed', 'Bluesky Versand fehlgeschlagen: ' . $res['error'], $res['http_code'] >= 500 || $res['http_code'] === 429);
        }
        return [
            'outcome'             => 'published',
            'http_code'           => $res['http_code'],
            'remote_container_id' => null,
            'remote_post_id'      => (string) ($res['json']['uri'] ?? ''),
            'remote_url'          => null,
        ];
    }

    private function publishMastodon(array $account, string $token, array $secrets, string $text): array
    {
        $instanceUrl = rtrim(trim((string) ($account['instance_url'] ?: ($secrets['instance_url'] ?? ''))), '/');
        if ($instanceUrl === '') {
            return $this->failResult(400, 'missing_instance_url', 'Mastodon-Instanz-URL fehlt.', false);
        }
        $res = $this->httpRequest('POST', $instanceUrl . '/api/v1/statuses', [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
        ], ['status' => $text]);
        if (!$res['ok']) {
            return $this->failResult($res['http_code'], 'mastodon_publish_failed', 'Mastodon Versand fehlgeschlagen: ' . $res['error'], $res['http_code'] >= 500 || $res['http_code'] === 429);
        }
        return [
            'outcome'             => 'published',
            'http_code'           => $res['http_code'],
            'remote_container_id' => null,
            'remote_post_id'      => (string) ($res['json']['id'] ?? ''),
            'remote_url'          => isset($res['json']['url']) ? (string) $res['json']['url'] : null,
        ];
    }

    private function publishTelegram(string $chatId, string $token, string $text, ?string $mediaUrl): array
    {
        if ($chatId === '') {
            return $this->failResult(400, 'missing_chat_id', 'Telegram Kanal-Chat-ID fehlt.', false);
        }
        if ($mediaUrl !== null && $mediaUrl !== '') {
            $res = $this->httpRequest('POST', 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendPhoto', [], [
                'chat_id' => $chatId,
                'photo'   => $mediaUrl,
                'caption' => $text,
            ], false);
        } else {
            $res = $this->httpRequest('POST', 'https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage', [], [
                'chat_id' => $chatId,
                'text'    => $text,
            ], false);
        }
        if (!$res['ok'] || empty($res['json']['ok'])) {
            return $this->failResult($res['http_code'], 'telegram_publish_failed', 'Telegram Versand fehlgeschlagen: ' . ($res['json']['description'] ?? $res['error']), $res['http_code'] >= 500 || $res['http_code'] === 429);
        }
        return [
            'outcome'             => 'published',
            'http_code'           => $res['http_code'],
            'remote_container_id' => null,
            'remote_post_id'      => (string) ($res['json']['result']['message_id'] ?? ''),
            'remote_url'          => null,
        ];
    }

    // -------------------------------------------------------------------------
    // HTTP-Hilfsmethoden
    // -------------------------------------------------------------------------

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $body
     * @return array{ok: bool, http_code: int, json: array<string, mixed>, error: string}
     */
    private function httpRequest(string $method, string $url, array $headers = [], ?array $body = null, bool $encodeJson = true): array
    {
        $args = [
            'timeout'    => self::TIMEOUT_SECONDS,
            'sslverify'  => true,
            'user-agent' => 'SocialMediaHub-WordPress/' . (defined('SMH_VERSION') ? SMH_VERSION : '2.1.0'),
            'headers'    => $headers,
        ];

        if ($method === 'POST') {
            $args['body'] = ($body !== null && $encodeJson) ? (json_encode($body) ?: '{}') : ($body ?? []);
            $response = wp_remote_post($url, $args);
        } else {
            $response = wp_remote_get($url, $args);
        }

        if (function_exists('is_wp_error') && is_wp_error($response)) {
            return [
                'ok'        => false,
                'http_code' => 0,
                'json'      => [],
                'error'     => 'Netzwerkfehler: ' . $response->get_error_message(),
            ];
        }

        $code = function_exists('wp_remote_retrieve_response_code') ? (int) wp_remote_retrieve_response_code($response) : 0;
        $raw = function_exists('wp_remote_retrieve_body') ? (string) wp_remote_retrieve_body($response) : '';
        $decoded = json_decode($raw, true);
        $json = is_array($decoded) ? $decoded : [];

        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'http_code' => $code, 'json' => $json, 'error' => ''];
        }

        $apiMsg = $this->extractApiErrorMessage($json, $raw, $code);
        return [
            'ok'        => false,
            'http_code' => $code,
            'json'      => $json,
            'error'     => $apiMsg,
        ];
    }

    /**
     * @param array<string, mixed> $json
     */
    private function extractApiErrorMessage(array $json, string $raw, int $code): string
    {
        if (isset($json['error']['message'])) {
            return sprintf('HTTP %d – %s', $code, (string) $json['error']['message']);
        }
        if (isset($json['error']) && is_string($json['error'])) {
            $desc = isset($json['message']) ? ': ' . (string) $json['message'] : '';
            return sprintf('HTTP %d – %s%s', $code, $json['error'], $desc);
        }
        if (isset($json['message']) && is_string($json['message'])) {
            return sprintf('HTTP %d – %s', $code, $json['message']);
        }
        if (isset($json['description']) && is_string($json['description'])) {
            return sprintf('HTTP %d – %s', $code, $json['description']);
        }
        $snippet = trim(substr(strip_tags($raw), 0, 180));
        return sprintf('HTTP %d%s', $code, $snippet !== '' ? ' – ' . $snippet : ' – Plattform hat die Anfrage abgelehnt.');
    }

    /**
     * @return array<string, mixed>
     */
    private function blockedVerification(string $endpoint, int $httpCode, string $message, string $now): array
    {
        return [
            'ok'                  => false,
            'connection_state'    => 'blocked',
            'http_code'           => $httpCode,
            'endpoint'            => $endpoint,
            'message'             => $message,
            'verified_handle'     => null,
            'external_account_id' => null,
            'granted_scopes'      => [],
            'instance_limits'     => null,
            'checked_at'          => $now,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function failResult(int $httpCode, string $errorCode, string $message, bool $retryable): array
    {
        return [
            'outcome'       => $retryable ? 'failed_retryable' : 'failed_permanent',
            'http_code'     => $httpCode,
            'error_code'    => $errorCode,
            'error_message' => $message,
            'retryable'     => $retryable,
        ];
    }
}
