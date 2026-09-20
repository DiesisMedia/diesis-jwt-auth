<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

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
        (new ReviewNudgePage())->register($pluginFile);

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

    /**
     * The raw request target. It is only compared against the configured
     * path patterns, never stored or output, and PathMatcher normalizes it
     * itself so that encoding tricks cannot dodge a protected pattern.
     */
    private function requestUri(): string
    {
        if (! isset($_SERVER['REQUEST_URI']) || ! is_string($_SERVER['REQUEST_URI'])) {
            return '/';
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- compared only, see above.
        return wp_unslash($_SERVER['REQUEST_URI']);
    }

    private function accessToken(): string
    {
        if (! isset($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION']) || ! is_string($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION'])) {
            return '';
        }

        return sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_ACCESS_JWT_ASSERTION']));
    }

    private function deny(DenialReason $reason): never
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- only with WP_DEBUG on.
            error_log('DIESIS JWT Auth for Cloudflare Access denied a request: ' . $reason->value);
        }

        nocache_headers();
        wp_die(
            esc_html__('Access denied.', 'diesis-jwt-auth'),
            esc_html__('Cloudflare Access authentication required', 'diesis-jwt-auth'),
            ['response' => 403]
        );
    }
}
