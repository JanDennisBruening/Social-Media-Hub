<?php
/**
 * Komplett-Export & Umzugs-Paket (`BackupExporter`).
 *
 * @package SocialMediaHub\Infrastructure\Export
 */

declare(strict_types=1);

namespace SocialMediaHub\Infrastructure\Export;

use InvalidArgumentException;
use SocialMediaHub\Infrastructure\Database\Repositories;
use SocialMediaHub\Infrastructure\Security\TokenSafe;

class BackupExporter
{
    private Repositories $repositories;
    private TokenSafe $tokenSafe;

    public function __construct(Repositories $repositories, TokenSafe $tokenSafe)
    {
        $this->repositories = $repositories;
        $this->tokenSafe = $tokenSafe;
    }

    /**
     * Erzeugt ein vollständiges, portables Umzugs- und Backup-Paket als Array (JSON-serialisierbar).
     *
     * @return array<string, mixed>
     */
    public function createFullPortableExport(?string $brandFilter = null): array
    {
        $accounts = [];
        foreach ($this->repositories->getAccounts($brandFilter) as $account) {
            $encrypted = (string) ($account['encrypted_secrets'] ?? '');
            $decrypted = $encrypted !== '' ? $this->tokenSafe->decryptSecrets($encrypted) : [];
            $redacted = $this->tokenSafe->redactForDisplay($decrypted);

            unset($account['encrypted_secrets']);
            $account['security_redaction'] = $redacted;
            $accounts[] = $account;
        }

        $brands = $this->repositories->getBrands();
        if ($brandFilter !== null && $brandFilter !== '' && $brandFilter !== 'all') {
            $brands = array_values(array_filter(
                $brands,
                static fn(array $b): bool => ($b['id'] ?? '') === $brandFilter
            ));
        }

        $postGroups = $this->repositories->getPostGroups($brandFilter);
        $postIds = array_map(static fn(array $g): string => (string) $g['id'], $postGroups);

        $revisions = array_values(array_filter(
            $this->repositories->getRevisions(),
            static fn(array $r): bool => in_array((string) ($r['post_group_id'] ?? ''), $postIds, true)
        ));
        $jobs = array_values(array_filter(
            $this->repositories->getJobs(),
            static fn(array $j): bool => in_array((string) ($j['post_group_id'] ?? ''), $postIds, true)
        ));
        $receipts = array_values(array_filter(
            $this->repositories->getReceipts(),
            static fn(array $r): bool => in_array((string) ($r['post_group_id'] ?? ''), $postIds, true)
        ));

        return [
            'export_format'   => 'social-media-hub-portable-backup',
            'export_version'  => '1.0.0',
            'exported_at'     => gmdate('Y-m-d\TH:i:s\Z'),
            'secrets_included'=> false,
            'brand_filter'    => $brandFilter ?? 'all',
            'counts'          => [
                'brands'       => count($brands),
                'accounts'     => count($accounts),
                'media_assets' => count($this->repositories->getMediaAssets($brandFilter)),
                'snippets'     => count($this->repositories->getSnippets($brandFilter)),
                'post_groups'  => count($postGroups),
                'revisions'    => count($revisions),
                'jobs'         => count($jobs),
                'receipts'     => count($receipts),
            ],
            'data' => [
                'brands'       => $brands,
                'accounts'     => $accounts,
                'media_assets' => $this->repositories->getMediaAssets($brandFilter),
                'snippets'     => $this->repositories->getSnippets($brandFilter),
                'post_groups'  => $postGroups,
                'revisions'    => $revisions,
                'jobs'         => $jobs,
                'receipts'     => $receipts,
                'audit_events' => $this->repositories->getAuditEvents(250),
            ],
        ];
    }

    /**
     * Erzeugt einen übersichtlichen CSV-Export aller Beiträge und Kanal-Ausspielungen.
     */
    public function createCsvArchiveReport(?string $brandFilter = null): string
    {
        $headers = [
            'Beitrags-ID',
            'Marke',
            'Kampagne',
            'Titel',
            'Geplant am',
            'Freigabe',
            'Zielkonto',
            'Plattform',
            'Format',
            'Zustellstatus',
            'Externer Link / Nachweis',
        ];
        $lines = [implode(';', $headers)];

        foreach ($this->repositories->getPostGroups($brandFilter) as $group) {
            $brand = $this->repositories->findBrand((string) ($group['brand_id'] ?? ''));
            $brandName = (string) ($brand['name'] ?? $group['brand_id'] ?? '');
            foreach ((array) ($group['targets'] ?? []) as $accountId) {
                $account = $this->repositories->findAccount((string) $accountId);
                $variant = (array) ($group['variants'][$accountId] ?? []);
                $row = [
                    (string) ($group['id'] ?? ''),
                    $brandName,
                    (string) ($group['campaign'] ?? ''),
                    (string) ($group['title'] ?? ''),
                    (string) ($group['scheduled_at'] ?? ''),
                    (string) ($group['approval_status'] ?? ''),
                    (string) ($account['handle'] ?? $accountId),
                    (string) ($account['platform'] ?? ''),
                    (string) ($variant['format_key'] ?? ''),
                    (string) ($variant['delivery_status'] ?? 'pending'),
                    (string) ($variant['remote_url'] ?? ($group['external_url'] ?? '')),
                ];
                $escaped = array_map(
                    static fn(string $val): string => '"' . str_replace('"', '""', str_replace(["\r", "\n"], ' ', $val)) . '"',
                    $row
                );
                $lines[] = implode(';', $escaped);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Prüft und importiert ein portables Backup-Paket zurück in das System.
     *
     * @param array<string, mixed> $payload
     * @return array{imported_posts: int, imported_media: int, imported_brands: int}
     */
    public function validateAndImport(array $payload): array
    {
        if (($payload['export_format'] ?? '') !== 'social-media-hub-portable-backup' || !is_array($payload['data'] ?? null)) {
            throw new InvalidArgumentException('Ungültiges Backup-Format: Erwartet wurde `social-media-hub-portable-backup`.');
        }

        $data = $payload['data'];
        if (!is_array($data['brands'] ?? null) || !is_array($data['post_groups'] ?? null)) {
            throw new InvalidArgumentException('Backup-Paket unvollständig: Marken oder Beitragsverbünde fehlen.');
        }

        $state = &$this->repositories->getStateRef();
        $state['brands'] = array_values($data['brands']);
        $state['media_assets'] = array_values($data['media_assets'] ?? []);
        $state['snippets'] = array_values($data['snippets'] ?? []);
        $state['post_groups'] = array_values($data['post_groups']);
        $state['revisions'] = array_values($data['revisions'] ?? []);
        $state['receipts'] = array_values($data['receipts'] ?? []);
        $this->repositories->persist();

        $this->repositories->recordAudit(
            'system.backup_imported',
            'Portables Backup-Paket importiert',
            sprintf('%d Marken, %d Medien, %d Beitragsverbünde übernommen.', count($state['brands']), count($state['media_assets']), count($state['post_groups']))
        );

        return [
            'imported_brands' => count($state['brands']),
            'imported_media'  => count($state['media_assets']),
            'imported_posts'  => count($state['post_groups']),
        ];
    }
}
