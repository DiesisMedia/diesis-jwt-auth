<?php

declare(strict_types=1);

namespace Diesis\JwtAuth;

use Closure;

/**
 * Cloudflare Access signing keys for one issuer, cached network-wide.
 *
 * Policy, all in one place:
 * - a fetched key set is cached for 12 hours;
 * - every fetch attempt, successful or not, is recorded; a forced refresh
 *   within 5 minutes of the last attempt is not performed, so an unknown key
 *   id costs at most one extra fetch per 5 minutes and a request that finds
 *   nothing cached fetches once, never twice;
 * - when a fetch fails or returns a malformed body, a previously cached set
 *   keeps being served; with nothing cached, SigningKeysUnavailable is thrown.
 */
final class SigningKeyCache
{
    private const KEY_SET_PREFIX = 'diesis_jwt_auth_jwks_';
    private const LAST_FETCH_PREFIX = 'diesis_jwt_auth_jwks_refreshed_';
    private const KEY_SET_TTL = 12 * 3600;
    private const LAST_FETCH_TTL = 86400;
    private const REFRESH_INTERVAL = 5 * 60;
    private const FETCH_TIMEOUT = 5;

    private readonly string $issuer;
    private readonly string $keySetName;
    private readonly string $lastFetchName;
    private readonly Closure $fetch;
    private readonly Closure $clock;

    /**
     * @param callable(string): mixed $fetch returns the decoded JSON body for a URL, or null on failure
     * @param callable(): int $clock
     */
    public function __construct(
        string $issuer,
        private readonly TransientStore $store,
        callable $fetch,
        callable $clock,
    ) {
        $this->issuer = rtrim($issuer, '/');
        $suffix = substr(hash('sha256', $this->issuer), 0, 16);
        $this->keySetName = self::KEY_SET_PREFIX . $suffix;
        $this->lastFetchName = self::LAST_FETCH_PREFIX . $suffix;
        $this->fetch = Closure::fromCallable($fetch);
        $this->clock = Closure::fromCallable($clock);
    }

    public static function forWordPress(string $issuer): self
    {
        return new self(
            $issuer,
            new WordPressTransientStore(),
            static function (string $url): mixed {
                $response = wp_safe_remote_get($url, [
                    'timeout' => self::FETCH_TIMEOUT,
                    'redirection' => 0,
                    'headers' => ['Accept' => 'application/json'],
                ]);

                if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                    return null;
                }

                return json_decode(wp_remote_retrieve_body($response), true);
            },
            static fn (): int => time(),
        );
    }

    /**
     * @return array<string, mixed> a JWKS document with at least one key
     * @throws SigningKeysUnavailable
     */
    public function keys(bool $forceRefresh): array
    {
        $cached = self::keySet($this->store->get($this->keySetName));

        if ($cached !== null && (! $forceRefresh || $this->fetchedRecently())) {
            return $cached;
        }

        if ($cached === null && $forceRefresh && $this->fetchedRecently()) {
            throw new SigningKeysUnavailable('Cloudflare Access signing keys were requested again too soon.');
        }

        $fetched = self::keySet(($this->fetch)($this->issuer . '/cdn-cgi/access/certs'));
        $this->store->set($this->lastFetchName, ($this->clock)(), self::LAST_FETCH_TTL);

        if ($fetched === null) {
            if ($cached !== null) {
                return $cached;
            }

            throw new SigningKeysUnavailable('Unable to retrieve Cloudflare Access signing keys.');
        }

        $this->store->set($this->keySetName, $fetched, self::KEY_SET_TTL);

        return $fetched;
    }

    public function purge(): void
    {
        $this->store->delete($this->keySetName);
        $this->store->delete($this->lastFetchName);
    }

    private function fetchedRecently(): bool
    {
        $lastFetch = $this->store->get($this->lastFetchName);

        return is_int($lastFetch) && $lastFetch > ($this->clock)() - self::REFRESH_INTERVAL;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function keySet(mixed $value): ?array
    {
        if (! is_array($value) || ! isset($value['keys']) || ! is_array($value['keys']) || $value['keys'] === []) {
            return null;
        }

        return ['keys' => array_values($value['keys'])];
    }
}
