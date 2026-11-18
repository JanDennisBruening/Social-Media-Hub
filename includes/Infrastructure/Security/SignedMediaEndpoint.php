<?php
/**
 * Kurzlebige, kryptografisch signierte Medien-Abhol-Links (Signed Pull URLs).
 *
 * @package SocialMediaHub\Infrastructure\Security
 */

declare(strict_types=1);

namespace SocialMediaHub\Infrastructure\Security;

class SignedMediaEndpoint
{
    private string $signingSecret;
    private string $siteBaseUrl;

    public function __construct(?string $signingSecret = null, ?string $siteBaseUrl = null)
    {
        if ($signingSecret !== null && $signingSecret !== '') {
            $this->signingSecret = $signingSecret;
        } elseif (function_exists('wp_salt')) {
            $this->signingSecret = wp_salt('NONCE_SALT');
        } else {
            $this->signingSecret = 'smh-signed-media-endpoint-default-secret';
        }

        if ($siteBaseUrl !== null && $siteBaseUrl !== '') {
            $this->siteBaseUrl = rtrim($siteBaseUrl, '/');
        } elseif (function_exists('site_url')) {
            $this->siteBaseUrl = rtrim((string) site_url(), '/');
        } else {
            $this->siteBaseUrl = 'https://example.com';
        }
    }

    /**
     * Erzeugt eine zeitlich begrenzte Abhol-URL für ein Medium + Rendition-Format.
     */
    public function createSignedPullUrl(
        string $assetId,
        string $renditionKey = 'original',
        int $ttlSeconds = 900,
        ?int $nowTimestamp = null
    ): string {
        $now = $nowTimestamp ?? time();
        $expiresAt = $now + max(60, $ttlSeconds);
        $signature = $this->computeSignature($assetId, $renditionKey, $expiresAt);

        $query = http_build_query([
            'asset_id'  => $assetId,
            'rendition' => $renditionKey,
            'expires'   => $expiresAt,
            'sig'       => $signature,
        ]);

        return $this->siteBaseUrl . '/wp-json/social-hub/v1/media-pull?' . $query;
    }

    /**
     * Prüft, ob eine angefragte Abhol-URL gültig und noch nicht abgelaufen ist.
     */
    public function verifySignedPullRequest(
        string $assetId,
        string $renditionKey,
        int $expiresAt,
        string $providedSignature,
        ?int $nowTimestamp = null
    ): bool {
        $now = $nowTimestamp ?? time();
        if ($assetId === '' || $providedSignature === '' || $expiresAt < $now) {
            return false;
        }
        $expected = $this->computeSignature($assetId, $renditionKey, $expiresAt);
        return hash_equals($expected, $providedSignature);
    }

    /**
     * Prüft, ob die aktuelle WordPress-Umgebung öffentlich über HTTPS erreichbar ist.
     */
    public function isPublicHttpsEnvironment(): bool
    {
        $parts = parse_url($this->siteBaseUrl);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            return false;
        }
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return false;
        }
        return true;
    }

    private function computeSignature(string $assetId, string $renditionKey, int $expiresAt): string
    {
        $payload = $assetId . '|' . $renditionKey . '|' . $expiresAt;
        return hash_hmac('sha256', $payload, $this->signingSecret);
    }
}
