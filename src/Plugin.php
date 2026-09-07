<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

/**
 * WordPress adapter: reads the request, asks Enforcement, and ends the request
 * with a 403 when it is denied. Runs early on init so nothing later on the
 * hook sees an unauthenticated request to a protected path.
 */
final class Plugin
{
    private function __construct()
    {
    }

    public static function boot(string $pluginFile): void
    {
        $plugin = new self();
        (new SettingsPage())->register($pluginFile);

        add_action('init', [$plugin, 'enforce'], -100);
    }

    public function enforce(): void
    {
        $settings = Settings::parse(get_option(Settings::OPTION, []));
        $enforcement = new Enforcement(
            $settings,
            static fn (bool $forceRefresh): array => SigningKeyCache::forWordPress($settings->issuer)->keys($forceRefresh),
        );
        $denial = $enforcement->decide($this->requestUri(), $this->accessToken());

        if ($denial !== null) {
            $this->deny($denial);
        }
    }

    private function requestUri(): string
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

        return is_string($requestUri) ? wp_unslash($requestUri) : '/';
    }

    private function accessToken(): string
    {
        $token = $_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'] ?? '';

        return is_string($token) ? trim(wp_unslash($token)) : '';
    }

    private function deny(DenialReason $reason): never
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Diesis Cloudflare Access JWT denied a request: ' . $reason->value);
        }

        nocache_headers();
        wp_die(
            esc_html__('Access denied.', 'diesis-wp-jwt-auth'),
            esc_html__('Cloudflare Access authentication required', 'diesis-wp-jwt-auth'),
            ['response' => 403]
        );
    }
}
