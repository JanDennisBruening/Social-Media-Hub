<?php
/**
 * Verschlüsselter Zugangsdaten-Tresor (TokenSafe) für OAuth-Tokens, App-Passwörter und Bot-Schlüssel.
 *
 * Nutzt vorrangig Libsodium (`sodium_crypto_secretbox`) und fällt bei fehlender Extension
 * auf `openssl_encrypt` mit `aes-256-gcm` zurück. Schlüsselmaterial wird aus einer eigenen
 * Konstante (`SMH_ENCRYPTION_KEY`) oder den WordPress-Salts abgeleitet.
 *
 * WICHTIG: Klartext-Tokens verlassen diesen Tresor nur für den unmittelbaren Server-zu-Provider-Aufruf
 * und werden niemals an Browser-Clients oder Backup-Exporte ausgeliefert.
 *
 * @package SocialMediaHub\Infrastructure\Security
 */

declare(strict_types=1);

namespace SocialMediaHub\Infrastructure\Security;

use RuntimeException;

class TokenSafe
{
    private string $binaryKey;

    public function __construct(?string $ExplicitKeyMaterial = null)
    {
        $material = $ExplicitKeyMaterial;
        if ($material === null || $material === '') {
            if (defined('SMH_ENCRYPTION_KEY') && is_string(SMH_ENCRYPTION_KEY) && SMH_ENCRYPTION_KEY !== '') {
                $material = SMH_ENCRYPTION_KEY;
            } elseif (function_exists('wp_salt')) {
                $material = wp_salt('auth') . '|' . wp_salt('secure_auth');
            } else {
                $material = 'smh-local-fallback-key-override-in-wp-config';
            }
        }
        $this->binaryKey = hash('sha256', 'smh-token-safe-v1:' . $material, true);
    }

    /**
     * Verschlüsselt ein Array aus geheimen Zugangsdaten (z. B. `access_token`, `refresh_token`).
     *
     * @param array<string, mixed> $secrets
     */
    public function encryptSecrets(array $secrets): string
    {
        $plaintext = json_encode($secrets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($plaintext)) {
            throw new RuntimeException('TokenSafe: Zugangsdaten konnten nicht serialisiert werden.');
        }

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->binaryKey);
            return 'v1:sodium:' . base64_encode($nonce . $cipher);
        }

        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $this->binaryKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
            if ($cipher === false) {
                throw new RuntimeException('TokenSafe: OpenSSL-Verschlüsselung fehlgeschlagen.');
            }
            return 'v1:aesgcm:' . base64_encode($iv . $tag . $cipher);
        }

        throw new RuntimeException('TokenSafe: Weder Libsodium noch OpenSSL stehen auf dem Server zur Verfügung.');
    }

    /**
     * Entschlüsselt ein gespeichertes Chiffrat zurück in das Zugangsdaten-Array.
     *
     * @return array<string, mixed>
     */
    public function decryptSecrets(string $encryptedPayload): array
    {
        if ($encryptedPayload === '') {
            return [];
        }

        if (str_starts_with($encryptedPayload, 'v1:sodium:')) {
            if (!function_exists('sodium_crypto_secretbox_open')) {
                throw new RuntimeException('TokenSafe: Libsodium zum Entschlüsseln nicht verfügbar.');
            }
            $raw = base64_decode(substr($encryptedPayload, strlen('v1:sodium:')), true);
            if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
                throw new RuntimeException('TokenSafe: Ungültiges Sodium-Chiffrat.');
            }
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->binaryKey);
            if ($plain === false) {
                throw new RuntimeException('TokenSafe: Entschlüsselung fehlgeschlagen (Schlüssel oder Daten manipuliert).');
            }
            $decoded = json_decode($plain, true);
            return is_array($decoded) ? $decoded : [];
        }

        if (str_starts_with($encryptedPayload, 'v1:aesgcm:')) {
            $raw = base64_decode(substr($encryptedPayload, strlen('v1:aesgcm:')), true);
            if ($raw === false || strlen($raw) < 28) {
                throw new RuntimeException('TokenSafe: Ungültiges AES-GCM-Chiffrat.');
            }
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $cipher = substr($raw, 28);
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->binaryKey, OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain === false) {
                throw new RuntimeException('TokenSafe: AES-GCM-Entschlüsselung fehlgeschlagen.');
            }
            $decoded = json_decode($plain, true);
            return is_array($decoded) ? $decoded : [];
        }

        throw new RuntimeException('TokenSafe: Unbekanntes Verschlüsselungsformat.');
    }

    /**
     * Erzeugt eine sichere, maskierte Zusammenfassung für die Admin-UI (niemals den echten Token).
     *
     * @param array<string, mixed> $secrets
     * @return array{has_access_token: bool, has_refresh_token: bool, token_hint: string}
     */
    public function redactForDisplay(array $secrets): array
    {
        $access = isset($secrets['access_token']) ? (string) $secrets['access_token'] : '';
        $refresh = isset($secrets['refresh_token']) ? (string) $secrets['refresh_token'] : '';
        $hint = $access !== ''
            ? '••••' . substr($access, -4)
            : 'Kein Token hinterlegt';

        return [
            'has_access_token'  => $access !== '',
            'has_refresh_token' => $refresh !== '',
            'token_hint'        => $hint,
        ];
    }
}
