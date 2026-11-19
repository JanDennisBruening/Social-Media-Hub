<?php
/**
 * Zentrale Repository-Schicht (v2.0.0) für Marken, Konten, Medien, Textbausteine,
 * Beitragsverbünde, Varianten, Revisions-Snapshots, Queue-Jobs, Nachweise und Audit-Events.
 *
 * Grundsatz v2.0.0:
 * Startet 100 % sauber und frei von Demo-Einträgen – der Nutzer beginnt wie am ersten Tag.
 * Enthält vollständige CRUD-, Mehrfachauswahl-, Verschiebe-, Duplizier- und Lösch-Operationen.
 *
 * @package SocialMediaHub\Infrastructure\Database
 */

declare(strict_types=1);

namespace SocialMediaHub\Infrastructure\Database;

use SocialMediaHub\Infrastructure\Security\TokenSafe;

class Repositories
{
    private const STATE_OPTION        = 'smh_repository_state';
    private const DATA_VERSION_OPTION = 'smh_data_version';

    /** @var array<string, mixed> */
    private array $state;
    private TokenSafe $tokenSafe;

    public function __construct(?TokenSafe $tokenSafe = null, ?array $initialState = null)
    {
        $this->tokenSafe = $tokenSafe ?? new TokenSafe();
        $this->state = $initialState ?? $this->loadPersistedOrEmptyState();
    }

    /**
     * Lädt den gespeicherten Zustand oder legt einen komplett sauberen, leeren Arbeitsbereich an.
     * Beim Upgrade von v1.0.0 auf v2.0.0 werden alte, unveränderte v1.0-Demo-Seeddaten
     * einmalig entfernt, damit der Hub sauber und frei von Demo-Müll startet.
     *
     * @return array<string, mixed>
     */
    public function loadPersistedOrEmptyState(): array
    {
        if (function_exists('get_option')) {
            $dataVersion = (string) get_option(self::DATA_VERSION_OPTION, '');
            $saved = get_option(self::STATE_OPTION, null);

            if ($dataVersion !== '2.0.0') {
                if (is_array($saved) && $this->isLegacyV1DemoState($saved)) {
                    $clean = $this->createEmptyWorkspaceState();
                    update_option(self::STATE_OPTION, $clean, false);
                    update_option(self::DATA_VERSION_OPTION, '2.0.0', false);
                    return $clean;
                }
                if (function_exists('update_option')) {
                    update_option(self::DATA_VERSION_OPTION, '2.0.0', false);
                }
            }

            if (is_array($saved) && isset($saved['schema_version'])) {
                return $this->ensureStateShape($saved);
            }
        }

        return $this->createEmptyWorkspaceState();
    }

    public function persist(): void
    {
        if (function_exists('update_option')) {
            update_option(self::STATE_OPTION, $this->state, false);
            update_option(self::DATA_VERSION_OPTION, '2.0.0', false);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function &getStateRef(): array
    {
        return $this->state;
    }

    /**
     * Setzt den gesamten Arbeitsbereich auf den komplett leeren, sauberen Ausgangszustand zurück.
     */
    public function resetToEmpty(): void
    {
        $this->state = $this->createEmptyWorkspaceState();
        $this->persist();
    }

    /**
     * Erzeugt einen komplett sauberen, leeren Arbeitsbereich ohne Demo-Beiträge oder Fake-Konten.
     *
     * @return array<string, mixed>
     */
    public function createEmptyWorkspaceState(): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');

        return [
            'schema_version' => 2,
            'brands' => [
                [
                    'id'               => 'default',
                    'slug'             => 'standard',
                    'name'             => 'Hauptprofil',
                    'color'            => '#586EE7',
                    'description'      => 'Standard-Arbeitsbereich (kann jederzeit umbenannt oder um weitere Marken ergänzt werden)',
                    'require_approval' => false,
                    'allowed_user_ids' => [],
                    'default_utm'      => ['utm_source' => '{platform}', 'utm_medium' => 'social'],
                    'created_at'       => $now,
                ],
            ],
            'accounts'         => [],
            'media_assets'     => [],
            'snippets'         => [],
            'post_groups'      => [],
            'revisions'        => [],
            'jobs'             => [],
            'receipts'         => [],
            'interactions'     => [],
            'automation_rules' => [
                [
                    'id'      => 'rule_auto_queue_on_approval',
                    'name'    => 'Automatisch nach Freigabe zum Wunschtermin einplanen',
                    'detail'  => 'Reiht alle verbundenen API-Kanäle automatisch in die Versand-Warteschlange ein.',
                    'enabled' => true,
                ],
                [
                    'id'      => 'rule_48h_token_preflight',
                    'name'    => '48-Stunden-Vorab-Check der Kanalverbindungen',
                    'detail'  => 'Prüft 48 Stunden vor geplanten Beiträgen, ob alle Kanal-Zugänge gültig sind.',
                    'enabled' => true,
                ],
                [
                    'id'      => 'rule_retry_transient_errors',
                    'name'    => 'Automatische Wiederholung bei kurzer Plattform-Störung (Max. 3×)',
                    'detail'  => 'Wiederholt nur vorübergehende Fehler (HTTP 429 / 5xx). Stoppt sofort bei Zugangsproblemen.',
                    'enabled' => true,
                ],
                [
                    'id'      => 'rule_manual_handoff_reminder',
                    'name'    => 'Erinnerung für manuelle Kanäle (z. B. WhatsApp-Kanal)',
                    'detail'  => 'Zeigt fällige manuelle Beiträge direkt unter „Manuelle Übergaben“ hervorgehoben an.',
                    'enabled' => true,
                ],
            ],
            'audit_events' => [
                [
                    'id'            => 'aud_init_v2',
                    'brand_id'      => 'default',
                    'post_group_id' => null,
                    'account_id'    => null,
                    'event_type'    => 'system.initialized',
                    'title'         => 'Social Media Hub v2.0 sauber eingerichtet',
                    'detail'        => 'Bereit für deine ersten Kanäle und Beiträge.',
                    'actor_id'      => 1,
                    'created_at'    => $now,
                ],
            ],
        ];
    }

    /**
     * Erkennt den unveränderten v1.0-Demo-Seedbestand anhand seiner festen Demo-IDs.
     *
     * @param array<string, mixed> $saved
     */
    private function isLegacyV1DemoState(array $saved): bool
    {
        $postIds = array_map(static fn(array $p): string => (string) ($p['id'] ?? ''), (array) ($saved['post_groups'] ?? []));
        $legacyDemoIds = ['pg_classic_1', 'pg_youtube_hint', 'pg_nui_hello'];
        $onlyLegacyPosts = $postIds !== [] && array_diff($postIds, $legacyDemoIds) === [];
        $hasLegacyBrand = false;
        foreach ((array) ($saved['brands'] ?? []) as $b) {
            if (($b['id'] ?? '') === 'demo_client') {
                $hasLegacyBrand = true;
                break;
            }
        }
        return $onlyLegacyPosts || ($hasLegacyBrand && $postIds === []);
    }

    /**
     * @param array<string, mixed> $saved
     * @return array<string, mixed>
     */
    private function ensureStateShape(array $saved): array
    {
        $defaults = $this->createEmptyWorkspaceState();
        foreach (['brands', 'accounts', 'media_assets', 'snippets', 'post_groups', 'revisions', 'jobs', 'receipts', 'interactions', 'automation_rules', 'audit_events'] as $key) {
            if (!isset($saved[$key]) || !is_array($saved[$key])) {
                $saved[$key] = $defaults[$key];
            }
        }
        if ($saved['brands'] === []) {
            $saved['brands'] = $defaults['brands'];
        }
        $saved['schema_version'] = 2;
        return $saved;
    }

    // -------------------------------------------------------------------------
    // Marken (Brands)
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function getBrands(): array
    {
        return array_values($this->state['brands'] ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBrand(string $brandId): ?array
    {
        foreach ($this->state['brands'] ?? [] as $brand) {
            if (($brand['id'] ?? '') === $brandId) {
                return $brand;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function saveBrand(array $input): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '') {
            $id = 'brand_' . bin2hex(random_bytes(4));
        }

        $name = trim((string) ($input['name'] ?? 'Neue Marke'));
        $slug = trim((string) ($input['slug'] ?? ''));
        if ($slug === '') {
            $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $name));
            $slug = trim($slug, '-') ?: $id;
        }

        $record = [
            'id'               => $id,
            'slug'             => $slug,
            'name'             => $name !== '' ? $name : 'Unbenannte Marke',
            'color'            => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($input['color'] ?? '')) ? (string) $input['color'] : '#586EE7',
            'description'      => trim((string) ($input['description'] ?? '')),
            'require_approval' => !empty($input['require_approval']),
            'allowed_user_ids' => array_values(array_map('intval', (array) ($input['allowed_user_ids'] ?? []))),
            'default_utm'      => is_array($input['default_utm'] ?? null) ? $input['default_utm'] : ['utm_source' => '{platform}', 'utm_medium' => 'social'],
            'updated_at'       => $now,
        ];

        foreach ($this->state['brands'] as $idx => $existing) {
            if (($existing['id'] ?? '') === $id) {
                $record['created_at'] = $existing['created_at'] ?? $now;
                $this->state['brands'][$idx] = $record;
                $this->persist();
                return $record;
            }
        }

        $record['created_at'] = $now;
        $this->state['brands'][] = $record;
        $this->persist();
        return $record;
    }

    public function deleteBrand(string $brandId): bool
    {
        if (count($this->state['brands']) <= 1) {
            return false;
        }
        $before = count($this->state['brands']);
        $this->state['brands'] = array_values(array_filter(
            $this->state['brands'],
            static fn(array $b): bool => ($b['id'] ?? '') !== $brandId
        ));
        if (count($this->state['brands']) === $before) {
            return false;
        }
        $fallbackBrandId = (string) ($this->state['brands'][0]['id'] ?? 'default');
        foreach ($this->state['accounts'] as $idx => $acc) {
            if (($acc['brand_id'] ?? '') === $brandId) {
                $this->state['accounts'][$idx]['brand_id'] = $fallbackBrandId;
            }
        }
        foreach ($this->state['post_groups'] as $idx => $pg) {
            if (($pg['brand_id'] ?? '') === $brandId) {
                $this->state['post_groups'][$idx]['brand_id'] = $fallbackBrandId;
            }
        }
        $this->persist();
        return true;
    }

    // -------------------------------------------------------------------------
    // Kanäle & Konten (Accounts)
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function getAccounts(?string $brandId = null): array
    {
        $accounts = array_values($this->state['accounts'] ?? []);
        if ($brandId !== null && $brandId !== '' && $brandId !== 'all') {
            $accounts = array_values(array_filter(
                $accounts,
                static fn(array $a): bool => ($a['brand_id'] ?? '') === $brandId
            ));
        }
        return $accounts;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAccount(string $accountId): ?array
    {
        foreach ($this->state['accounts'] ?? [] as $account) {
            if (($account['id'] ?? '') === $accountId) {
                return $account;
            }
        }
        return null;
    }

    /**
     * Legt ein neues Konto an oder aktualisiert ein bestehendes.
     * Übergebene Klartext-Zugangsdaten (`access_token`, `refresh_token`, `bot_token`, `instance_url`, `chat_id`)
     * werden unmittelbar über `TokenSafe` verschlüsselt und niemals im Klartext gespeichert.
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function saveAccount(array $input): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $id = trim((string) ($input['id'] ?? ''));
        $existing = $id !== '' ? $this->findAccount($id) : null;
        if ($id === '') {
            $id = 'acc_' . bin2hex(random_bytes(5));
        }

        $platform = strtolower(trim((string) ($input['platform'] ?? ($existing['platform'] ?? 'instagram'))));
        $isManualPlatform = in_array($platform, ['whatsapp', 'signal'], true);

        $defaultAccountType = match ($platform) {
            'instagram', 'pinterest' => 'business',
            'facebook'               => 'page',
            'linkedin'               => 'person',
            'threads'                => 'profile',
            'tiktok'                 => 'creator',
            'bluesky'                => 'atproto',
            'mastodon'               => 'instance',
            'telegram', 'whatsapp'   => 'channel',
            default                  => 'group',
        };

        $accountType = trim((string) ($input['account_type'] ?? ($existing['account_type'] ?? $defaultAccountType)));
        $defaultBrandId = (string) ($this->state['brands'][0]['id'] ?? 'default');
        $brandId = trim((string) ($input['brand_id'] ?? ($existing['brand_id'] ?? $defaultBrandId)));
        if ($this->findBrand($brandId) === null) {
            $brandId = $defaultBrandId;
        }

        // Bestehende Geheimnisse laden und mit neu eingegebenen aktualisieren
        $existingEncrypted = (string) ($existing['encrypted_secrets'] ?? '');
        $secrets = $existingEncrypted !== '' ? $this->tokenSafe->decryptSecrets($existingEncrypted) : [];

        $newToken = trim((string) ($input['access_token'] ?? ''));
        $newRefresh = trim((string) ($input['refresh_token'] ?? ''));
        $newIdentifier = trim((string) ($input['external_account_id'] ?? ($existing['external_account_id'] ?? '')));
        $instanceUrl = trim((string) ($input['instance_url'] ?? ($existing['instance_url'] ?? '')));

        if ($newToken !== '') {
            $secrets['access_token'] = $newToken;
        }
        if ($newRefresh !== '') {
            $secrets['refresh_token'] = $newRefresh;
        }
        if ($instanceUrl !== '') {
            $secrets['instance_url'] = $instanceUrl;
        }

        $encryptedSecrets = $secrets !== [] ? $this->tokenSafe->encryptSecrets($secrets) : '';

        // Realistischer Verbindungsstatus: manuell für WhatsApp/Signal, verbunden wenn Token hinterlegt, sonst unverified
        $requestedState = trim((string) ($input['connection_state'] ?? ''));
        if ($isManualPlatform || $requestedState === 'manual') {
            $connectionState = 'manual';
            $authMode = 'manual_handoff';
        } elseif ($requestedState !== '') {
            $connectionState = $requestedState;
            $authMode = (string) ($input['auth_mode'] ?? ($existing['auth_mode'] ?? 'direct_credentials'));
        } elseif (!empty($secrets['access_token'])) {
            $connectionState = $platform === 'tiktok' ? 'handoff_only' : 'connected';
            $authMode = (string) ($input['auth_mode'] ?? 'direct_credentials');
        } else {
            $connectionState = (string) ($existing['connection_state'] ?? 'unverified');
            $authMode = (string) ($input['auth_mode'] ?? 'direct_credentials');
        }

        $defaultScopes = match ($platform) {
            'instagram' => ['instagram_basic', 'instagram_content_publish'],
            'facebook'  => ['pages_manage_posts'],
            'linkedin'  => $accountType === 'organization' ? ['w_organization_social'] : ['w_member_social'],
            'threads'   => ['threads_basic', 'threads_content_publish'],
            'tiktok'    => ['video.upload'],
            'pinterest' => ['boards:read', 'pins:write'],
            'bluesky'   => ['com.atproto.repo.createRecord'],
            'mastodon'  => ['write:statuses', 'write:media'],
            default     => [],
        };

        $grantedScopes = isset($input['granted_scopes']) && is_array($input['granted_scopes'])
            ? array_values(array_map('strval', $input['granted_scopes']))
            : (!empty($secrets['access_token']) ? $defaultScopes : (array) ($existing['granted_scopes'] ?? []));

        $record = [
            'id'                  => $id,
            'brand_id'            => $brandId,
            'platform'            => $platform,
            'account_type'        => $accountType,
            'handle'              => trim((string) ($input['handle'] ?? ($existing['handle'] ?? 'Neuer Kanal'))),
            'external_account_id' => $newIdentifier,
            'instance_url'        => $instanceUrl,
            'auth_mode'           => $authMode,
            'connection_state'    => $connectionState,
            'granted_scopes'      => $grantedScopes,
            'instance_limits'     => $input['instance_limits'] ?? ($existing['instance_limits'] ?? null),
            'encrypted_secrets'   => $encryptedSecrets,
            'token_expires_at'    => $input['token_expires_at'] ?? ($existing['token_expires_at'] ?? null),
            'last_checked_at'     => $now,
            'updated_at'          => $now,
        ];

        foreach ($this->state['accounts'] as $idx => $item) {
            if (($item['id'] ?? '') === $id) {
                $record['created_at'] = $item['created_at'] ?? $now;
                $this->state['accounts'][$idx] = $record;
                $this->persist();
                return $record;
            }
        }

        $record['created_at'] = $now;
        $this->state['accounts'][] = $record;
        $this->persist();
        return $record;
    }

    /**
     * @param array<string, mixed> $updates
     */
    public function updateAccount(string $accountId, array $updates): ?array
    {
        foreach ($this->state['accounts'] as $idx => $account) {
            if (($account['id'] ?? '') === $accountId) {
                $this->state['accounts'][$idx] = array_merge($account, $updates, [
                    'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
                ]);
                $this->persist();
                return $this->state['accounts'][$idx];
            }
        }
        return null;
    }

    public function deleteAccount(string $accountId): bool
    {
        $before = count($this->state['accounts']);
        $this->state['accounts'] = array_values(array_filter(
            $this->state['accounts'],
            static fn(array $a): bool => ($a['id'] ?? '') !== $accountId
        ));
        if (count($this->state['accounts']) === $before) {
            return false;
        }
        // Aus Ziel-Listen offener Beiträge entfernen
        foreach ($this->state['post_groups'] as $idx => $pg) {
            $targets = array_values(array_filter(
                (array) ($pg['targets'] ?? []),
                static fn($tid): bool => (string) $tid !== $accountId
            ));
            $this->state['post_groups'][$idx]['targets'] = $targets;
            if (isset($this->state['post_groups'][$idx]['variants'][$accountId])) {
                unset($this->state['post_groups'][$idx]['variants'][$accountId]);
            }
        }
        $this->persist();
        return true;
    }

    // -------------------------------------------------------------------------
    // Medienbibliothek (Media Assets)
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function getMediaAssets(?string $brandId = null): array
    {
        $media = array_values($this->state['media_assets'] ?? []);
        if ($brandId !== null && $brandId !== '' && $brandId !== 'all') {
            $media = array_values(array_filter(
                $media,
                static fn(array $m): bool => ($m['brand_id'] ?? '') === $brandId
            ));
        }
        return $media;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findMediaAsset(string $mediaId): ?array
    {
        foreach ($this->state['media_assets'] ?? [] as $asset) {
            if (($asset['id'] ?? '') === $mediaId) {
                return $asset;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function saveMediaAsset(array $input): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '') {
            $id = 'med_' . bin2hex(random_bytes(5));
        }

        $defaultBrandId = (string) ($this->state['brands'][0]['id'] ?? 'default');
        $mime = trim((string) ($input['mime_type'] ?? 'image/jpeg'));
        $kind = trim((string) ($input['kind'] ?? ''));
        if ($kind === '') {
            $kind = str_starts_with($mime, 'video/')
                ? 'video'
                : ($mime === 'application/pdf' ? 'document' : 'image');
        }

        $record = [
            'id'               => $id,
            'brand_id'         => trim((string) ($input['brand_id'] ?? $defaultBrandId)) ?: $defaultBrandId,
            'wp_attachment_id' => isset($input['wp_attachment_id']) ? (int) $input['wp_attachment_id'] : null,
            'url'              => trim((string) ($input['url'] ?? '')),
            'thumbnail_url'    => trim((string) ($input['thumbnail_url'] ?? ($input['url'] ?? ''))),
            'name'             => trim((string) ($input['name'] ?? 'Medium')),
            'kind'             => $kind,
            'mime_type'        => $mime,
            'width_px'         => (int) ($input['width_px'] ?? 1080),
            'height_px'        => (int) ($input['height_px'] ?? 1080),
            'duration_sec'     => (int) ($input['duration_sec'] ?? 0),
            'alt_text'         => trim((string) ($input['alt_text'] ?? '')),
            'copyright_notice' => trim((string) ($input['copyright_notice'] ?? '')),
            'renditions'       => is_array($input['renditions'] ?? null) ? $input['renditions'] : [
                '4:5'  => ['width' => 1080, 'height' => 1350, 'ready' => true],
                '1:1'  => ['width' => 1080, 'height' => 1080, 'ready' => true],
                '9:16' => ['width' => 1080, 'height' => 1920, 'ready' => true],
                '2:3'  => ['width' => 1000, 'height' => 1500, 'ready' => true],
                '16:9' => ['width' => 1920, 'height' => 1080, 'ready' => true],
            ],
            'updated_at'       => $now,
        ];

        foreach ($this->state['media_assets'] as $idx => $existing) {
            if (($existing['id'] ?? '') === $id) {
                $record['created_at'] = $existing['created_at'] ?? $now;
                $this->state['media_assets'][$idx] = $record;
                $this->persist();
                return $record;
            }
        }

        $record['created_at'] = $now;
        array_unshift($this->state['media_assets'], $record);
        $this->persist();
        return $record;
    }

    /**
     * @param list<string> $mediaIds
     */
    public function deleteMediaAssets(array $mediaIds): int
    {
        $ids = array_values(array_unique(array_map('strval', $mediaIds)));
        $before = count($this->state['media_assets']);
        $this->state['media_assets'] = array_values(array_filter(
            $this->state['media_assets'],
            static fn(array $m): bool => !in_array((string) ($m['id'] ?? ''), $ids, true)
        ));
        $deleted = $before - count($this->state['media_assets']);
        if ($deleted > 0) {
            $this->persist();
        }
        return $deleted;
    }

    // -------------------------------------------------------------------------
    // Textbausteine & Hashtag-Pakete (Snippets)
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function getSnippets(?string $brandId = null): array
    {
        $snippets = array_values($this->state['snippets'] ?? []);
        if ($brandId !== null && $brandId !== '' && $brandId !== 'all') {
            $snippets = array_values(array_filter(
                $snippets,
                static fn(array $s): bool => ($s['brand_id'] ?? '') === $brandId
            ));
        }
        return $snippets;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function saveSnippet(array $input): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '') {
            $id = 'snip_' . bin2hex(random_bytes(4));
        }
        $defaultBrandId = (string) ($this->state['brands'][0]['id'] ?? 'default');

        $record = [
            'id'           => $id,
            'brand_id'     => trim((string) ($input['brand_id'] ?? $defaultBrandId)) ?: $defaultBrandId,
            'title'        => trim((string) ($input['title'] ?? 'Textbaustein')),
            'snippet_type' => in_array((string) ($input['snippet_type'] ?? 'hashtags'), ['hashtags', 'cta', 'text'], true)
                ? (string) $input['snippet_type']
                : 'hashtags',
            'content'      => trim((string) ($input['content'] ?? '')),
            'updated_at'   => $now,
        ];

        foreach ($this->state['snippets'] as $idx => $existing) {
            if (($existing['id'] ?? '') === $id) {
                $record['created_at'] = $existing['created_at'] ?? $now;
                $this->state['snippets'][$idx] = $record;
                $this->persist();
                return $record;
            }
        }

        $record['created_at'] = $now;
        array_unshift($this->state['snippets'], $record);
        $this->persist();
        return $record;
    }

    public function deleteSnippet(string $snippetId): bool
    {
        $before = count($this->state['snippets']);
        $this->state['snippets'] = array_values(array_filter(
            $this->state['snippets'],
            static fn(array $s): bool => ($s['id'] ?? '') !== $snippetId
        ));
        if (count($this->state['snippets']) < $before) {
            $this->persist();
            return true;
        }
        return false;
    }

    // -------------------------------------------------------------------------
    // Beitragsverbünde (PostGroups) – inkl. Bulk Move, Duplicate & Delete
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function getPostGroups(?string $brandId = null): array
    {
        $groups = array_values($this->state['post_groups'] ?? []);
        if ($brandId !== null && $brandId !== '' && $brandId !== 'all') {
            $groups = array_values(array_filter(
                $groups,
                static fn(array $g): bool => ($g['brand_id'] ?? '') === $brandId
            ));
        }
        return $groups;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPostGroup(string $postGroupId): ?array
    {
        foreach ($this->state['post_groups'] ?? [] as $group) {
            if (($group['id'] ?? '') === $postGroupId) {
                return $group;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $postGroup
     * @return array<string, mixed>
     */
    public function savePostGroup(array $postGroup): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $defaultBrandId = (string) ($this->state['brands'][0]['id'] ?? 'default');

        if (empty($postGroup['id'])) {
            $postGroup['id'] = 'pg_' . bin2hex(random_bytes(5));
            $postGroup['created_at'] = $now;
        }
        if (empty($postGroup['brand_id'])) {
            $postGroup['brand_id'] = $defaultBrandId;
        }
        if (trim((string) ($postGroup['title'] ?? '')) === '') {
            $firstLine = strtok(trim((string) ($postGroup['master_text'] ?? '')), "\r\n") ?: 'Neuer Beitrag';
            $postGroup['title'] = mb_substr($firstLine, 0, 72);
        }
        if (!isset($postGroup['archived'])) {
            $postGroup['archived'] = false;
        }
        if (!isset($postGroup['campaign'])) {
            $postGroup['campaign'] = 'Allgemein';
        }
        if (!isset($postGroup['timezone'])) {
            $postGroup['timezone'] = 'Europe/Berlin';
        }
        $postGroup['updated_at'] = $now;

        foreach ($this->state['post_groups'] as $idx => $existing) {
            if (($existing['id'] ?? '') === $postGroup['id']) {
                $postGroup['created_at'] = $existing['created_at'] ?? $now;
                $this->state['post_groups'][$idx] = $postGroup;
                $this->persist();
                return $postGroup;
            }
        }

        array_unshift($this->state['post_groups'], $postGroup);
        $this->persist();
        return $postGroup;
    }

    /**
     * Verschiebt einen oder mehrere Beiträge (Ordner/Kampagne, Status/Archiv, Termin oder Marke).
     *
     * @param list<string>         $postGroupIds
     * @param array<string, mixed> $moveSpec
     */
    public function bulkMovePostGroups(array $postGroupIds, array $moveSpec): int
    {
        $ids = array_values(array_unique(array_map('strval', $postGroupIds)));
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $movedCount = 0;

        foreach ($this->state['post_groups'] as $idx => $group) {
            if (!in_array((string) ($group['id'] ?? ''), $ids, true)) {
                continue;
            }
            if (isset($moveSpec['campaign']) && trim((string) $moveSpec['campaign']) !== '') {
                $this->state['post_groups'][$idx]['campaign'] = trim((string) $moveSpec['campaign']);
            }
            if (isset($moveSpec['brand_id']) && trim((string) $moveSpec['brand_id']) !== '' && $this->findBrand((string) $moveSpec['brand_id']) !== null) {
                $this->state['post_groups'][$idx]['brand_id'] = trim((string) $moveSpec['brand_id']);
            }
            if (array_key_exists('scheduled_at', $moveSpec) && $moveSpec['scheduled_at'] !== '__keep__') {
                $this->state['post_groups'][$idx]['scheduled_at'] = $moveSpec['scheduled_at'] ? (string) $moveSpec['scheduled_at'] : null;
            }
            if (isset($moveSpec['workflow_target']) && $moveSpec['workflow_target'] !== '__keep__') {
                $target = (string) $moveSpec['workflow_target'];
                if ($target === 'archived') {
                    $this->state['post_groups'][$idx]['archived'] = true;
                } elseif ($target === 'draft') {
                    $this->state['post_groups'][$idx]['archived'] = false;
                    $this->state['post_groups'][$idx]['approval_status'] = 'none';
                } elseif ($target === 'review') {
                    $this->state['post_groups'][$idx]['archived'] = false;
                    $this->state['post_groups'][$idx]['approval_status'] = 'pending';
                } elseif ($target === 'approved') {
                    $this->state['post_groups'][$idx]['archived'] = false;
                    $this->state['post_groups'][$idx]['approval_status'] = 'approved';
                }
            }
            $this->state['post_groups'][$idx]['updated_at'] = $now;
            $movedCount++;
        }

        if ($movedCount > 0) {
            $this->persist();
            $this->recordAudit(
                'post_groups.bulk_moved',
                sprintf('%d Beitrag/Beiträge verschoben', $movedCount),
                json_encode($moveSpec, JSON_UNESCAPED_UNICODE) ?: ''
            );
        }

        return $movedCount;
    }

    /**
     * Dupliziert einen oder mehrere Beiträge als saubere neue Entwürfe (ohne alte Versandstatus).
     *
     * @param list<string> $postGroupIds
     * @return list<array<string, mixed>>
     */
    public function duplicatePostGroups(array $postGroupIds): array
    {
        $ids = array_values(array_unique(array_map('strval', $postGroupIds)));
        $created = [];
        $now = gmdate('Y-m-d\TH:i:s\Z');

        foreach ($ids as $id) {
            $orig = $this->findPostGroup($id);
            if ($orig === null) {
                continue;
            }
            $copy = json_decode(json_encode($orig) ?: '{}', true);
            $copy['id'] = 'pg_' . bin2hex(random_bytes(5));
            $copy['title'] = (string) ($orig['title'] ?? 'Beitrag') . ' (Kopie)';
            $copy['approval_status'] = 'none';
            $copy['approved_by'] = null;
            $copy['approved_at'] = null;
            $copy['archived'] = false;
            $copy['scheduled_at'] = null;
            $copy['current_revision_id'] = null;
            $copy['created_at'] = $now;
            $copy['updated_at'] = $now;

            foreach ((array) ($copy['variants'] ?? []) as $accId => $v) {
                $copy['variants'][$accId]['delivery_status'] = 'pending';
                $copy['variants'][$accId]['remote_post_id'] = null;
                $copy['variants'][$accId]['remote_url'] = null;
                $copy['variants'][$accId]['completed_at'] = null;
                $copy['variants'][$accId]['last_error'] = null;
            }

            array_unshift($this->state['post_groups'], $copy);
            $created[] = $copy;
        }

        if ($created !== []) {
            $this->persist();
            $this->recordAudit(
                'post_groups.duplicated',
                sprintf('%d Beitrag/Beiträge dupliziert', count($created))
            );
        }

        return $created;
    }

    /**
     * Löscht einen oder mehrere Beiträge endgültig samt zugehöriger Jobs.
     *
     * @param list<string> $postGroupIds
     */
    public function deletePostGroups(array $postGroupIds): int
    {
        $ids = array_values(array_unique(array_map('strval', $postGroupIds)));
        $before = count($this->state['post_groups']);
        $this->state['post_groups'] = array_values(array_filter(
            $this->state['post_groups'],
            static fn(array $g): bool => !in_array((string) ($g['id'] ?? ''), $ids, true)
        ));
        $deleted = $before - count($this->state['post_groups']);

        if ($deleted > 0) {
            $this->state['jobs'] = array_values(array_filter(
                $this->state['jobs'] ?? [],
                static fn(array $j): bool => !in_array((string) ($j['post_group_id'] ?? ''), $ids, true)
            ));
            $this->persist();
            $this->recordAudit(
                'post_groups.deleted',
                sprintf('%d Beitrag/Beiträge endgültig gelöscht', $deleted),
                implode(', ', $ids)
            );
        }

        return $deleted;
    }

    // -------------------------------------------------------------------------
    // Revisionen, Jobs, Nachweise, Automationen & Audit
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $revision
     * @return array<string, mixed>
     */
    public function addRevision(array $revision): array
    {
        $this->state['revisions'][] = $revision;
        $this->persist();
        return $revision;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getRevisions(?string $postGroupId = null): array
    {
        $revisions = array_values($this->state['revisions'] ?? []);
        if ($postGroupId !== null && $postGroupId !== '') {
            $revisions = array_values(array_filter(
                $revisions,
                static fn(array $r): bool => ($r['post_group_id'] ?? '') === $postGroupId
            ));
        }
        return $revisions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getJobs(?string $postGroupId = null): array
    {
        $jobs = array_values($this->state['jobs'] ?? []);
        if ($postGroupId !== null && $postGroupId !== '') {
            $jobs = array_values(array_filter(
                $jobs,
                static fn(array $j): bool => ($j['post_group_id'] ?? '') === $postGroupId
            ));
        }
        return $jobs;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findJobByIdempotencyKey(string $idempotencyKey): ?array
    {
        foreach ($this->state['jobs'] ?? [] as $job) {
            if (($job['idempotency_key'] ?? '') === $idempotencyKey) {
                return $job;
            }
        }
        return null;
    }

    /**
     * @param array<string, mixed> $job
     * @return array<string, mixed>
     */
    public function saveJob(array $job): array
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');
        if (empty($job['id'])) {
            $job['id'] = 'job_' . bin2hex(random_bytes(5));
            $job['created_at'] = $now;
        }
        $job['updated_at'] = $now;

        foreach ($this->state['jobs'] as $idx => $existing) {
            if (($existing['id'] ?? '') === $job['id']) {
                $this->state['jobs'][$idx] = $job;
                $this->persist();
                return $job;
            }
        }

        $this->state['jobs'][] = $job;
        $this->persist();
        return $job;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getReceipts(?string $postGroupId = null): array
    {
        $receipts = array_values($this->state['receipts'] ?? []);
        if ($postGroupId !== null && $postGroupId !== '') {
            $receipts = array_values(array_filter(
                $receipts,
                static fn(array $r): bool => ($r['post_group_id'] ?? '') === $postGroupId
            ));
        }
        return $receipts;
    }

    /**
     * @param array<string, mixed> $receipt
     * @return array<string, mixed>
     */
    public function saveReceipt(array $receipt): array
    {
        if (empty($receipt['id'])) {
            $receipt['id'] = 'rec_' . bin2hex(random_bytes(5));
        }
        foreach ($this->state['receipts'] as $idx => $existing) {
            if (($existing['id'] ?? '') === $receipt['id']) {
                $this->state['receipts'][$idx] = $receipt;
                $this->persist();
                return $receipt;
            }
        }
        array_unshift($this->state['receipts'], $receipt);
        $this->persist();
        return $receipt;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAutomationRules(): array
    {
        return array_values($this->state['automation_rules'] ?? []);
    }

    public function toggleAutomationRule(string $ruleId, bool $enabled): array
    {
        foreach ($this->state['automation_rules'] as $idx => $rule) {
            if (($rule['id'] ?? '') === $ruleId) {
                $this->state['automation_rules'][$idx]['enabled'] = $enabled;
                $this->persist();
                break;
            }
        }
        return $this->getAutomationRules();
    }

    public function recordAudit(
        string $eventType,
        string $title,
        string $detail = '',
        ?string $brandId = null,
        ?string $postGroupId = null,
        ?string $accountId = null,
        int $actorId = 1
    ): array {
        $entry = [
            'id'            => 'aud_' . bin2hex(random_bytes(5)),
            'brand_id'      => $brandId,
            'post_group_id' => $postGroupId,
            'account_id'    => $accountId,
            'event_type'    => $eventType,
            'title'         => $title,
            'detail'        => $detail,
            'actor_id'      => $actorId,
            'created_at'    => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        array_unshift($this->state['audit_events'], $entry);
        $this->state['audit_events'] = array_slice($this->state['audit_events'], 0, 500);
        $this->persist();
        return $entry;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getAuditEvents(int $limit = 100): array
    {
        return array_slice(array_values($this->state['audit_events'] ?? []), 0, $limit);
    }
}
