<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

use RuntimeException;

final class Plugin
{
    private const JWKS_CACHE_KEY = 'diesis_wp_jwt_auth_jwks';
    private const JWKS_REFRESH_KEY = 'diesis_wp_jwt_auth_jwks_refreshed';
    private const MINUTE = 60;
    private const HOUR = 3600;
    private const DAY = 86400;

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
            fn (bool $forceRefresh): array => $this->jwks($settings->issuer, $forceRefresh),
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

    /**
     * The two network-wide site transient names that cache the JWKS for an
     * issuer. Centralized so that uninstall cleanup deletes exactly what
     * jwks() writes.
     *
     * @return array{0: string, 1: string} the key set and refresh-marker names
     */
    public static function jwksCacheKeys(string $issuer): array
    {
        $issuerHash = substr(hash('sha256', rtrim($issuer, '/')), 0, 16);

        return [
            self::JWKS_CACHE_KEY . '_' . $issuerHash,
            self::JWKS_REFRESH_KEY . '_' . $issuerHash,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(string $issuer, bool $forceRefresh): array
    {
        [$cacheKey, $refreshKey] = self::jwksCacheKeys($issuer);
        $cached = self::normalizeJwks(get_site_transient($cacheKey));

        if (! $forceRefresh && $cached !== null) {
            return $cached;
        }

        $lastRefresh = get_site_transient($refreshKey);

        if ($forceRefresh && is_int($lastRefresh) && $lastRefresh > time() - (5 * self::MINUTE) && $cached !== null) {
            return $cached;
        }

        $response = wp_safe_remote_get(
            rtrim($issuer, '/') . '/cdn-cgi/access/certs',
            [
                'timeout' => 5,
                'redirection' => 0,
                'headers' => ['Accept' => 'application/json'],
            ]
        );

        set_site_transient($refreshKey, time(), self::DAY);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            if ($cached !== null) {
                return $cached;
            }

            throw new RuntimeException('Unable to retrieve Cloudflare Access signing keys.');
        }

        $decoded = self::normalizeJwks(json_decode(wp_remote_retrieve_body($response), true));

        if ($decoded === null) {
            if ($cached !== null) {
                return $cached;
            }

            throw new RuntimeException('Cloudflare Access returned an invalid key set.');
        }

        set_site_transient($cacheKey, $decoded, 12 * self::HOUR);

        return $decoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function normalizeJwks(mixed $value): ?array
    {
        if (! is_array($value) || ! isset($value['keys']) || ! is_array($value['keys']) || $value['keys'] === []) {
            return null;
        }

        return ['keys' => array_values($value['keys'])];
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
