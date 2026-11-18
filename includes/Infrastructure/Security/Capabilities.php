<?php
/**
 * Rollen-, Capability- & Marken-Zugriffskontrolle (Schutz gegen IDOR & unbefugte Aktionen).
 *
 * Definiert die vier abgestuften Berechtigungen aus dem Designkonzept (S08):
 * - `smh_manage_all`    (Administrator: Marken, Kanäle, OAuth-Tokens, Queue & Systemeinstellungen)
 * - `smh_edit_posts`    (Redaktion: Beiträge, Varianten, Medien & Snippets für zugewiesene Marken)
 * - `smh_approve_posts` (Freigabe / Lektorat: Beitragsverbünde prüfen, freigeben & Versand starten)
 * - `smh_client_review` (Kunde / Mandant: Nur zugewiesene Marke einsehen, Vorschauen prüfen & freigeben)
 *
 * @package SocialMediaHub\Infrastructure\Security
 */

declare(strict_types=1);

namespace SocialMediaHub\Infrastructure\Security;

class Capabilities
{
    public const MANAGE_ALL    = 'smh_manage_all';
    public const EDIT_POSTS    = 'smh_edit_posts';
    public const APPROVE_POSTS = 'smh_approve_posts';
    public const CLIENT_REVIEW = 'smh_client_review';

    /**
     * Registriert die Plugin-Capabilities auf den WordPress-Standardrollen und legt
     * bei Bedarf die spezialisierten Rollen `smh_approver` und `smh_client_reviewer` an.
     */
    public static function registerRolesAndCapabilities(): void
    {
        if (!function_exists('get_role')) {
            return;
        }

        $admin = get_role('administrator');
        if ($admin !== null) {
            $admin->add_cap(self::MANAGE_ALL);
            $admin->add_cap(self::EDIT_POSTS);
            $admin->add_cap(self::APPROVE_POSTS);
            $admin->add_cap(self::CLIENT_REVIEW);
        }

        $editor = get_role('editor');
        if ($editor !== null) {
            $editor->add_cap(self::EDIT_POSTS);
            $editor->add_cap(self::APPROVE_POSTS);
        }

        $author = get_role('author');
        if ($author !== null) {
            $author->add_cap(self::EDIT_POSTS);
        }

        if (function_exists('add_role')) {
            add_role('smh_approver', 'Social Hub · Freigabe & Lektorat', [
                'read'              => true,
                self::EDIT_POSTS    => true,
                self::APPROVE_POSTS => true,
            ]);
            add_role('smh_client_reviewer', 'Social Hub · Mandanten-Prüfer', [
                'read'              => true,
                self::CLIENT_REVIEW => true,
            ]);
        }
    }

    /**
     * Prüft, ob ein Benutzer eine bestimmte Capability besitzt.
     */
    public static function userCan(string $capability, ?int $userId = null): bool
    {
        if (function_exists('current_user_can') && $userId === null) {
            return current_user_can($capability) || current_user_can('manage_options');
        }
        if (function_exists('user_can') && $userId !== null) {
            return user_can($userId, $capability) || user_can($userId, 'manage_options');
        }
        return true;
    }

    /**
     * Prüft serverseitig, ob ein Benutzer auf eine bestimmte Marke (`Brand`) zugreifen darf (IDOR-Schutz).
     *
     * @param array<string, mixed> $brand
     * @param list<string>         $userCapabilities
     */
    public static function canAccessBrand(array $brand, int $userId, array $userCapabilities): bool
    {
        if (in_array(self::MANAGE_ALL, $userCapabilities, true)) {
            return true;
        }

        $allowedUsers = array_map('intval', (array) ($brand['allowed_user_ids'] ?? []));
        $isClientOnly = !in_array(self::EDIT_POSTS, $userCapabilities, true)
            && !in_array(self::APPROVE_POSTS, $userCapabilities, true)
            && in_array(self::CLIENT_REVIEW, $userCapabilities, true);

        if ($isClientOnly) {
            return in_array($userId, $allowedUsers, true);
        }

        if ($allowedUsers === []) {
            return in_array(self::EDIT_POSTS, $userCapabilities, true)
                || in_array(self::APPROVE_POSTS, $userCapabilities, true);
        }

        return in_array($userId, $allowedUsers, true);
    }
}
