<?php
/**
 * Steckmodul-Vertrag (ProviderInterface) für alle Social-Media- und Messenger-Kanäle.
 *
 * @package SocialMediaHub\Providers
 */

declare(strict_types=1);

namespace SocialMediaHub\Providers;

interface ProviderInterface
{
    public function getPlatformKey(): string;

    /**
     * @return array<string, mixed>
     */
    public function getManifest(): array;

    /**
     * @param array<string, mixed> $account
     * @return array{
     *   status: string,
     *   delivery_mode: string,
     *   reason: string,
     *   missing_scopes: list<string>,
     *   token_expired: bool,
     *   evaluated_at: string
     * }
     */
    public function evaluateAccountCapabilities(array $account): array;

    /**
     * @param array<string, mixed>       $postGroup
     * @param array<string, mixed>       $variant
     * @param array<string, mixed>       $account
     * @param list<array<string, mixed>> $resolvedMedia
     * @return array{
     *   key: string,
     *   label: string,
     *   method: string,
     *   issues: list<string>,
     *   advice: list<string>,
     *   effective_text: string,
     *   grapheme_count: int,
     *   char_limit: int|null
     * }
     */
    public function validateVariant(
        array $postGroup,
        array $variant,
        array $account,
        array $resolvedMedia
    ): array;

    /**
     * @param array<string, mixed> $job
     * @param array<string, mixed> $snapshotVariant
     * @param array<string, mixed> $account
     * @param array<string, mixed> $decryptedSecrets
     * @return array{
     *   outcome: string,
     *   http_code: int,
     *   remote_container_id?: string|null,
     *   remote_post_id?: string,
     *   remote_url?: string,
     *   error_code?: string,
     *   error_message?: string,
     *   retryable?: bool
     * }
     */
    public function executeDeliveryStep(
        array $job,
        array $snapshotVariant,
        array $account,
        array $decryptedSecrets
    ): array;
}
