<?php

declare(strict_types=1);

namespace Diesis\WpJwtAuth;

/**
 * The plugin's configuration as an immutable value.
 *
 * The stored option row and admin form input are parsed by the same code, so
 * the rules for enabled, issuer, audience, allowed emails and path patterns
 * exist once. Input that cannot be trusted (an issuer outside Cloudflare
 * Access, enforcement without issuer or audience) is recorded in $problems
 * and disables enforcement instead of denying requests: a bad option row must
 * never lock the site.
 */
final class Settings
{
    public const OPTION = 'diesis_wp_jwt_auth';

    private const DEFAULT_PROTECTED_PATHS = ['/wp-login.php*', '/wp-admin', '/wp-admin/*'];

    /**
     * @param list<string> $allowedEmails
     * @param list<string> $protectedPaths
     * @param list<string> $excludedPaths
     * @param list<SettingsProblem> $problems
     */
    private function __construct(
        public readonly bool $enabled,
        public readonly string $issuer,
        public readonly string $audience,
        public readonly array $allowedEmails,
        public readonly array $protectedPaths,
        public readonly array $excludedPaths,
        public readonly array $problems,
    ) {
    }

    public static function defaults(): self
    {
        return self::parse([]);
    }

    /**
     * The shape persisted in the option row and returned to register_setting().
     *
     * @return array{
     *   enabled: bool,
     *   issuer: string,
     *   audience: string,
     *   allowed_emails: list<string>,
     *   protected_paths: list<string>,
     *   excluded_paths: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'issuer' => $this->issuer,
            'audience' => $this->audience,
            'allowed_emails' => $this->allowedEmails,
            'protected_paths' => $this->protectedPaths,
            'excluded_paths' => $this->excludedPaths,
        ];
    }

    /**
     * Parse the stored option row or raw admin form input.
     */
    public static function parse(mixed $input): self
    {
        $input = is_array($input) ? $input : [];
        $problems = [];
        $issuer = isset($input['issuer']) && is_string($input['issuer'])
            ? rtrim(esc_url_raw(trim($input['issuer'])), '/')
            : '';
        $audience = isset($input['audience']) && is_string($input['audience'])
            ? sanitize_text_field($input['audience'])
            : '';
        $enabled = isset($input['enabled']) && in_array($input['enabled'], ['1', 1, true], true);

        if ($issuer !== '' && ! self::isCloudflareAccessIssuer($issuer)) {
            $problems[] = SettingsProblem::InvalidIssuer;
            $issuer = '';
            $enabled = false;
        }

        if ($enabled && ($issuer === '' || $audience === '')) {
            $problems[] = SettingsProblem::MissingConfiguration;
            $enabled = false;
        }

        return new self(
            $enabled,
            $issuer,
            $audience,
            self::emails($input['allowed_emails'] ?? ''),
            self::paths($input['protected_paths'] ?? '', self::DEFAULT_PROTECTED_PATHS),
            self::paths($input['excluded_paths'] ?? '', []),
            $problems,
        );
    }

    private static function isCloudflareAccessIssuer(string $issuer): bool
    {
        $parts = wp_parse_url($issuer);

        return is_array($parts)
            && ($parts['scheme'] ?? '') === 'https'
            && isset($parts['host'])
            && is_string($parts['host'])
            && str_ends_with(strtolower($parts['host']), '.cloudflareaccess.com')
            && ! isset($parts['path']);
    }

    /**
     * @return list<string>
     */
    private static function emails(mixed $value): array
    {
        $emails = [];

        foreach (self::listValue($value) as $email) {
            $email = strtolower(sanitize_email($email));

            if ($email !== '' && is_email($email)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Keep only absolute paths with at most one wildcard, and that one trailing.
     *
     * @param list<string> $whenEmpty
     * @return list<string>
     */
    private static function paths(mixed $value, array $whenEmpty): array
    {
        $paths = [];

        foreach (self::listValue($value) as $path) {
            $path = trim(sanitize_text_field($path));

            if ($path === '' || ! str_starts_with($path, '/') || substr_count($path, '*') > 1) {
                continue;
            }

            if (str_contains($path, '*') && ! str_ends_with($path, '*')) {
                continue;
            }

            $paths[] = $path;
        }

        $paths = array_values(array_unique($paths));

        return $paths === [] ? $whenEmpty : $paths;
    }

    /**
     * Accept either a stored list or one-entry-per-line textarea input.
     *
     * @return list<string>
     */
    private static function listValue(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (! is_string($value)) {
            return [];
        }

        $lines = preg_split('/\R/u', $value);

        if (! is_array($lines)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }
}
