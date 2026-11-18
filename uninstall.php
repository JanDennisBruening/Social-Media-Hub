<?php
/**
 * Uninstall handler for Social Media Hub.
 *
 * Bewahrt alle Daten standardmäßig vor versehentlichem Löschen beim Plugin-Update.
 * Tabellen und Optionen werden nur entfernt, wenn die Konstante SMH_REMOVE_ALL_DATA_ON_UNINSTALL
 * oder die Systemeinstellung `remove_data_on_uninstall` explizit gesetzt wurde.
 *
 * @package SocialMediaHub
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = get_option('smh_system_settings', []);
$shouldRemove = (defined('SMH_REMOVE_ALL_DATA_ON_UNINSTALL') && SMH_REMOVE_ALL_DATA_ON_UNINSTALL === true)
    || (!empty($settings['remove_data_on_uninstall']));

if ($shouldRemove) {
    global $wpdb;
    $tables = [
        'smh_audit_events',
        'smh_receipts',
        'smh_jobs',
        'smh_revisions',
        'smh_variants',
        'smh_post_groups',
        'smh_media_assets',
        'smh_snippets',
        'smh_accounts',
        'smh_brands',
    ];
    foreach ($tables as $table) {
        $fullTable = $wpdb->prefix . $table;
        $wpdb->query("DROP TABLE IF EXISTS `{$fullTable}`");
    }
    delete_option('smh_db_version');
    delete_option('smh_system_settings');
    delete_option('smh_repository_state');
}
