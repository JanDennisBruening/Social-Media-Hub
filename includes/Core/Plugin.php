<?php
/**
 * Zentraler Service-Container & Plugin-Kernel (`Plugin`).
 *
 * Verbindet alle Schichten (Security, Repositories, Provider-Steckmodule, Preflight,
 * Queue-Worker, Publication-Service, Komplett-Exporter, REST-API und Admin-UI)
 * und sorgt für nahtlose Kompatibilität mit `JDB_Wartungsmodus` sowie den anderen JDB-Plugins.
 *
 * @package SocialMediaHub\Core
 */

declare(strict_types=1);

namespace SocialMediaHub\Core;

use SocialMediaHub\Admin\AdminPage;
use SocialMediaHub\Domain\PreflightService;
use SocialMediaHub\Domain\PublicationService;
use SocialMediaHub\Infrastructure\Database\Repositories;
use SocialMediaHub\Infrastructure\Export\BackupExporter;
use SocialMediaHub\Infrastructure\Http\LiveApiClient;
use SocialMediaHub\Infrastructure\Queue\PublishQueueWorker;
use SocialMediaHub\Infrastructure\Security\Capabilities;
use SocialMediaHub\Infrastructure\Security\SignedMediaEndpoint;
use SocialMediaHub\Infrastructure\Security\TokenSafe;
use SocialMediaHub\Providers\ProviderRegistry;
use SocialMediaHub\Rest\RestController;

class Plugin
{
    private static ?self $instance = null;

    private EventBus $eventBus;
    private TokenSafe $tokenSafe;
    private SignedMediaEndpoint $signedMediaEndpoint;
    private LiveApiClient $liveApiClient;
    private Repositories $repositories;
    private ProviderRegistry $providerRegistry;
    private PreflightService $preflightService;
    private PublishQueueWorker $queueWorker;
    private PublicationService $publicationService;
    private BackupExporter $backupExporter;
    private RestController $restController;
    private AdminPage $adminPage;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct(?Repositories $customRepositories = null, ?TokenSafe $customTokenSafe = null)
    {
        $this->eventBus = new EventBus();
        $this->tokenSafe = $customTokenSafe ?? new TokenSafe();
        $this->signedMediaEndpoint = new SignedMediaEndpoint();
        $this->liveApiClient = new LiveApiClient();
        $this->repositories = $customRepositories ?? new Repositories($this->tokenSafe);
        $this->providerRegistry = new ProviderRegistry($this->signedMediaEndpoint, $this->liveApiClient);
        $this->preflightService = new PreflightService($this->repositories, $this->providerRegistry);
        $this->queueWorker = new PublishQueueWorker(
            $this->repositories,
            $this->providerRegistry,
            $this->tokenSafe,
            $this->eventBus
        );
        $this->publicationService = new PublicationService(
            $this->repositories,
            $this->preflightService,
            $this->queueWorker,
            $this->eventBus
        );
        $this->backupExporter = new BackupExporter($this->repositories, $this->tokenSafe);
        $this->restController = new RestController(
            $this->repositories,
            $this->providerRegistry,
            $this->preflightService,
            $this->publicationService,
            $this->backupExporter,
            $this->signedMediaEndpoint,
            $this->tokenSafe,
            $this->eventBus,
            $this->liveApiClient
        );
        $this->adminPage = new AdminPage($this);
    }

    public function boot(): void
    {
        if (function_exists('add_action')) {
            add_action('rest_api_init', [$this->restController, 'registerRoutes']);
            add_action('admin_menu', [$this->adminPage, 'registerAdminMenu']);
            add_action('admin_enqueue_scripts', [$this->adminPage, 'enqueueAssets']);
            add_action(Activator::CRON_PREFLIGHT_HOOK, [$this->preflightService, 'run48HourConnectionPreflight']);
        }

        // Gezielte Kompatibilität mit `JDB_Wartungsmodus`:
        // 1. Unser Plugin greift niemals in das öffentliche Frontend (`template_redirect`) ein;
        //    die Wartungsseite von `JDB_Wartungsmodus` bleibt im Frontend zu 100 % unberührt.
        // 2. Für `/wp-json/social-hub/v1/...`-Requests (entweder von berechtigten Social-Hub-Rollen
        //    oder beim kryptografisch HMAC-signierten Medien-Abruf durch Instagram/Meta unter `/media-pull`)
        //    wird die REST-Sperre von `JDB_Wartungsmodus` gezielt nur für diesen Request freigegeben.
        if (function_exists('add_filter')) {
            add_filter('admin_body_class', [$this->adminPage, 'filterAdminBodyClass']);
            add_filter('rest_pre_dispatch', [$this, 'ensureWartungsmodusRestCompatibility'], 10, 3);
        }
    }

    /**
     * Erlaubt verifizierte `/social-hub/v1/`-REST-Aufrufe auch bei aktivem `JDB_Wartungsmodus`,
     * ohne die REST-Sperre für öffentliche WordPress-Endpunkte (`/wp/v2/...`) aufzuheben.
     *
     * @param mixed $result
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public function ensureWartungsmodusRestCompatibility($result, $server, $request)
    {
        if (!is_object($request) || !method_exists($request, 'get_route')) {
            return $result;
        }

        $route = (string) $request->get_route();
        if (!str_starts_with($route, '/social-hub/v1/')) {
            return $result;
        }

        $isAuthorizedSocialHubUser = Capabilities::userCan(Capabilities::MANAGE_ALL)
            || Capabilities::userCan(Capabilities::EDIT_POSTS)
            || Capabilities::userCan(Capabilities::APPROVE_POSTS)
            || Capabilities::userCan(Capabilities::CLIENT_REVIEW);

        $isValidSignedMediaPull = false;
        if ($route === '/social-hub/v1/media-pull' && method_exists($request, 'get_param')) {
            $assetId = (string) ($request->get_param('asset_id') ?? '');
            $rendition = (string) ($request->get_param('rendition') ?? 'original');
            $expires = (int) ($request->get_param('expires') ?? 0);
            $sig = (string) ($request->get_param('sig') ?? '');
            $isValidSignedMediaPull = $this->signedMediaEndpoint->verifySignedPullRequest($assetId, $rendition, $expires, $sig);
        }

        if (($isAuthorizedSocialHubUser || $isValidSignedMediaPull) && function_exists('remove_filter')) {
            remove_filter('rest_pre_dispatch', ['JDB_Wartungsmodus', 'block_public_rest'], PHP_INT_MAX);
        }

        return $result;
    }

    public function eventBus(): EventBus
    {
        return $this->eventBus;
    }

    public function tokenSafe(): TokenSafe
    {
        return $this->tokenSafe;
    }

    public function signedMediaEndpoint(): SignedMediaEndpoint
    {
        return $this->signedMediaEndpoint;
    }

    public function repositories(): Repositories
    {
        return $this->repositories;
    }

    public function providerRegistry(): ProviderRegistry
    {
        return $this->providerRegistry;
    }

    public function preflightService(): PreflightService
    {
        return $this->preflightService;
    }

    public function queueWorker(): PublishQueueWorker
    {
        return $this->queueWorker;
    }

    public function publicationService(): PublicationService
    {
        return $this->publicationService;
    }

    public function backupExporter(): BackupExporter
    {
        return $this->backupExporter;
    }

    public function restController(): RestController
    {
        return $this->restController;
    }
}
