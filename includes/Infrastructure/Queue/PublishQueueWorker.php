<?php
/**
 * Asynchroner Queue-Worker (`PublishQueueWorker`) mit Idempotenz-Schutz und
 * exponentiellem Backoff (60s / 300s / 1800s) bei transienten Plattformfehlern (HTTP 429 / 5xx).
 *
 * @package SocialMediaHub\Infrastructure\Queue
 */

declare(strict_types=1);

namespace SocialMediaHub\Infrastructure\Queue;

use RuntimeException;
use SocialMediaHub\Core\EventBus;
use SocialMediaHub\Infrastructure\Database\Repositories;
use SocialMediaHub\Infrastructure\Security\TokenSafe;
use SocialMediaHub\Providers\ProviderRegistry;

class PublishQueueWorker
{
    /** @var list<int> Backoff-Intervalle in Sekunden (1 Min., 5 Min., 30 Min.) */
    private const BACKOFF_DELAYS = [60, 300, 1800];

    private Repositories $repositories;
    private ProviderRegistry $providerRegistry;
    private TokenSafe $tokenSafe;
    private EventBus $eventBus;

    public function __construct(
        Repositories $repositories,
        ProviderRegistry $providerRegistry,
        TokenSafe $tokenSafe,
        EventBus $eventBus
    ) {
        $this->repositories = $repositories;
        $this->providerRegistry = $providerRegistry;
        $this->tokenSafe = $tokenSafe;
        $this->eventBus = $eventBus;
    }

    /**
     * Verarbeitet einen einzelnen Zustell-Job idempotent.
     *
     * @return array<string, mixed>
     */
    public function processJob(string $jobId): array
    {
        $targetJob = null;
        foreach ($this->repositories->getJobs() as $j) {
            if (($j['id'] ?? '') === $jobId) {
                $targetJob = $j;
                break;
            }
        }
        if ($targetJob === null) {
            throw new RuntimeException("Queue-Job {$jobId} nicht gefunden.");
        }

        // Idempotenz-Garantie: Bereits veröffentlichte Jobs niemals erneut senden!
        if (($targetJob['job_status'] ?? '') === 'published') {
            return $targetJob;
        }

        $postGroupId = (string) ($targetJob['post_group_id'] ?? '');
        $accountId = (string) ($targetJob['account_id'] ?? '');
        $postGroup = $this->repositories->findPostGroup($postGroupId);
        $account = $this->repositories->findAccount($accountId);

        if ($postGroup === null || $account === null) {
            $targetJob['job_status'] = 'failed_permanent';
            $targetJob['last_error_code'] = 'missing_entity';
            $targetJob['last_error_message'] = 'Beitragsverbund oder Zielkonto existiert nicht mehr.';
            return $this->repositories->saveJob($targetJob);
        }

        $platform = (string) ($account['platform'] ?? '');
        $provider = $this->providerRegistry->get($platform);
        if ($provider === null) {
            $targetJob['job_status'] = 'failed_permanent';
            $targetJob['last_error_code'] = 'missing_provider';
            $targetJob['last_error_message'] = "Kein Provider-Adapter für {$platform} vorhanden.";
            return $this->repositories->saveJob($targetJob);
        }

        $encryptedSecrets = (string) ($account['encrypted_secrets'] ?? '');
        $decryptedSecrets = $encryptedSecrets !== '' ? $this->tokenSafe->decryptSecrets($encryptedSecrets) : [];
        $variant = (array) ($postGroup['variants'][$accountId] ?? []);

        // Effektiven Text und primäre Medien-URL für den echten Live-Versand auflösen
        $mediaIds = (array) ($variant['media_ids'] ?? []);
        $resolvedMedia = [];
        foreach ($mediaIds as $mediaId) {
            $asset = $this->repositories->findMediaAsset((string) $mediaId);
            if ($asset !== null) {
                $resolvedMedia[] = $asset;
            }
        }
        $validation = $provider->validateVariant($postGroup, $variant, $account, $resolvedMedia);
        $variant['_post_group'] = $postGroup;
        $variant['_effective_text'] = (string) ($validation['effective_text'] ?? ($postGroup['master_text'] ?? ''));
        $variant['_media_url'] = isset($resolvedMedia[0]['url']) && trim((string) $resolvedMedia[0]['url']) !== ''
            ? (string) $resolvedMedia[0]['url']
            : null;

        $targetJob['attempt_count'] = ((int) ($targetJob['attempt_count'] ?? 0)) + 1;
        $targetJob['job_stage'] = 'uploading_and_publishing';

        $result = $provider->executeDeliveryStep($targetJob, $variant, $account, $decryptedSecrets);
        $now = gmdate('Y-m-d\TH:i:s\Z');

        if ($result['outcome'] === 'published') {
            $targetJob['job_stage'] = 'completed';
            $targetJob['job_status'] = 'published';
            $targetJob['last_http_code'] = $result['http_code'];
            $targetJob['remote_container_id'] = $result['remote_container_id'] ?? null;
            $targetJob['remote_post_id'] = $result['remote_post_id'] ?? null;
            $targetJob['remote_url'] = $result['remote_url'] ?? null;
            $targetJob['last_error_code'] = null;
            $targetJob['last_error_message'] = null;
            $targetJob['completed_at'] = $now;

            $postGroup['variants'][$accountId]['delivery_status'] = 'published';
            $postGroup['variants'][$accountId]['remote_post_id'] = $targetJob['remote_post_id'];
            $postGroup['variants'][$accountId]['remote_url'] = $targetJob['remote_url'];
            $postGroup['variants'][$accountId]['completed_at'] = $now;
            $postGroup['variants'][$accountId]['last_error'] = null;
            $this->repositories->savePostGroup($postGroup);

            $savedJob = $this->repositories->saveJob($targetJob);
            $this->eventBus->dispatch('job.succeeded', [
                'job_id'         => $savedJob['id'],
                'post_group_id'  => $postGroupId,
                'account_id'     => $accountId,
                'remote_post_id' => $savedJob['remote_post_id'],
                'remote_url'     => $savedJob['remote_url'],
            ]);
            return $savedJob;
        }

        // Fehlerbehandlung: Transiente Fehler (HTTP 429 / 5xx) vs. permanente Fehler (HTTP 401 / 403)
        $isRetryable = !empty($result['retryable']) && $targetJob['attempt_count'] < ((int) ($targetJob['max_attempts'] ?? 3));
        $targetJob['last_http_code'] = $result['http_code'];
        $targetJob['last_error_code'] = $result['error_code'] ?? 'delivery_error';
        $targetJob['last_error_message'] = $result['error_message'] ?? 'Unbekannter Fehler beim Versand.';

        if ($isRetryable) {
            $backoffIndex = max(0, min(count(self::BACKOFF_DELAYS) - 1, $targetJob['attempt_count'] - 1));
            $delay = self::BACKOFF_DELAYS[$backoffIndex];
            $targetJob['job_status'] = 'failed_retryable';
            $targetJob['next_attempt_at'] = gmdate('Y-m-d\TH:i:s\Z', time() + $delay);
            $postGroup['variants'][$accountId]['delivery_status'] = 'failed_retryable';
        } else {
            $targetJob['job_status'] = 'failed_permanent';
            $targetJob['next_attempt_at'] = null;
            $postGroup['variants'][$accountId]['delivery_status'] = 'failed_permanent';
        }

        $postGroup['variants'][$accountId]['last_error'] = $targetJob['last_error_message'];
        $this->repositories->savePostGroup($postGroup);
        $savedJob = $this->repositories->saveJob($targetJob);

        $this->eventBus->dispatch('job.failed', [
            'job_id'        => $savedJob['id'],
            'post_group_id' => $postGroupId,
            'account_id'    => $accountId,
            'retryable'     => $isRetryable,
            'error'         => $savedJob['last_error_message'],
        ]);

        return $savedJob;
    }
}
