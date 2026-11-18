<?php
/**
 * Aktivierungs- und Deaktivierungs-Lifecycle für das WordPress-Plugin (`Activator`).
 *
 * Legt beim Aktivieren die 10 eigenständigen Custom Tables an (`dbDelta`),
 * registriert die 4 WordPress-Capabilities und plant die Hintergrund-Checks ein.
 *
 * @package SocialMediaHub\Core
 */

declare(strict_types=1);

namespace SocialMediaHub\Core;

use SocialMediaHub\Infrastructure\Database\Repositories;
use SocialMediaHub\Infrastructure\Database\Schema;
use SocialMediaHub\Infrastructure\Security\Capabilities;

class Activator
{
    public const CRON_QUEUE_HOOK     = 'smh_process_publish_queue';
    public const CRON_PREFLIGHT_HOOK = 'smh_run_48h_connection_preflight';

    public static function activate(): void
    {
        Capabilities::registerRolesAndCapabilities();

        global $wpdb;
        if (isset($wpdb) && is_object($wpdb)) {
            $prefix = (string) ($wpdb->prefix ?? 'wp_');
            $charset = method_exists($wpdb, 'get_charset_collate') ? (string) $wpdb->get_charset_collate() : '';
            $tables = Schema::getTableDefinitions($prefix, $charset);
            if (defined('ABSPATH') && is_readable(ABSPATH . 'wp-admin/includes/upgrade.php')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                if (function_exists('dbDelta')) {
                    foreach ($tables as $sql) {
                        dbDelta($sql);
                    }
                }
            }
        }

        if (function_exists('update_option')) {
            update_option('smh_db_version', SMH_DB_VERSION);
        }

        $repo = new Repositories();
        $repo->persist();

        if (function_exists('wp_next_scheduled') && function_exists('wp_schedule_event')) {
            if (!wp_next_scheduled(self::CRON_QUEUE_HOOK)) {
                wp_schedule_event(time() + 60, 'hourly', self::CRON_QUEUE_HOOK);
            }
            if (!wp_next_scheduled(self::CRON_PREFLIGHT_HOOK)) {
                wp_schedule_event(time() + 300, 'twicedaily', self::CRON_PREFLIGHT_HOOK);
            }
        }
    }

    public static function deactivate(): void
    {
        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::CRON_QUEUE_HOOK);
            wp_clear_scheduled_hook(self::CRON_PREFLIGHT_HOOK);
        }
    }
}
