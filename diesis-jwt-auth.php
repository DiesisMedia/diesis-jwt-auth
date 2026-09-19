<?php

/**
 * Plugin Name: DIESIS JWT Auth for Cloudflare Access
 * Plugin URI: https://github.com/DiesisMedia/diesis-jwt-auth
 * Description: Validates Cloudflare Access JWTs at the WordPress origin for selected paths.
 * Version: 1.4.0
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Author: DIESIS Media - Florian Gratzl
 * Author URI: https://diesis.media
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: diesis-jwt-auth
 * Domain Path: /languages
 */

declare(strict_types=1);

namespace Diesis\JwtAuth;

if (! defined('ABSPATH')) {
    exit;
}

// Network-activated plugins need an explicit path, before enforcement can deny a request.
add_action(
    'init',
    static function (): void {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- WordPress 6.8 does not register bundled catalogs for network-activated plugins.
        load_plugin_textdomain('diesis-jwt-auth', false, dirname(plugin_basename(__FILE__)) . '/languages');
    },
    -101
);

if (! is_readable(__DIR__ . '/vendor/autoload.php')) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('DIESIS JWT Auth for Cloudflare Access is incomplete. Reinstall the release ZIP containing its dependencies.', 'diesis-jwt-auth');
            echo '</p></div>';
        }
    );

    return;
}

require __DIR__ . '/vendor/autoload.php';

Plugin::boot(__FILE__);
