<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

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
        $settings = Settings::fromStored(get_option(Settings::OPTION, []));

        if (! $settings->enabled) {
            return;
        }

        $requestUri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? wp_unslash($_SERVER['REQUEST_URI'])
            : '/';
        $matcher = new PathMatcher($settings->protectedPaths, $settings->excludedPaths);

        if (! $matcher->protects($requestUri)) {
            return;
        }

        $token = $this->accessToken();

        if ($token === '') {
            $this->deny('token_missing');
        }

        $validator = new ClaimsValidator(
            $settings->issuer,
            $settings->audience,
            $settings->allowedEmails,
        );
        $verifier = new AccessTokenVerifier(
            $validator,
            SigningKeyCache::forWordPress($settings->issuer)->keys(...),
        );
        $result = $verifier->verify($token);

        if (! $result->allowed) {
            $this->deny($result->reason);
        }
    }

    private function accessToken(): string
    {
        $token = $_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'] ?? '';

        return is_string($token) ? trim(wp_unslash($token)) : '';
    }

    private function deny(string $reason): never
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Diesis Cloudflare Access JWT denied a request: ' . sanitize_key($reason));
        }

        nocache_headers();
        wp_die(
            esc_html__('Access denied.', 'diesis-wp-jwt-auth'),
            esc_html__('Cloudflare Access authentication required', 'diesis-wp-jwt-auth'),
            ['response' => 403]
        );
    }
}
