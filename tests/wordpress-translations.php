<?php

declare(strict_types=1);

error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Run in a separate process with WordPress core and a plugin fixture without vendor/.
$locale = $argv[1];
define('ABSPATH', rtrim($argv[2], '/') . '/');
define('WPINC', 'wp-includes');
define('WP_PLUGIN_DIR', realpath($argv[3]));
$wp_plugin_paths = [];
define('WPMU_PLUGIN_DIR', __DIR__ . '/mu-plugins');
define('WP_LANG_DIR', __DIR__ . '/empty-language-packs');
define('WP_DEBUG', true);
define('HOUR_IN_SECONDS', 3600);

// No database, site, theme, or persistent cache is involved.
function get_template_directory(): string
{
    return __DIR__ . '/theme';
}

function get_stylesheet_directory(): string
{
    return __DIR__ . '/theme';
}

function wp_cache_get(string $key, string $group = ''): bool
{
    return false;
}

function wp_cache_set(string $key, mixed $value, string $group = '', int $expire = 0): bool
{
    return true;
}

function _doing_it_wrong(string $function, string $message, string $version): never
{
    throw new RuntimeException($message);
}

function wp_normalize_path(string $path): string
{
    return str_replace('\\', '/', $path);
}

function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function trailingslashit(string $value): string
{
    return rtrim($value, '/\\') . '/';
}

require ABSPATH . WPINC . '/plugin.php';
require ABSPATH . WPINC . '/pomo/mo.php';
require ABSPATH . WPINC . '/l10n/class-wp-translation-controller.php';
require ABSPATH . WPINC . '/l10n/class-wp-translations.php';
require ABSPATH . WPINC . '/l10n/class-wp-translation-file.php';
require ABSPATH . WPINC . '/l10n/class-wp-translation-file-mo.php';
require ABSPATH . WPINC . '/l10n/class-wp-translation-file-php.php';
require ABSPATH . WPINC . '/class-wp-textdomain-registry.php';
require ABSPATH . WPINC . '/l10n.php';

$wp_textdomain_registry = new WP_Textdomain_Registry();
$wp_textdomain_registry->init();
add_filter('pre_determine_locale', static fn (): string => $locale);
$domain = 'diesis-jwt-auth';
$plugin = WP_PLUGIN_DIR . '/' . $domain . '/diesis-jwt-auth.php';

// Actual plugin early-return branch for an installation missing dependencies.
// Like network activation, this does not pre-register the plugin's Domain Path.
require $plugin;
$checks = 0;
$catalog = require dirname($plugin) . '/languages/' . $domain . '-' . $locale . '.l10n.php';
$expectedMessages = $catalog['messages'];
$assert = static function (mixed $actual, mixed $expected, string $label) use (&$checks): void {
    if ($actual !== $expected) {
        throw new RuntimeException($label . ': expected ' . var_export($expected, true) . ', actual ' . var_export($actual, true));
    }
    ++$checks;
};
// Enforcement runs at -100: assert the translation has loaded by that instant.
add_action('init', static function () use ($assert, $domain, $expectedMessages): void {
    foreach ($expectedMessages as $source => $translated) {
        $assert(__($source, $domain), $translated, $source);
    }
}, -100);
do_action('after_setup_theme');
do_action('init');
$noticeSource = 'DIESIS JWT Auth for Cloudflare Access is incomplete. Reinstall the release ZIP containing its dependencies.';
ob_start();
do_action('admin_notices');
$actualNotice = ob_get_clean();
$expectedNotice = $expectedMessages[$noticeSource];
$assert($actualNotice, '<div class="notice notice-error"><p>' . esc_html($expectedNotice) . '</p></div>', 'Actual missing-dependency notice');
$assert(is_textdomain_loaded($domain), true, 'Domain loaded');
printf("PASS %s: %d translation checks\n", $locale, $checks);
