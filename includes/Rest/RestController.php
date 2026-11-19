<?php
/**
 * WordPress REST-API-Controller (`/wp-json/social-hub/v1/...`) v2.0.0.
 *
 * Vollständige CRUD-, Mehrfachauswahl-, Verschiebe-, Duplizier-, Lösch-, Versand-,
 * Nachweis-, Medien-, Kanal-Test- und Komplett-Export-Endpunkte.
 *
 * @package SocialMediaHub\Rest
 */

declare(strict_types=1);

namespace SocialMediaHub\Rest;

use SocialMediaHub\Core\EventBus;
use SocialMediaHub\Domain\PreflightService;
use SocialMediaHub\Domain\PublicationService;
use SocialMediaHub\Infrastructure\Database\Repositories;
use SocialMediaHub\Infrastructure\Export\BackupExporter;
use SocialMediaHub\Infrastructure\Security\Capabilities;
use SocialMediaHub\Infrastructure\Security\SignedMediaEndpoint;
use SocialMediaHub\Infrastructure\Security\TokenSafe;
use SocialMediaHub\Providers\ProviderRegistry;
use Throwable;

class RestController
{
    public const NAMESPACE = 'social-hub/v1';

    private Repositories $repositories;
    private ProviderRegistry $providerRegistry;
    private PreflightService $preflightService;
    private PublicationService $publicationService;
    private BackupExporter $backupExporter;
    private SignedMediaEndpoint $signedMediaEndpoint;
    private TokenSafe $tokenSafe;
    private EventBus $eventBus;

    public function __construct(
        Repositories $repositories,
        ProviderRegistry $providerRegistry,
        PreflightService $preflightService,
        PublicationService $publicationService,
        BackupExporter $backupExporter,
        SignedMediaEndpoint $signedMediaEndpoint,
        TokenSafe $tokenSafe,
        EventBus $eventBus
    ) {
        $this->repositories = $repositories;
        $this->providerRegistry = $providerRegistry;
        $this->preflightService = $preflightService;
        $this->publicationService = $publicationService;
        $this->backupExporter = $backupExporter;
        $this->signedMediaEndpoint = $signedMediaEndpoint;
        $this->tokenSafe = $tokenSafe;
        $this->eventBus = $eventBus;
    }

    public function registerRoutes(): void
    {
        if (!function_exists('register_rest_route')) {
            return;
        }

        $canRead = static fn(): bool => Capabilities::userCan(Capabilities::CLIENT_REVIEW) || Capabilities::userCan(Capabilities::EDIT_POSTS);
        $canEdit = static fn(): bool => Capabilities::userCan(Capabilities::EDIT_POSTS);
        $canApprove = static fn(): bool => Capabilities::userCan(Capabilities::APPROVE_POSTS) || Capabilities::userCan(Capabilities::EDIT_POSTS);
        $canManage = static fn(): bool => Capabilities::userCan(Capabilities::MANAGE_ALL);

        // Zustand abrufen
        register_rest_route(self::NAMESPACE, '/state', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGetState'],
            'permission_callback' => $canRead,
        ]);

        // Marken (Brands)
        register_rest_route(self::NAMESPACE, '/brands', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSaveBrand'],
            'permission_callback' => $canManage,
        ]);
        register_rest_route(self::NAMESPACE, '/brands/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleDeleteBrand'],
            'permission_callback' => $canManage,
        ]);

        // Kanäle & Konten (Accounts)
        register_rest_route(self::NAMESPACE, '/accounts', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSaveAccount'],
            'permission_callback' => $canManage,
        ]);
        register_rest_route(self::NAMESPACE, '/accounts/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleDeleteAccount'],
            'permission_callback' => $canManage,
        ]);
        register_rest_route(self::NAMESPACE, '/accounts/(?P<id>[a-zA-Z0-9_-]+)/test', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleTestAccount'],
            'permission_callback' => $canEdit,
        ]);

        // Medienbibliothek
        register_rest_route(self::NAMESPACE, '/media', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSaveMedia'],
            'permission_callback' => $canEdit,
        ]);
        register_rest_route(self::NAMESPACE, '/media/delete', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleDeleteMedia'],
            'permission_callback' => $canEdit,
        ]);

        // Textbausteine & Hashtags (Snippets)
        register_rest_route(self::NAMESPACE, '/snippets', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSaveSnippet'],
            'permission_callback' => $canEdit,
        ]);
        register_rest_route(self::NAMESPACE, '/snippets/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'handleDeleteSnippet'],
            'permission_callback' => $canEdit,
        ]);

        // Beiträge (PostGroups) – Einzel- & Mehrfachaktionen (Verschieben, Duplizieren, Löschen)
        register_rest_route(self::NAMESPACE, '/post-groups', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSavePostGroup'],
            'permission_callback' => $canEdit,
        ]);
        register_rest_route(self::NAMESPACE, '/post-groups/bulk', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleBulkPostGroups'],
            'permission_callback' => $canEdit,
        ]);
        register_rest_route(self::NAMESPACE, '/post-groups/(?P<id>[a-zA-Z0-9_-]+)/variants/(?P<account_id>[a-zA-Z0-9_-]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleUpdateVariant'],
            'permission_callback' => $canEdit,
        ]);
        register_rest_route(self::NAMESPACE, '/post-groups/(?P<id>[a-zA-Z0-9_-]+)/approve', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleApprovePostGroup'],
            'permission_callback' => $canApprove,
        ]);
        register_rest_route(self::NAMESPACE, '/post-groups/(?P<id>[a-zA-Z0-9_-]+)/dispatch', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleDispatchPostGroup'],
            'permission_callback' => $canApprove,
        ]);
        register_rest_route(self::NAMESPACE, '/post-groups/(?P<id>[a-zA-Z0-9_-]+)/manual-receipt', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleManualReceipt'],
            'permission_callback' => $canEdit,
        ]);

        // Automationsregeln
        register_rest_route(self::NAMESPACE, '/automations', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleToggleAutomation'],
            'permission_callback' => $canManage,
        ]);

        // Export, Import & Komplett-Reset
        register_rest_route(self::NAMESPACE, '/export', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleExport'],
            'permission_callback' => $canEdit,
        ]);
        register_rest_route(self::NAMESPACE, '/import', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleImport'],
            'permission_callback' => $canManage,
        ]);
        register_rest_route(self::NAMESPACE, '/reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleResetWorkspace'],
            'permission_callback' => $canManage,
        ]);

        // Signierter Medien-Abhol-Endpunkt (Pull-API für Instagram/Meta)
        register_rest_route(self::NAMESPACE, '/media-pull', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleSignedMediaPull'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Erzeugt den kompletten, um Geheimnisse bereinigten Client-Snapshot inkl. berechneter Prüfwerte.
     *
     * @return array<string, mixed>
     */
    public function buildSanitizedClientState(?string $brandFilter = null): array
    {
        $accounts = [];
        foreach ($this->repositories->getAccounts($brandFilter) as $account) {
            $platform = (string) ($account['platform'] ?? '');
            $provider = $this->providerRegistry->get($platform);
            $encrypted = (string) ($account['encrypted_secrets'] ?? '');
            $decrypted = $encrypted !== '' ? $this->tokenSafe->decryptSecrets($encrypted) : [];

            unset($account['encrypted_secrets']);
            $account['security_redaction'] = $this->tokenSafe->redactForDisplay($decrypted);
            $account['capability_diagnosis'] = $provider !== null
                ? $provider->evaluateAccountCapabilities($account)
                : ['status' => 'unknown', 'delivery_mode' => 'unknown', 'reason' => 'Unbekannter Provider'];
            $accounts[] = $account;
        }

        $postGroups = [];
        foreach ($this->repositories->getPostGroups($brandFilter) as $group) {
            $normalized = $this->preflightService->normalizePostGroupVariants($group);
            $normalized['summary'] = $this->preflightService->evaluatePostGroupSummary($normalized);
            $postGroups[] = $normalized;
        }

        return [
            'version'              => SMH_VERSION,
            'manifests'            => $this->providerRegistry->getAllManifests(),
            'brands'               => $this->repositories->getBrands(),
            'accounts'             => $accounts,
            'media_assets'         => $this->repositories->getMediaAssets($brandFilter),
            'snippets'             => $this->repositories->getSnippets($brandFilter),
            'post_groups'          => $postGroups,
            'revisions'            => $this->repositories->getRevisions(),
            'jobs'                 => $this->repositories->getJobs(),
            'receipts'             => $this->repositories->getReceipts(),
            'automation_rules'     => $this->repositories->getAutomationRules(),
            'preflight_48h_alerts' => $this->preflightService->run48HourConnectionPreflight(),
            'audit_events'         => $this->repositories->getAuditEvents(60),
            'wartungsmodus_active' => function_exists('get_option') ? (bool) get_option('jdb_wartungsmodus_enabled', false) : false,
        ];
    }

    /**
     * @param mixed $request
     * @return array<string, mixed>
     */
    private function extractJson($request): array
    {
        if (is_object($request) && method_exists($request, 'get_json_params')) {
            $json = $request->get_json_params();
            if (is_array($json)) {
                return $json;
            }
        }
        return is_array($request) ? $request : [];
    }

    /**
     * @param mixed $request
     */
    private function extractParam($request, string $key, string $default = ''): string
    {
        if (is_object($request) && method_exists($request, 'get_param')) {
            $val = $request->get_param($key);
            return $val !== null ? (string) $val : $default;
        }
        return $default;
    }

    public function handleGetState($request): array
    {
        $brand = $this->extractParam($request, 'brand_id', 'all');
        return $this->buildSanitizedClientState($brand);
    }

    public function handleSaveBrand($request): array
    {
        $saved = $this->repositories->saveBrand($this->extractJson($request));
        $this->repositories->recordAudit('brand.saved', 'Marke gespeichert', (string) $saved['name'], (string) $saved['id']);
        return ['ok' => true, 'brand' => $saved, 'state' => $this->buildSanitizedClientState()];
    }

    public function handleDeleteBrand($request): array
    {
        $id = $this->extractParam($request, 'id');
        $ok = $this->repositories->deleteBrand($id);
        return [
            'ok'    => $ok,
            'error' => $ok ? null : 'Die letzte verbleibende Marke kann nicht gelöscht werden.',
            'state' => $this->buildSanitizedClientState(),
        ];
    }

    public function handleSaveAccount($request): array
    {
        $saved = $this->repositories->saveAccount($this->extractJson($request));
        $this->repositories->recordAudit('account.saved', 'Kanal gespeichert', (string) ($saved['handle'] ?? ''), (string) ($saved['brand_id'] ?? ''), null, (string) $saved['id']);
        return ['ok' => true, 'account_id' => $saved['id'], 'state' => $this->buildSanitizedClientState()];
    }

    public function handleDeleteAccount($request): array
    {
        $id = $this->extractParam($request, 'id');
        $ok = $this->repositories->deleteAccount($id);
        $this->repositories->recordAudit('account.deleted', 'Kanal entfernt', $id);
        return ['ok' => $ok, 'state' => $this->buildSanitizedClientState()];
    }

    public function handleTestAccount($request): array
    {
        $id = $this->extractParam($request, 'id');
        $account = $this->repositories->findAccount($id);
        if ($account === null) {
            return ['ok' => false, 'error' => 'Kanal nicht gefunden.'];
        }
        $provider = $this->providerRegistry->get((string) ($account['platform'] ?? ''));
        $cap = $provider !== null ? $provider->evaluateAccountCapabilities($account) : ['status' => 'unknown', 'reason' => 'Provider fehlt'];
        $this->repositories->updateAccount($id, ['last_checked_at' => gmdate('Y-m-d\TH:i:s\Z')]);
        return ['ok' => true, 'diagnosis' => $cap, 'state' => $this->buildSanitizedClientState()];
    }

    public function handleSaveMedia($request): array
    {
        $saved = $this->repositories->saveMediaAsset($this->extractJson($request));
        $this->repositories->recordAudit('media.saved', 'Medium hinzugefügt', (string) ($saved['name'] ?? ''), (string) ($saved['brand_id'] ?? ''));
        return ['ok' => true, 'media' => $saved, 'state' => $this->buildSanitizedClientState()];
    }

    public function handleDeleteMedia($request): array
    {
        $params = $this->extractJson($request);
        $ids = array_values(array_map('strval', (array) ($params['ids'] ?? [])));
        $deleted = $this->repositories->deleteMediaAssets($ids);
        return ['ok' => true, 'deleted' => $deleted, 'state' => $this->buildSanitizedClientState()];
    }

    public function handleSaveSnippet($request): array
    {
        $saved = $this->repositories->saveSnippet($this->extractJson($request));
        return ['ok' => true, 'snippet' => $saved, 'state' => $this->buildSanitizedClientState()];
    }

    public function handleDeleteSnippet($request): array
    {
        $id = $this->extractParam($request, 'id');
        $ok = $this->repositories->deleteSnippet($id);
        return ['ok' => $ok, 'state' => $this->buildSanitizedClientState()];
    }

    public function handleSavePostGroup($request): array
    {
        $params = $this->extractJson($request);
        $saved = $this->repositories->savePostGroup($params);
        $saved = $this->preflightService->normalizePostGroupVariants($saved);
        $this->repositories->savePostGroup($saved);

        $this->repositories->recordAudit(
            'post_group.saved',
            'Beitrag gespeichert',
            (string) ($saved['title'] ?? ''),
            (string) ($saved['brand_id'] ?? ''),
            (string) ($saved['id'] ?? '')
        );

        return [
            'ok'         => true,
            'post_group' => $saved,
            'summary'    => $this->preflightService->evaluatePostGroupSummary($saved),
            'state'      => $this->buildSanitizedClientState(),
        ];
    }

    public function handleBulkPostGroups($request): array
    {
        $params = $this->extractJson($request);
        $action = (string) ($params['action'] ?? '');
        $ids = array_values(array_map('strval', (array) ($params['ids'] ?? [])));

        if ($ids === []) {
            return ['ok' => false, 'error' => 'Keine Beiträge ausgewählt.'];
        }

        if ($action === 'delete') {
            $deleted = $this->repositories->deletePostGroups($ids);
            return ['ok' => true, 'deleted' => $deleted, 'state' => $this->buildSanitizedClientState()];
        }

        if ($action === 'duplicate') {
            $created = $this->repositories->duplicatePostGroups($ids);
            return ['ok' => true, 'duplicated' => count($created), 'state' => $this->buildSanitizedClientState()];
        }

        if ($action === 'move') {
            $moveSpec = is_array($params['move'] ?? null) ? $params['move'] : [];
            $moved = $this->repositories->bulkMovePostGroups($ids, $moveSpec);
            return ['ok' => true, 'moved' => $moved, 'state' => $this->buildSanitizedClientState()];
        }

        return ['ok' => false, 'error' => 'Unbekannte Mehrfachaktion.'];
    }

    public function handleUpdateVariant($request): array
    {
        $id = $this->extractParam($request, 'id');
        $accountId = $this->extractParam($request, 'account_id');
        $changes = $this->extractJson($request);

        $updated = $this->publicationService->updateTargetVariant($id, $accountId, $changes);
        return [
            'ok'         => true,
            'post_group' => $updated,
            'summary'    => $this->preflightService->evaluatePostGroupSummary($updated),
            'state'      => $this->buildSanitizedClientState(),
        ];
    }

    public function handleApprovePostGroup($request): array
    {
        $id = $this->extractParam($request, 'id');
        $approved = $this->publicationService->approvePostGroup($id);
        return [
            'ok'         => true,
            'post_group' => $approved,
            'summary'    => $this->preflightService->evaluatePostGroupSummary($approved),
            'state'      => $this->buildSanitizedClientState(),
        ];
    }

    public function handleDispatchPostGroup($request): array
    {
        $id = $this->extractParam($request, 'id');
        $params = $this->extractJson($request);
        $mode = (string) ($params['mode'] ?? 'publish_now');
        $onlyAccounts = isset($params['only_account_ids']) && is_array($params['only_account_ids'])
            ? array_values(array_map('strval', $params['only_account_ids']))
            : null;

        try {
            $result = $this->publicationService->dispatchPostGroup($id, $mode, $onlyAccounts);
            return array_merge(['ok' => true, 'state' => $this->buildSanitizedClientState()], $result);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function handleManualReceipt($request): array
    {
        $id = $this->extractParam($request, 'id');
        $params = $this->extractJson($request);
        $accountId = (string) ($params['account_id'] ?? '');
        $action = (string) ($params['action'] ?? 'confirm');

        try {
            if ($action === 'revoke') {
                $group = $this->publicationService->revokeManualConfirmation($id, $accountId);
                return [
                    'ok'         => true,
                    'post_group' => $group,
                    'summary'    => $this->preflightService->evaluatePostGroupSummary($group),
                    'state'      => $this->buildSanitizedClientState(),
                ];
            }

            $receipt = $this->publicationService->confirmManualPublication(
                $id,
                $accountId,
                (string) ($params['external_url'] ?? ''),
                (string) ($params['notes'] ?? '')
            );
            $group = $this->repositories->findPostGroup($id) ?? [];

            return [
                'ok'      => true,
                'receipt' => $receipt,
                'summary' => $this->preflightService->evaluatePostGroupSummary($group),
                'state'   => $this->buildSanitizedClientState(),
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function handleToggleAutomation($request): array
    {
        $params = $this->extractJson($request);
        $ruleId = (string) ($params['id'] ?? '');
        $enabled = !empty($params['enabled']);
        $rules = $this->repositories->toggleAutomationRule($ruleId, $enabled);
        return ['ok' => true, 'automation_rules' => $rules, 'state' => $this->buildSanitizedClientState()];
    }

    public function handleExport($request): array
    {
        $format = $this->extractParam($request, 'format', 'json');
        $brand = $this->extractParam($request, 'brand_id', 'all');

        if ($format === 'csv') {
            return [
                'ok'       => true,
                'format'   => 'csv',
                'filename' => 'social-media-hub-archiv-' . gmdate('Y-m-d') . '.csv',
                'content'  => $this->backupExporter->createCsvArchiveReport($brand),
            ];
        }

        return [
            'ok'       => true,
            'format'   => 'json',
            'filename' => 'social-media-hub-komplett-backup-' . gmdate('Y-m-d') . '.json',
            'payload'  => $this->backupExporter->createFullPortableExport($brand),
        ];
    }

    public function handleImport($request): array
    {
        try {
            $stats = $this->backupExporter->validateAndImport($this->extractJson($request));
            return array_merge(['ok' => true, 'state' => $this->buildSanitizedClientState()], $stats);
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    public function handleResetWorkspace(): array
    {
        $this->repositories->resetToEmpty();
        return ['ok' => true, 'state' => $this->buildSanitizedClientState()];
    }

    public function handleSignedMediaPull($request): array
    {
        $assetId = $this->extractParam($request, 'asset_id');
        $rendition = $this->extractParam($request, 'rendition', 'original');
        $expires = (int) $this->extractParam($request, 'expires', '0');
        $sig = $this->extractParam($request, 'sig');

        if (!$this->signedMediaEndpoint->verifySignedPullRequest($assetId, $rendition, $expires, $sig)) {
            return [
                'ok'        => false,
                'http_code' => 403,
                'error'     => 'Signierter Medien-Abhol-Link ist abgelaufen oder ungültig.',
            ];
        }

        $asset = $this->repositories->findMediaAsset($assetId);
        if ($asset === null) {
            return [
                'ok'        => false,
                'http_code' => 404,
                'error'     => 'Medium nicht gefunden.',
            ];
        }

        return [
            'ok'        => true,
            'http_code' => 200,
            'asset_id'  => $assetId,
            'rendition' => $rendition,
            'mime_type' => $asset['mime_type'] ?? 'image/jpeg',
            'name'      => $asset['name'] ?? 'asset.jpg',
            'url'       => $asset['url'] ?? '',
        ];
    }
}
