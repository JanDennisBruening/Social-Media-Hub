<?php
/**
 * WordPress-Admin-Integration (`AdminPage` v2.0.0).
 *
 * - Reduziert beim Öffnen des Social Media Hub automatisch die WordPress-Hauptnavigation (`folded`),
 *   sodass maximale Arbeitsfläche entsteht (lässt sich jederzeit über WP wieder ausklappen).
 * - Bindet die native WordPress-Mediathek (`wp_enqueue_media()`) für echten Bild-/Video-Upload ein.
 * - Lädt CSS und JS strikt nur auf der eigenen Plugin-Seite (`toplevel_page_social-media-hub`).
 *
 * @package SocialMediaHub\Admin
 */

declare(strict_types=1);

namespace SocialMediaHub\Admin;

use SocialMediaHub\Core\Plugin;
use SocialMediaHub\Infrastructure\Security\Capabilities;

class AdminPage
{
    public const MENU_SLUG = 'social-media-hub';

    private Plugin $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function registerAdminMenu(): void
    {
        if (!function_exists('add_menu_page')) {
            return;
        }

        add_menu_page(
            'Social Media Hub',
            'Social Hub',
            Capabilities::CLIENT_REVIEW,
            self::MENU_SLUG,
            [$this, 'renderAdminApp'],
            'dashicons-share',
            31
        );
    }

    /**
     * Klappen der WordPress-Hauptnavigation (`folded`) serverseitig vor dem ersten Rendern,
     * sobald der Benutzer den Social Media Hub öffnet.
     */
    public function filterAdminBodyClass(string $classes): string
    {
        if (!function_exists('get_current_screen')) {
            return $classes;
        }
        $screen = get_current_screen();
        if ($screen !== null && str_contains((string) $screen->id, self::MENU_SLUG)) {
            if (!str_contains(' ' . $classes . ' ', ' folded ')) {
                $classes .= ' folded';
            }
            $classes .= ' smh-admin-screen';
        }
        return $classes;
    }

    public function enqueueAssets(string $hookSuffix = ''): void
    {
        if (!str_contains($hookSuffix, self::MENU_SLUG)) {
            return;
        }

        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }

        if (function_exists('wp_enqueue_style') && function_exists('wp_enqueue_script')) {
            wp_enqueue_style(
                'smh-admin-app',
                SMH_PLUGIN_URL . 'assets/css/admin-app.css',
                [],
                SMH_VERSION
            );

            wp_enqueue_script(
                'smh-admin-app',
                SMH_PLUGIN_URL . 'assets/js/admin-app.js',
                [],
                SMH_VERSION,
                true
            );

            if (function_exists('wp_localize_script')) {
                wp_localize_script('smh-admin-app', 'SMH_BOOT', [
                    'restUrl'      => function_exists('rest_url') ? esc_url_raw((string) rest_url('social-hub/v1')) : '/wp-json/social-hub/v1',
                    'nonce'        => function_exists('wp_create_nonce') ? wp_create_nonce('wp_rest') : '',
                    'siteUrl'      => function_exists('home_url') ? esc_url_raw((string) home_url('/')) : '',
                    'initialState' => $this->plugin->restController()->buildSanitizedClientState(),
                ]);
            }
        }
    }

    public function renderAdminApp(): void
    {
        ?>
        <div id="smh-wordpress-admin-root" class="smh-wp-wrap">
            <noscript>Bitte aktiviere JavaScript, um den Social Media Hub zu verwenden.</noscript>
        </div>
        <?php
    }
}
