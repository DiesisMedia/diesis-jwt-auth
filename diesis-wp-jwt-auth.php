<?php

/**
 * Plugin Name: Diesis Cloudflare Access JWT Auth
 * Plugin URI: https://github.com/flowsworld/diesis-wp-jwt-auth
 * Description: Validates Cloudflare Access JWTs at the WordPress origin for selected paths.
 * Version: 1.0.1
 * Requires at least: 6.8
 * Requires PHP: 8.1
 * Author: Diesis
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: diesis-wp-jwt-auth
 */

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

if (! defined('ABSPATH')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if (! is_readable($autoload)) {
    add_action(
        'admin_notices',
        static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Diesis Cloudflare Access JWT Auth is incomplete. Reinstall the release ZIP containing its dependencies.', 'diesis-wp-jwt-auth');
            echo '</p></div>';
        }
    );

    return;
}

require $autoload;

Plugin::boot(__FILE__);
