<?php
/**
 * Domänen-Service für Freigaben, unveränderliche Revisions-Snapshots (`smh_revisions`),
 * unabhängige Versandaufträge (`PublicationJob`), Einzel-Wiederholung bei Teilversagen
 * und revisionssichere manuelle Nachweise (`ManualReceipt`).
 *
 * @package SocialMediaHub\Domain
 */

declare(strict_types=1);

namespace SocialMediaHub\Domain;

use RuntimeException;
use SocialMediaHub\Core\EventBus;
use SocialMediaHub\Infrastructure\Database\Repositories;
use SocialMediaHub\Infrastructure\Queue\PublishQueueWorker;

class PublicationService
{
    private Repositories $repositories;
    private PreflightService $preflightService;
    private PublishQueueWorker $queueWorker;
    private EventBus $eventBus;

    public function __construct(
        Repositories $repositories,
        PreflightService $preflightService,
        PublishQueueWorker $queueWorker,
        EventBus $eventBus
    ) {
        $this->repositories = $repositories;
        $this->preflightService = $preflightService;
        $this->queueWorker = $queueWorker;
        $this->eventBus = $eventBus;
    }

    /**
     * @param array<string, mixed> $postGroup
     * @return array<string, mixed>
     */
    public function createImmutableRevisionSnapshot(
        array &$postGroup,
        string $reason = 'publish',
        int $actorId = 1
    ): array {
        $postGroup = $this->preflightService->normalizePostGroupVariants($postGroup);
        $existingRevisions = $this->repositories->getRevisions((string) $postGroup['id']);
        $nextNumber = count($existingRevisions) + 1;

        $snapshotPayload = [
            'post_group_id' => $postGroup['id'],
            'brand_id'      => $postGroup['brand_id'],
            'title'         => $postGroup['title'],
            'master_text'   => $postGroup['master_text'],
            'external_url'  => $postGroup['external_url'] ?? '',
            'targets'       => $postGroup['targets'],
            'variants'      => $postGroup['variants'],
        ];
        $snapshotJson = json_encode($snapshotPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        $snapshotHash = hash('sha256', $snapshotJson);

        $revision = [
            'id'              => sprintf('rev_%s_v%d_%s', $postGroup['id'], $nextNumber, substr($snapshotHash, 0, 6)),
            'post_group_id'   => (string) $postGroup['id'],
            'revision_number' => $nextNumber,
            'reason'          => $reason,
            'snapshot_hash'   => $snapshotHash,
            'snapshot_data'   => $snapshotPayload,
            'created_by'      => $actorId,
            'created_at'      => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $this->repositories->addRevision($revision);
        $postGroup['current_revision_id'] = $revision['id'];
        $this->repositories->savePostGroup($postGroup);

        return $revision;
    }

    /**
     * @return array<string, mixed>
     */
    public function approvePostGroup(string $postGroupId, int $actorId = 1): array
    {
        $postGroup = $this->repositories->findPostGroup($postGroupId);
        if ($postGroup === null) {
            throw new RuntimeException("Beitragsverbund {$postGroupId} nicht gefunden.");
        }

        $postGroup['approval_status'] = 'approved';
        $postGroup['approved_by'] = $actorId;
        $postGroup['approved_at'] = gmdate('Y-m-d\TH:i:s\Z');
        $revision = $this->createImmutableRevisionSnapshot($postGroup, 'approval', $actorId);

        $this->repositories->recordAudit(
            'post_group.approved',
            'Freigabe erteilt & Revisions-Snapshot gesichert',
            sprintf('%s · Revision #%d (%s)', (string) $postGroup['title'], $revision['revision_number'], $revision['id']),
            (string) $postGroup['brand_id'],
            (string) $postGroup['id'],
            null,
            $actorId
        );

        $this->eventBus->dispatch('post_group.approved', [
            'post_group_id' => $postGroup['id'],
            'brand_id'      => $postGroup['brand_id'],
            'revision_id'   => $revision['id'],
        ]);

        return $postGroup;
    }

    /**
     * @param array<string, mixed> $changes
     * @return array<string, mixed>
     */
    public function updateTargetVariant(string $postGroupId, string $accountId, array $changes, int $actorId = 1): array
    {
        $postGroup = $this->repositories->findPostGroup($postGroupId);
        if ($postGroup === null) {
            throw new RuntimeException("Beitragsverbund {$postGroupId} nicht gefunden.");
        }
        $postGroup = $this->preflightService->normalizePostGroupVariants($postGroup);

        $current = (array) ($postGroup['variants'][$accountId] ?? []);
        if (isset($changes['metadata']) && is_array($changes['metadata'])) {
            $current['metadata'] = array_merge((array) ($current['metadata'] ?? []), $changes['metadata']);
            unset($changes['metadata']);
        }
        $updated = array_merge($current, $changes);

        if (!in_array($updated['delivery_status'] ?? 'pending', ['published', 'manual_confirmed'], true)) {
            $updated['delivery_status'] = 'pending';
        }

        $postGroup['variants'][$accountId] = $updated;
        return $this->repositories->savePostGroup($postGroup);
    }

    /**
     * @param list<string>|null $onlyAccountIds
     * @return array<string, mixed>
     */
    public function dispatchPostGroup(
        string $postGroupId,
        string $mode = 'publish_now',
        ?array $onlyAccountIds = null,
        int $actorId = 1
    ): array {
        $postGroup = $this->repositories->findPostGroup($postGroupId);
        if ($postGroup === null) {
            throw new RuntimeException("Beitragsverbund {$postGroupId} nicht gefunden.");
        }

        if (($postGroup['approval_status'] ?? 'none') === 'pending') {
            throw new RuntimeException('Dieser Beitragsverbund wartet noch auf seine Pflicht-Freigabe.');
        }

        $revision = $this->createImmutableRevisionSnapshot($postGroup, $mode, $actorId);
        $targetIds = (array) ($postGroup['targets'] ?? []);
        if ($onlyAccountIds !== null && $onlyAccountIds !== []) {
            $targetIds = array_values(array_intersect($targetIds, $onlyAccountIds));
        }

        $queuedOrPublished = 0;
        $handoffPrepared = 0;
        $manualPrepared = 0;
        $skippedAlreadyDone = 0;
        $blockedOrFailed = 0;
        $dispatchedJobs = [];

        foreach ($targetIds as $accountId) {
            $accountId = (string) $accountId;
            $variant = (array) ($postGroup['variants'][$accountId] ?? []);
            $currentStatus = (string) ($variant['delivery_status'] ?? 'pending');

            if (in_array($currentStatus, ['published', 'manual_confirmed'], true)) {
                $skippedAlreadyDone++;
                continue;
            }

            $check = $this->preflightService->checkTargetVariant($postGroup, $accountId);
            if ($check['key'] === 'off') {
                continue;
            }

            if ($check['key'] === 'blocked' || $check['key'] === 'unknown') {
                $postGroup['variants'][$accountId]['delivery_status'] = 'failed_permanent';
                $postGroup['variants'][$accountId]['last_error'] = implode(' ', $check['issues'] ?: $check['advice']);
                $blockedOrFailed++;
                continue;
            }

            if ($check['key'] === 'manual') {
                $postGroup['variants'][$accountId]['delivery_status'] = 'manual_pending';
                $manualPrepared++;
                continue;
            }

            if ($check['key'] === 'handoff') {
                $postGroup['variants'][$accountId]['delivery_status'] = 'handoff_open';
                $handoffPrepared++;
                continue;
            }

            $idempotencyKey = sprintf('idem_%s_%s', $postGroupId, $accountId);
            $existingJob = $this->repositories->findJobByIdempotencyKey($idempotencyKey);

            if ($existingJob !== null && ($existingJob['job_status'] ?? '') === 'published') {
                $skippedAlreadyDone++;
                continue;
            }

            $job = $this->repositories->saveJob([
                'id'              => $existingJob['id'] ?? null,
                'post_group_id'   => $postGroupId,
                'variant_id'      => $postGroupId . ':' . $accountId,
                'account_id'      => $accountId,
                'revision_id'     => $revision['id'],
                'idempotency_key' => $idempotencyKey,
                'job_stage'       => $mode === 'schedule' ? 'scheduled' : 'queued',
                'job_status'      => $mode === 'schedule' ? 'scheduled' : 'queued',
                'attempt_count'   => 0,
                'max_attempts'    => 3,
                'next_attempt_at' => $mode === 'schedule' ? ($postGroup['scheduled_at'] ?? gmdate('Y-m-d\TH:i:s\Z')) : gmdate('Y-m-d\TH:i:s\Z'),
            ]);

            if ($mode === 'schedule') {
                $postGroup['variants'][$accountId]['delivery_status'] = 'scheduled';
                $queuedOrPublished++;
                $dispatchedJobs[] = $job;
            } else {
                $this->repositories->savePostGroup($postGroup);
                $processedJob = $this->queueWorker->processJob($job['id']);
                $postGroup = $this->repositories->findPostGroup($postGroupId) ?? $postGroup;
                $dispatchedJobs[] = $processedJob;
                if (($processedJob['job_status'] ?? '') === 'published') {
                    $queuedOrPublished++;
                } else {
                    $blockedOrFailed++;
                }
            }
        }

        $postGroup = $this->repositories->savePostGroup($postGroup);
        $summary = $this->preflightService->evaluatePostGroupSummary($postGroup);

        $this->repositories->recordAudit(
            'post_group.dispatched',
            sprintf('Ausspielung (%s) für „%s“ ausgeführt', $mode === 'schedule' ? 'Planung' : 'Sofortversand', (string) $postGroup['title']),
            sprintf(
                'API: %d · Handoff: %d · Manuell: %d · Übersprungen (bereits live): %d · Blockiert/Fehler: %d',
                $queuedOrPublished,
                $handoffPrepared,
                $manualPrepared,
                $skippedAlreadyDone,
                $blockedOrFailed
            ),
            (string) $postGroup['brand_id'],
            (string) $postGroup['id'],
            null,
            $actorId
        );

        return [
            'revision_id'          => (string) $revision['id'],
            'queued_or_published'  => $queuedOrPublished,
            'handoff_prepared'     => $handoffPrepared,
            'manual_prepared'      => $manualPrepared,
            'skipped_already_done' => $skippedAlreadyDone,
            'blocked_or_failed'    => $blockedOrFailed,
            'jobs'                 => $dispatchedJobs,
            'summary'              => $summary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmManualPublication(
        string $postGroupId,
        string $accountId,
        string $externalUrl = '',
        string $notes = '',
        int $actorId = 1
    ): array {
        $postGroup = $this->repositories->findPostGroup($postGroupId);
        if ($postGroup === null) {
            throw new RuntimeException("Beitragsverbund {$postGroupId} nicht gefunden.");
        }
        $postGroup = $this->preflightService->normalizePostGroupVariants($postGroup);
        $check = $this->preflightService->checkTargetVariant($postGroup, $accountId);

        if (!in_array($check['method'], ['manual', 'handoff'], true)) {
            throw new RuntimeException('Nur manuelle Kanäle oder App-Übergaben können manuell als veröffentlicht bestätigt werden.');
        }

        $externalUrl = trim($externalUrl);
        if ($externalUrl !== '' && !preg_match('~^https?://~i', $externalUrl)) {
            throw new RuntimeException('Bitte eine gültige http(s)-Adresse für den externen Beitragsnachweis angeben.');
        }

        $revision = $this->createImmutableRevisionSnapshot($postGroup, 'manual_confirm', $actorId);
        $now = gmdate('Y-m-d\TH:i:s\Z');

        $receipt = $this->repositories->saveReceipt([
            'post_group_id' => $postGroupId,
            'variant_id'    => $postGroupId . ':' . $accountId,
            'account_id'    => $accountId,
            'revision_id'   => $revision['id'],
            'receipt_state' => 'confirmed',
            'external_url'  => $externalUrl,
            'notes'         => trim($notes),
            'confirmed_by'  => $actorId,
            'confirmed_at'  => $now,
            'revoked_at'    => null,
        ]);

        $postGroup['variants'][$accountId]['delivery_status'] = 'manual_confirmed';
        $postGroup['variants'][$accountId]['remote_url'] = $externalUrl;
        $postGroup['variants'][$accountId]['completed_at'] = $now;
        $postGroup['variants'][$accountId]['receipt_id'] = $receipt['id'];
        $this->repositories->savePostGroup($postGroup);

        $account = $this->repositories->findAccount($accountId);
        $this->repositories->recordAudit(
            'manual_publication.confirmed',
            'Externe Veröffentlichung bestätigt',
            sprintf('%s · %s (%s)', (string) $postGroup['title'], (string) ($account['handle'] ?? $accountId), $externalUrl ?: 'ohne URL'),
            (string) $postGroup['brand_id'],
            $postGroupId,
            $accountId,
            $actorId
        );

        $this->eventBus->dispatch('manual_publication.confirmed', [
            'post_group_id' => $postGroupId,
            'account_id'    => $accountId,
            'receipt_id'    => $receipt['id'],
        ]);

        return $receipt;
    }

    /**
     * @return array<string, mixed>
     */
    public function revokeManualConfirmation(string $postGroupId, string $accountId, int $actorId = 1): array
    {
        $postGroup = $this->repositories->findPostGroup($postGroupId);
        if ($postGroup === null) {
            throw new RuntimeException("Beitragsverbund {$postGroupId} nicht gefunden.");
        }
        $postGroup = $this->preflightService->normalizePostGroupVariants($postGroup);

        $check = $this->preflightService->checkTargetVariant($postGroup, $accountId);
        $postGroup['variants'][$accountId]['delivery_status'] = $check['method'] === 'handoff' ? 'handoff_open' : 'manual_pending';
        $postGroup['variants'][$accountId]['completed_at'] = null;
        $this->repositories->savePostGroup($postGroup);

        $this->repositories->recordAudit(
            'manual_publication.revoked',
            'Manueller Veröffentlichungsnachweis widerrufen',
            sprintf('%s · Konto %s wieder als offen markiert.', (string) $postGroup['title'], $accountId),
            (string) $postGroup['brand_id'],
            $postGroupId,
            $accountId,
            $actorId
        );

        return $postGroup;
    }
}
