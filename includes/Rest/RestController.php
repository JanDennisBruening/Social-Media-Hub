<?php
/**
 * WordPress REST-API-Controller (`/wp-json/social-hub/v1/...`).
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

        register_rest_route(self::NAMESPACE, '/state', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleGetState'],
            'permission_callback' => static fn(): bool => Capabilities::userCan(Capabilities::CLIENT_REVIEW) || Capabilities::userCan(Capabilities::EDIT_POSTS),
        ]);

        register_rest_route(self::NAMESPACE, '/post-groups', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleSavePostGroup'],
            'permission_callback' => static fn(): bool => Capabilities::userCan(Capabilities::EDIT_POSTS),
        ]);

        register_rest_route(self::NAMESPACE, '/post-groups/(?P<id>[a-zA-Z0-9_-]+)/variants/(?P<account_id>[a-zA-Z0-9_-]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleUpdateVariant'],
            'permission_callback' => static fn(): bool => Capabilities::userCan(Capabilities::EDIT_POSTS),
        ]);

        register_rest_route(self::NAMESPACE, '/post-groups/(?P<id>[a-zA-Z0-9_-]+)/approve', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleApprovePostGroup'],
            'permission_callback' => static fn(): bool => Capabilities::userCan(Capabilities::APPROVE_POSTS) || Capabilities::userCan(Capabilities::CLIENT_REVIEW),
        ]);

        register_rest_route(self::NAMESPACE, '/post-groups/(?P<id>[a-zA-Z0-9_-]+)/dispatch', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleDispatchPostGroup'],
            'permission_callback' => static fn(): bool => Capabilities::userCan(Capabilities::APPROVE_POSTS) || Capabilities::userCan(Capabilities::EDIT_POSTS),
        ]);

        register_rest_route(self::NAMESPACE, '/post-groups/(?P<id>[a-zA-Z0-9_-]+)/manual-receipt', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleManualReceipt'],
            'permission_callback' => static fn(): bool => Capabilities::userCan(Capabilities::EDIT_POSTS),
        ]);

        register_rest_route(self::NAMESPACE, '/export', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleExport'],
            'permission_callback' => static fn(): bool => Capabilities::userCan(Capabilities::EDIT_POSTS),
        ]);

        register_rest_route(self::NAMESPACE, '/import', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handleImport'],
            'permission_callback' => static fn(): bool => Capabilities::userCan(Capabilities::MANAGE_ALL),
        ]);

        register_rest_route(self::NAMESPACE, '/media-pull', [
            'methods'             => 'GET',
            'callback'            => [$this, 'handleSignedMediaPull'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
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
            'preflight_48h_alerts' => $this->preflightService->run48HourConnectionPreflight(),
            'audit_events'         => $this->repositories->getAuditEvents(60),
        ];
    }

    /**
     * @param mixed $request
     * @return array<string, mixed>
     */
    public function handleGetState($request): array
    {
        $brand = is_object($request) && method_exists($request, 'get_param')
            ? (string) ($request->get_param('brand_id') ?? 'all')
            : 'all';
        return $this->buildSanitizedClientState($brand);
    }

    /**
     * @param mixed $request
     * @return array<string, mixed>
     */
    public function handleSavePostGroup($request): array
    {
        $params = is_object($request) && method_exists($request, 'get_json_params')
            ? (array) $request->get_json_params()
            : (is_array($request) ? $request : []);

        $saved = $this->repositories->savePostGroup($params);
        $saved = $this->preflightService->normalizePostGroupVariants($saved);
        $this->repositories->savePostGroup($saved);

        return [
            'ok'         => true,
            'post_group' => $saved,
            'summary'    => $this->preflightService->evaluatePostGroupSummary($saved),
        ];
    }

    /**
     * @param mixed $request
     * @return array<string, mixed>
     */
    public function handleUpdateVariant($request): array
    {
        $id = is_object($request) && method_exists($request, 'get_param') ? (string) $request->get_param('id') : '';
        $accountId = is_object($request) && method_exists($request, 'get_param') ? (string) $request->get_param('account_id') : '';
        $changes = is_object($request) && method_exists($request, 'get_json_params') ? (array) $request->get_json_params() : [];

        $updated = $this->publicationService->updateTargetVariant($id, $accountId, $changes);
        return [
            'ok'         => true,
            'post_group' => $updated,
            'summary'    => $this->preflightService->evaluatePostGroupSummary($updated),
        ];
    }

    /**
     * @param mixed $request
     * @return array<string, mixed>
     */
    public function handleApprovePostGroup($request): array
    {
        $id = is_object($request) && method_exists($request, 'get_param') ? (string) $request->get_param('id') : '';
        $approved = $this->publicationService->approvePostGroup($id);
        return [
            'ok'         => true,
            'post_group' => $approved,
            'summary'    => $this->preflightService->evaluatePostGroupSummary($approved),
        ];
    }

    /**
     * @param mixed $request
     * @return array<string, mixed>
     */
    public function handleDispatchPostGroup($request): array
    {
        $id = is_object($request) && method_exists($request, 'get_param') ? (string) $request->get_param('id') : '';
        $params = is_object($request) && method_exists($request, 'get_json_params') ? (array) $request->get_json_params() : [];
        $mode = (string) ($params['mode'] ?? 'publish_now');
        $onlyAccounts = isset($params['only_account_ids']) && is_array($params['only_account_ids'])
            ? array_values(array_map('strval', $params['only_account_ids']))
            : null;

        try {
            $result = $this->publicationService->dispatchPostGroup($id, $mode, $onlyAccounts);
            return array_merge(['ok' => true], $result);
        } catch (Throwable $e) {
            return [
                'ok'    => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param mixed $request
     * @return array<string, mixed>
     */
    public function handleManualReceipt($request): array
    {
        $id = is_object($request) && method_exists($request, 'get_param') ? (string) $request->get_param('id') : '';
        $params = is_object($request) && method_exists($request, 'get_json_params') ? (array) $request->get_json_params() : [];
        $accountId = (string) ($params['account_id'] ?? '');
        $action = (string) ($params['action'] ?? 'confirm');

        if ($action === 'revoke') {
            $group = $this->publicationService->revokeManualConfirmation($id, $accountId);
            return [
                'ok'         => true,
                'post_group' => $group,
                'summary'    => $this->preflightService->evaluatePostGroupSummary($group),
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
        ];
    }

    /**
     * @param mixed $request
     * @return array<string, mixed>
     */
    public function handleExport($request): array
    {
        $format = is_object($request) && method_exists($request, 'get_param')
            ? (string) ($request->get_param('format') ?? 'json')
            : 'json';
        $brand = is_object($request) && method_exists($request, 'get_param')
            ? (string) ($request->get_param('brand_id') ?? 'all')
            : 'all';

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

    /**
     * @param mixed $request
     * @return array<string, mixed>
     */
    public function handleImport($request): array
    {
        $payload = is_object($request) && method_exists($request, 'get_json_params')
            ? (array) $request->get_json_params()
            : (is_array($request) ? $request : []);
        $stats = $this->backupExporter->validateAndImport($payload);
        return array_merge(['ok' => true], $stats);
    }

    /**
     * @param mixed $request
     * @return array<string, mixed>
     */
    public function handleSignedMediaPull($request): array
    {
        $assetId = is_object($request) && method_exists($request, 'get_param') ? (string) $request->get_param('asset_id') : '';
        $rendition = is_object($request) && method_exists($request, 'get_param') ? (string) ($request->get_param('rendition') ?? 'original') : 'original';
        $expires = is_object($request) && method_exists($request, 'get_param') ? (int) $request->get_param('expires') : 0;
        $sig = is_object($request) && method_exists($request, 'get_param') ? (string) $request->get_param('sig') : '';

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
        ];
    }
}
