<?php
/**
 * Plugin Name:       Social Media Hub
 * Plugin URI:        https://jandesigndigital.de/plugins/social-media-hub
 * Description:       Eigenständiges, aufgeräumtes WordPress-Backend-Plugin zur geführten Erstellung, Vorprüfung, 1-Klick-Veröffentlichung, manuellen Übergabe (z. B. WhatsApp-Kanäle) und revisionssicheren Dokumentation von Social-Media-Beiträgen – inklusive Komplett-Export.
 * Version:           2.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Jan Dennis Brüning
 * Author URI:        https://jandesigndigital.de
 * Text Domain:       social-media-hub
 * Domain Path:       /languages
 *
 * @package SocialMediaHub
 */

declare(strict_types=1);

namespace SocialMediaHub;

if (!defined('ABSPATH')) {
    exit;
}

define('SMH_VERSION', '2.0.0');
define('SMH_DB_VERSION', '2.0.0');
define('SMH_PLUGIN_FILE', __FILE__);
define('SMH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMH_PLUGIN_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'SocialMediaHub\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $file = SMH_PLUGIN_DIR . 'includes/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_readable($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, [Core\Activator::class, 'activate']);
register_deactivation_hook(__FILE__, [Core\Activator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    Core\Plugin::instance()->boot();
});
