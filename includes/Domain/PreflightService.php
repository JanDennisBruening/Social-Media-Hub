<?php
/**
 * Domänen-Service für die plattform- und kontospezifische Vorprüfung (Preflight)
 * sowie den proaktiven 48-Stunden-Verbindungscheck.
 *
 * @package SocialMediaHub\Domain
 */

declare(strict_types=1);

namespace SocialMediaHub\Domain;

use SocialMediaHub\Infrastructure\Database\Repositories;
use SocialMediaHub\Providers\ProviderRegistry;

class PreflightService
{
    private Repositories $repositories;
    private ProviderRegistry $providerRegistry;

    public function __construct(Repositories $repositories, ProviderRegistry $providerRegistry)
    {
        $this->repositories = $repositories;
        $this->providerRegistry = $providerRegistry;
    }

    /**
     * @param array<string, mixed> $postGroup
     * @return array<string, mixed>
     */
    public function normalizePostGroupVariants(array $postGroup): array
    {
        $targets = array_values(array_unique(array_map('strval', (array) ($postGroup['targets'] ?? []))));
        $variants = is_array($postGroup['variants'] ?? null) ? $postGroup['variants'] : [];
        $primaryMediaId = (string) ($postGroup['primary_media_id'] ?? '');
        $primaryType = (string) ($postGroup['primary_type'] ?? 'image');

        foreach ($targets as $accountId) {
            $account = $this->repositories->findAccount($accountId);
            if ($account === null) {
                continue;
            }
            $platform = (string) ($account['platform'] ?? '');
            $provider = $this->providerRegistry->get($platform);
            $manifest = $provider !== null ? $provider->getManifest() : [];
            $formats = (array) ($manifest['formats'] ?? []);

            $existing = is_array($variants[$accountId] ?? null) ? $variants[$accountId] : [];
            $defaultFormat = $this->chooseDefaultFormat($formats, $primaryType);
            $formatKey = isset($existing['format_key']) && isset($formats[$existing['format_key']])
                ? (string) $existing['format_key']
                : $defaultFormat;

            $recommendedRendition = (string) ($formats[$formatKey]['recommended_rendition'] ?? '4:5');

            $variants[$accountId] = [
                'enabled'         => (bool) ($existing['enabled'] ?? true),
                'format_key'      => $formatKey,
                'text_override'   => (string) ($existing['text_override'] ?? ''),
                'media_ids'       => isset($existing['media_ids']) && is_array($existing['media_ids'])
                    ? array_values(array_map('strval', $existing['media_ids']))
                    : ($primaryMediaId !== '' ? [$primaryMediaId] : []),
                'rendition_key'   => (string) ($existing['rendition_key'] ?? $recommendedRendition),
                'metadata'        => is_array($existing['metadata'] ?? null) ? $existing['metadata'] : [],
                'delivery_status' => (string) ($existing['delivery_status'] ?? 'pending'),
                'remote_post_id'  => $existing['remote_post_id'] ?? null,
                'remote_url'      => $existing['remote_url'] ?? null,
                'completed_at'    => $existing['completed_at'] ?? null,
                'last_error'      => $existing['last_error'] ?? null,
            ];
        }

        $postGroup['targets'] = $targets;
        $postGroup['variants'] = $variants;
        return $postGroup;
    }

    /**
     * @param array<string, mixed> $postGroup
     * @return array<string, mixed>
     */
    public function checkTargetVariant(array $postGroup, string $accountId): array
    {
        $postGroup = $this->normalizePostGroupVariants($postGroup);
        $account = $this->repositories->findAccount($accountId);
        if ($account === null) {
            return [
                'key'            => 'unknown',
                'label'          => 'Konto fehlt',
                'method'         => 'unknown',
                'issues'         => ['Zielkonto existiert nicht mehr.'],
                'advice'         => [],
                'effective_text' => '',
                'grapheme_count' => 0,
                'char_limit'     => null,
            ];
        }

        $platform = (string) ($account['platform'] ?? '');
        $provider = $this->providerRegistry->get($platform);
        if ($provider === null) {
            return [
                'key'            => 'unknown',
                'label'          => 'Provider unbekannt',
                'method'         => 'unknown',
                'issues'         => ["Kein Provider-Adapter für Plattform „{$platform}“ registriert."],
                'advice'         => [],
                'effective_text' => '',
                'grapheme_count' => 0,
                'char_limit'     => null,
            ];
        }

        $variant = (array) ($postGroup['variants'][$accountId] ?? []);
        $mediaIds = (array) ($variant['media_ids'] ?? []);
        $resolvedMedia = [];
        foreach ($mediaIds as $mediaId) {
            $asset = $this->repositories->findMediaAsset((string) $mediaId);
            if ($asset !== null) {
                $resolvedMedia[] = $asset;
            }
        }

        return $provider->validateVariant($postGroup, $variant, $account, $resolvedMedia);
    }

    /**
     * @param array<string, mixed> $postGroup
     * @return array<string, mixed>
     */
    public function evaluatePostGroupSummary(array $postGroup): array
    {
        $postGroup = $this->normalizePostGroupVariants($postGroup);
        $summary = [
            'active'           => 0,
            'ready'            => 0,
            'manual'           => 0,
            'handoff'          => 0,
            'blocked'          => 0,
            'unknown'          => 0,
            'off'              => 0,
            'published_api'    => 0,
            'confirmed_manual' => 0,
            'handoff_open'     => 0,
            'failed'           => 0,
            'queued'           => 0,
            'composite_state'  => 'draft',
            'composite_label'  => 'In Vorbereitung',
            'per_target'       => [],
        ];

        foreach ((array) ($postGroup['targets'] ?? []) as $accountId) {
            $check = $this->checkTargetVariant($postGroup, (string) $accountId);
            $variant = (array) ($postGroup['variants'][$accountId] ?? []);
            $deliveryStatus = (string) ($variant['delivery_status'] ?? 'pending');

            $summary['per_target'][(string) $accountId] = array_merge($check, [
                'delivery_status' => $deliveryStatus,
                'remote_post_id'  => $variant['remote_post_id'] ?? null,
                'remote_url'      => $variant['remote_url'] ?? null,
                'completed_at'    => $variant['completed_at'] ?? null,
                'last_error'      => $variant['last_error'] ?? null,
            ]);

            $key = (string) ($check['key'] ?? 'unknown');
            $summary[$key] = ($summary[$key] ?? 0) + 1;
            if ($key !== 'off') {
                $summary['active']++;
            }

            if ($deliveryStatus === 'published') {
                $summary['published_api']++;
            } elseif ($deliveryStatus === 'manual_confirmed') {
                $summary['confirmed_manual']++;
            } elseif ($deliveryStatus === 'handoff_open') {
                $summary['handoff_open']++;
            } elseif (in_array($deliveryStatus, ['failed', 'failed_retryable', 'failed_permanent'], true)) {
                $summary['failed']++;
            } elseif (in_array($deliveryStatus, ['queued', 'scheduled'], true)) {
                $summary['queued']++;
            }
        }

        $totalDone = $summary['published_api'] + $summary['confirmed_manual'];
        if ($summary['active'] === 0) {
            $summary['composite_state'] = 'empty';
            $summary['composite_label'] = 'Keine Ziele aktiv';
        } elseif ($totalDone === $summary['active']) {
            $summary['composite_state'] = 'completed';
            $summary['composite_label'] = sprintf('Vollständig abgeschlossen (%d/%d)', $totalDone, $summary['active']);
        } elseif ($summary['failed'] > 0 && $totalDone > 0) {
            $summary['composite_state'] = 'partial_failure';
            $summary['composite_label'] = sprintf(
                'Teilversagen · %d veröffentlicht · %d fehlgeschlagen · %d offen',
                $totalDone,
                $summary['failed'],
                max(0, $summary['active'] - $totalDone - $summary['failed'])
            );
        } elseif ($totalDone > 0 && $totalDone < $summary['active']) {
            $summary['composite_state'] = 'partially_completed';
            $summary['composite_label'] = sprintf(
                'Teilweise offen · %d erledigt · %d ausstehend',
                $totalDone,
                $summary['active'] - $totalDone
            );
        } elseif (($postGroup['approval_status'] ?? 'none') === 'pending') {
            $summary['composite_state'] = 'review';
            $summary['composite_label'] = 'Wartet auf Freigabe';
        } elseif ($summary['blocked'] > 0) {
            $summary['composite_state'] = 'blocked';
            $summary['composite_label'] = sprintf(
                '%d %s prüfen (Blockiert)',
                $summary['blocked'],
                $summary['blocked'] === 1 ? 'Ziel' : 'Ziele'
            );
        } elseif ($summary['queued'] > 0) {
            $summary['composite_state'] = 'scheduled';
            $summary['composite_label'] = sprintf(
                '%d in Queue · %d manuell/Handoff',
                $summary['queued'],
                $summary['manual'] + $summary['handoff']
            );
        } else {
            $summary['composite_state'] = 'ready_mix';
            $summary['composite_label'] = sprintf(
                '%d API-bereit · %d manuell/Handoff',
                $summary['ready'],
                $summary['manual'] + $summary['handoff']
            );
        }

        return $summary;
    }

    /**
     * @return list<array{post_group_id: string, post_title: string, account_id: string, handle: string, issue: string}>
     */
    public function run48HourConnectionPreflight(?int $nowTimestamp = null): array
    {
        $now = $nowTimestamp ?? time();
        $horizon = $now + 48 * 3600;
        $warnings = [];

        foreach ($this->repositories->getPostGroups() as $group) {
            $scheduledStr = (string) ($group['scheduled_at'] ?? '');
            $scheduledTs = $scheduledStr !== '' ? strtotime($scheduledStr) : false;
            if ($scheduledTs === false || $scheduledTs < $now || $scheduledTs > $horizon) {
                continue;
            }

            foreach ((array) ($group['targets'] ?? []) as $accountId) {
                $account = $this->repositories->findAccount((string) $accountId);
                if ($account === null) {
                    continue;
                }
                $provider = $this->providerRegistry->get((string) ($account['platform'] ?? ''));
                if ($provider === null) {
                    continue;
                }
                $cap = $provider->evaluateAccountCapabilities($account);
                $expiresStr = (string) ($account['token_expires_at'] ?? '');
                $expiresTs = $expiresStr !== '' ? strtotime($expiresStr) : false;

                if ($cap['status'] === 'blocked' || ($expiresTs !== false && $expiresTs <= $horizon)) {
                    $warnings[] = [
                        'post_group_id' => (string) $group['id'],
                        'post_title'    => (string) $group['title'],
                        'account_id'    => (string) $account['id'],
                        'handle'        => (string) $account['handle'],
                        'issue'         => $cap['status'] === 'blocked'
                            ? $cap['reason']
                            : 'Zugangstoken läuft vor dem geplanten Versandtermin ab.',
                    ];
                }
            }
        }

        return $warnings;
    }

    /**
     * @param array<string, mixed> $formats
     */
    private function chooseDefaultFormat(array $formats, string $primaryType): string
    {
        if ($formats === []) {
            return 'text';
        }
        foreach ($formats as $key => $spec) {
            if ($primaryType === 'video' && ($spec['media_kind'] ?? '') === 'video') {
                return (string) $key;
            }
            if ($primaryType === 'carousel' && in_array($key, ['carousel', 'multiimage', 'album'], true)) {
                return (string) $key;
            }
            if ($primaryType === 'image' && ($spec['media_kind'] ?? '') === 'image') {
                return (string) $key;
            }
            if ($primaryType === 'text' && ($spec['media_kind'] ?? '') === 'none') {
                return (string) $key;
            }
        }
        return (string) array_key_first($formats);
    }
}
